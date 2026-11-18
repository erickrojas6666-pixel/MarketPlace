<?php

namespace App\Support\Imagenes;

use GdImage;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/**
 * Re-codifica toda imagen subida (docs de seguridad, riesgo 5 y sección 8.2):
 *
 *  - Valida el tipo REAL por contenido (no por extensión ni por el MIME que declara el cliente).
 *  - Limita el número de píxeles (evita "bombas de descompresión").
 *  - Aplica la orientación EXIF y vuelve a codificar a WebP: se pierden EXIF, GPS
 *    y cualquier carga útil escondida en la imagen original.
 *  - Genera la versión principal y la miniatura, y el hash sha256 de la principal.
 *
 * Lanza InvalidArgumentException con mensajes pensados para mostrarse al usuario.
 */
final class ProcesadorImagen
{
    private const TIPOS_PERMITIDOS = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** 25 megapíxeles: sobra para una foto de celular y acota la memoria de GD. */
    private const MAX_PIXELES = 25_000_000;

    private const CALIDAD_WEBP = 82;

    public function procesar(UploadedFile $archivo, int $ladoMax = 1600, int $ladoMiniatura = 400): ImagenProcesada
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('La extensión GD con soporte WebP no está disponible en el servidor.');
        }

        $ruta  = $archivo->getRealPath();
        $bytes = $ruta ? @file_get_contents($ruta) : false;

        if ($bytes === false || $bytes === '') {
            throw new InvalidArgumentException('No pudimos leer la imagen. Intenta con otra.');
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || ! in_array($info[2], self::TIPOS_PERMITIDOS, true)) {
            throw new InvalidArgumentException('Formato no permitido. Usa una imagen JPG, PNG o WebP.');
        }

        [$ancho, $alto] = $info;

        if ($ancho < 1 || $alto < 1 || ($ancho * $alto) > self::MAX_PIXELES) {
            throw new InvalidArgumentException('La imagen es demasiado grande. Reduce su resolución e inténtalo de nuevo.');
        }

        $origen = @imagecreatefromstring($bytes);

        if (! $origen instanceof GdImage) {
            throw new InvalidArgumentException('La imagen está dañada o no se pudo procesar.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $origen = $this->orientar($origen, $this->orientacionExif($ruta));
        }

        $principal = $this->escalar($origen, $ladoMax);
        $miniatura = $this->escalar($origen, $ladoMiniatura);

        $datosPrincipal = $this->codificar($principal);

        return new ImagenProcesada(
            principal: $datosPrincipal,
            miniatura: $this->codificar($miniatura),
            hash: hash('sha256', $datosPrincipal),
            ancho: imagesx($principal),
            alto: imagesy($principal),
        );
    }

    /** Orientación EXIF (1 = normal). Sin la extensión exif, se asume normal. */
    private function orientacionExif(string $ruta): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        try {
            $exif = @exif_read_data($ruta);
        } catch (\Throwable) {
            return 1;
        }

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    private function orientar(GdImage $imagen, int $orientacion): GdImage
    {
        $girada = match ($orientacion) {
            3       => imagerotate($imagen, 180, 0),
            6       => imagerotate($imagen, -90, 0),
            8       => imagerotate($imagen, 90, 0),
            default => $imagen,
        };

        return $girada instanceof GdImage ? $girada : $imagen;
    }

    /** Reduce manteniendo proporción (nunca agranda). Fondo blanco para PNG con transparencia. */
    private function escalar(GdImage $origen, int $ladoMax): GdImage
    {
        $ancho = imagesx($origen);
        $alto  = imagesy($origen);
        $factor = min(1, $ladoMax / max($ancho, $alto));

        $nuevoAncho = max(1, (int) round($ancho * $factor));
        $nuevoAlto  = max(1, (int) round($alto * $factor));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        if (! $destino instanceof GdImage) {
            throw new RuntimeException('No se pudo reservar memoria para la imagen.');
        }

        imagefill($destino, 0, 0, (int) imagecolorallocate($destino, 255, 255, 255));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $destino;
    }

    private function codificar(GdImage $imagen): string
    {
        ob_start();
        $ok = imagewebp($imagen, null, self::CALIDAD_WEBP);
        $datos = (string) ob_get_clean();

        if (! $ok || $datos === '') {
            throw new RuntimeException('No se pudo codificar la imagen a WebP.');
        }

        return $datos;
    }
}
