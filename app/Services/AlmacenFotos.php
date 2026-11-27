<?php

namespace App\Services;

use App\Support\Imagenes\ProcesadorImagen;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Único punto por el que entran fotos al sistema.
 *
 * Dos discos (config/marketplace.php → fotos):
 *  - público: foto de perfil y portafolio (URL pública; en producción, bucket público de R2).
 *  - privado: evidencia de pedidos (solo URL temporal firmada; bucket privado de R2).
 *
 * Nombres aleatorios (ULID): nada del nombre original del archivo llega al almacenamiento.
 */
class AlmacenFotos
{
    public function __construct(private readonly ProcesadorImagen $procesador) {}

    public function discoPublico(): string
    {
        return (string) config('marketplace.fotos.disco_publico');
    }

    public function discoPrivado(): string
    {
        return (string) config('marketplace.fotos.disco_privado');
    }

    /** Convención: la miniatura vive junto a la imagen principal con sufijo _m. */
    public static function miniaturaDe(string $ruta): string
    {
        return preg_replace('/\.webp$/', '_m.webp', $ruta) ?? $ruta;
    }

    /**
     * Procesa y guarda la imagen y su miniatura.
     *
     * @return array{ruta: string, hash_sha256: string}
     *
     * @throws ValidationException con el error en el campo "foto"
     */
    public function guardar(UploadedFile $archivo, string $disco, string $carpeta): array
    {
        try {
            $imagen = $this->procesador->procesar(
                $archivo,
                (int) config('marketplace.fotos.lado_max'),
                (int) config('marketplace.fotos.lado_miniatura'),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['foto' => $e->getMessage()]);
        }

        $ruta      = trim($carpeta, '/').'/'.Str::lower((string) Str::ulid()).'.webp';
        $miniatura = self::miniaturaDe($ruta);
        $fs        = Storage::disk($disco);

        try {
            $fs->put($ruta, $imagen->principal);
            $fs->put($miniatura, $imagen->miniatura);
        } catch (Throwable $e) {
            report($e);
            $this->borrar($disco, $ruta);

            throw ValidationException::withMessages([
                'foto' => 'No pudimos guardar la foto. Intenta de nuevo en un momento.',
            ]);
        }

        return ['ruta' => $ruta, 'hash_sha256' => $imagen->hash];
    }

    /**
     * Documentos de identidad: se re-codifican (sin EXIF/GPS) y se guardan CIFRADOS con la
     * clave de la aplicación (AES-256 vía Crypt). Aunque alguien lea el bucket, solo ve bytes
     * ilegibles. No hay miniatura ni URL: solo se leen con leerCifrado() tras autorizar.
     *
     * @return array{ruta: string, hash_sha256: string}
     *
     * @throws ValidationException con el error en el campo "foto"
     */
    public function guardarCifrado(UploadedFile $archivo, string $disco, string $carpeta, int $ladoMax): array
    {
        try {
            $imagen = $this->procesador->procesar($archivo, $ladoMax, 64);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['foto' => $e->getMessage()]);
        }

        $ruta = trim($carpeta, '/').'/'.Str::lower((string) Str::ulid()).'.enc';

        try {
            Storage::disk($disco)->put($ruta, Crypt::encryptString($imagen->principal));
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'foto' => 'No pudimos guardar el documento. Intenta de nuevo en un momento.',
            ]);
        }

        return ['ruta' => $ruta, 'hash_sha256' => $imagen->hash];
    }

    /**
     * Devuelve los bytes (WebP) de un documento cifrado y verifica su integridad.
     * Lanza RuntimeException si falta el archivo, no se puede descifrar o el hash no coincide.
     */
    public function leerCifrado(string $disco, string $ruta, string $hashEsperado): string
    {
        $cifrado = Storage::disk($disco)->get($ruta);

        if (! is_string($cifrado) || $cifrado === '') {
            throw new \RuntimeException('El documento no existe en el almacenamiento.');
        }

        try {
            $bytes = Crypt::decryptString($cifrado);
        } catch (DecryptException) {
            throw new \RuntimeException('No se pudo descifrar el documento.');
        }

        if (! hash_equals($hashEsperado, hash('sha256', $bytes))) {
            throw new \RuntimeException('El documento no coincide con su huella de integridad.');
        }

        return $bytes;
    }

    /** Borra la imagen y su miniatura. Un fallo del almacenamiento se reporta pero no rompe la petición. */
    public function borrar(string $disco, string|array|null $rutas): void
    {
        $rutas = array_filter((array) $rutas);

        $todas = [];
        foreach ($rutas as $ruta) {
            $todas[] = $ruta;
            $todas[] = self::miniaturaDe($ruta);
        }

        if ($todas === []) {
            return;
        }

        try {
            Storage::disk($disco)->delete(array_values(array_unique($todas)));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function urlPublica(?string $ruta): ?string
    {
        return $ruta ? Storage::disk($this->discoPublico())->url($ruta) : null;
    }

    /** URL firmada de vida corta para el disco privado. */
    public function urlTemporal(string $ruta): string
    {
        return Storage::disk($this->discoPrivado())->temporaryUrl(
            $ruta,
            now()->addMinutes((int) config('marketplace.fotos.minutos_url_privada')),
        );
    }
}
