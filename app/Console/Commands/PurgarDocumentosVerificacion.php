<?php

namespace App\Console\Commands;

use App\Models\DocumentosVerificacion;
use App\Models\PerfilPrestador;
use App\Services\AlmacenFotos;
use App\Support\Auditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retención de datos (LFPDPPP, minimización): elimina el ARCHIVO de los documentos de
 * identidad ya resueltos pasado el plazo. Se conserva el registro (qué, cuándo, quién revisó).
 */
class PurgarDocumentosVerificacion extends Command
{
    protected $signature = 'verificacion:purgar-documentos {--dry-run : Solo muestra cuántos se purgarían}';

    protected $description = 'Elimina los archivos de documentos de verificación vencidos según la política de retención.';

    public function handle(AlmacenFotos $almacen): int
    {
        $limite = now()->subDays((int) config('marketplace.verificacion.retencion_dias'));

        $consulta = DocumentosVerificacion::query()
            ->whereNull('purgado_at')
            ->whereIn('estado', ['valido', 'rechazado'])
            ->where('updated_at', '<', $limite)
            ->whereHas('perfil', fn ($q) => $q->whereIn('estado_verificacion', [
                PerfilPrestador::ESTADO_VERIFICADO,
                PerfilPrestador::ESTADO_RECHAZADO,
            ]));

        if ($this->option('dry-run')) {
            $this->info("Se purgarían {$consulta->count()} documento(s).");

            return self::SUCCESS;
        }

        $total = 0;

        $consulta->chunkById(100, function ($documentos) use ($almacen, &$total) {
            foreach ($documentos as $doc) {
                $ruta = $doc->ruta_cifrada;

                DB::transaction(function () use ($doc) {
                    $doc->update(['ruta_cifrada' => '', 'purgado_at' => now()]);

                    Auditoria::registrar('documento.purgado', 'perfil_prestador', $doc->perfil_id, null, [
                        'documento_id' => $doc->id,
                        'tipo'         => $doc->tipo,
                    ]);
                });

                $almacen->borrar($almacen->discoPrivado(), $ruta !== '' ? $ruta : null);
                $total++;
            }
        });

        $this->info("Documentos purgados: {$total}.");

        return self::SUCCESS;
    }
}
