<?php

namespace App\Support\Imagenes;

/** Resultado de re-codificar una imagen subida: bytes WebP listos para guardar. */
final class ImagenProcesada
{
    public function __construct(
        public readonly string $principal,
        public readonly string $miniatura,
        public readonly string $hash,
        public readonly int $ancho,
        public readonly int $alto,
    ) {}
}
