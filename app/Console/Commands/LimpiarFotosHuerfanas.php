<?php

namespace App\Console\Commands;

use App\Models\DocumentosVerificacion;
use App\Models\FotoEvidencia;
use App\Models\FotoTrabajo;
use App\Models\User;
use App\Services\AlmacenFotos;
use App\Support\Auditoria;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Borra del almacenamiento (R2 o disco local) los archivos que ya no tiene ningún
 * registro en la base de datos: lo que queda cuando un borrado falla (AlmacenFotos::borrar
 * solo reporta el error) o cuando una subida se guardó pero la transacción se revirtió.
 *
 * Carpetas que revisa (ver los controladores que llaman a AlmacenFotos):
 *  - disco público : perfiles/{user}/…, portafolio/{servicio}/…
 *  - disco privado : evidencia/{pedido}/…, verificacion/{perfil}/…
 *
 * Protecciones:
 *  - Solo toca archivos con más de --horas de antigüedad (no pisa subidas en curso).
 *  - Lee primero el listado del almacenamiento y después la BD, para no marcar como
 *    huérfano un archivo que se registró mientras corría el comando.
 *  - Si la BD no tiene NINGUNA referencia en una carpeta que sí tiene archivos, lo
 *    trata como error de configuración (BD o disco equivocado) y no borra, salvo --forzar.
 *  - --limite acota cuántos archivos se borran por ejecución.
 *  - Siempre puedes probar antes con --dry-run.
 */
class LimpiarFotosHuerfanas extends Command
{
    protected $signature = 'fotos:limpiar-huerfanas
        {--dry-run : Solo muestra cuántos archivos se borrarían, sin borrar nada}
        {--horas=24 : Antigüedad mínima (en horas) para considerar huérfano un archivo}
        {--limite=1000 : Máximo de archivos a borrar por ejecución}
        {--forzar : Continúa aunque la BD no tenga referencias en una carpeta que sí tiene archivos}';

    protected $description = 'Elimina del almacenamiento las fotos y documentos que ya no tienen registro en la BD.';

    public function handle(AlmacenFotos $almacen): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $horas  = max(1, (int) $this->option('horas'));
        $limite = max(1, (int) $this->option('limite'));
        $corte  = now()->subHours($horas)->getTimestamp();

        $ambitos = [
            ['disco' => $almacen->discoPublico(), 'carpeta' => 'perfiles',     'refs' => fn () => $this->rutasDe(User::class, 'foto_path')],
            ['disco' => $almacen->discoPublico(), 'carpeta' => 'portafolio',   'refs' => fn () => $this->rutasDe(FotoTrabajo::class, 'ruta')],
            ['disco' => $almacen->discoPrivado(), 'carpeta' => 'evidencia',    'refs' => fn () => $this->rutasDe(FotoEvidencia::class, 'ruta')],
            ['disco' => $almacen->discoPrivado(), 'carpeta' => 'verificacion', 'refs' => fn () => $this->rutasDe(DocumentosVerificacion::class, 'ruta_cifrada')],
        ];

        $encontrados = 0;
        $borrados    = 0;
        $errores     = 0;

        foreach ($ambitos as $ambito) {
            ['disco' => $disco, 'carpeta' => $carpeta] = $ambito;
            $etiqueta = "{$disco}:{$carpeta}/";

            if ($borrados >= $limite) {
                $this->warn("Se alcanzó el límite de {$limite} archivos; el resto queda para la próxima ejecución.");
                break;
            }

            // 1) Listado del almacenamiento (antes de leer la BD, ver nota de la clase).
            try {
                $archivos = Storage::disk($disco)->allFiles($carpeta);
            } catch (Throwable $e) {
                report($e);
                $this->error("No se pudo listar {$etiqueta}: revisa credenciales y conexión del disco.");
                $errores++;
                continue;
            }

            if ($archivos === []) {
                $this->line("{$etiqueta} sin archivos.");
                continue;
            }

            // 2) Referencias válidas en la BD (imagen principal + su miniatura).
            $referenciadas = ($ambito['refs'])();

            if ($referenciadas === [] && ! $this->option('forzar')) {
                $this->error("{$etiqueta} tiene archivos pero la BD no tiene ninguna referencia: no se borra nada ahí. Verifica BD y disco, o usa --forzar.");
                $errores++;
                continue;
            }

            // 3) Huérfanos con la antigüedad mínima.
            $huerfanos = [];
            foreach ($archivos as $ruta) {
                if (isset($referenciadas[$ruta])) {
                    continue;
                }

                try {
                    if (Storage::disk($disco)->lastModified($ruta) > $corte) {
                        continue; // demasiado reciente: puede ser una subida en curso
                    }
                } catch (Throwable $e) {
                    report($e);
                    continue; // sin fecha confiable, por seguridad no se toca
                }

                $huerfanos[] = $ruta;
            }

            $encontrados += count($huerfanos);
            $this->line(sprintf('%s %d archivo(s), %d huérfano(s).', $etiqueta, count($archivos), count($huerfanos)));

            if ($huerfanos === []) {
                continue;
            }

            $huerfanos = array_slice($huerfanos, 0, $limite - $borrados);

            if ($this->output->isVerbose()) {
                foreach ($huerfanos as $ruta) {
                    $this->line("   · {$ruta}");
                }
            }

            if ($dryRun) {
                continue;
            }

            foreach (array_chunk($huerfanos, 100) as $lote) {
                try {
                    Storage::disk($disco)->delete($lote);
                    $borrados += count($lote);
                } catch (Throwable $e) {
                    report($e);
                    $errores++;
                    $this->error("Falló el borrado de un lote en {$etiqueta}; se reintentará en la próxima ejecución.");
                }
            }
        }

        if ($dryRun) {
            $this->info("Modo prueba: se borrarían {$encontrados} archivo(s) huérfano(s).");

            return $errores > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info("Archivos huérfanos borrados: {$borrados}.");

        if ($borrados > 0) {
            try {
                // Solo cantidades: nunca rutas ni contenido.
                Auditoria::registrar('almacenamiento.huerfanos_limpiados', 'almacenamiento', 0, null, [
                    'archivos' => $borrados,
                    'horas'    => $horas,
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Rutas que la BD referencia en $columna, incluidas las miniaturas (convención _m.webp).
     * Se lee sin Eloquent (toBase) para no pasar por accesores ni ocultar columnas.
     *
     * @param  class-string<Model>  $modelo
     * @return array<string, true>  conjunto: la ruta es la llave
     */
    private function rutasDe(string $modelo, string $columna): array
    {
        $conjunto = [];

        $modelo::query()
            ->toBase()
            ->whereNotNull($columna)
            ->where($columna, '!=', '')
            ->pluck($columna)
            ->each(function ($ruta) use (&$conjunto) {
                $conjunto[$ruta] = true;
                $conjunto[AlmacenFotos::miniaturaDe($ruta)] = true;
            });

        return $conjunto;
    }
}
