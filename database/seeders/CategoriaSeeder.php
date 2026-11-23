<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            'Plomería y Gas' => 'Instalación y reparación de cañerías, grifos y artefactos de gas.',
            'Electricidad'   => 'Reparaciones eléctricas, tableros y cortocircuitos.',
            'Cerrajería'     => 'Apertura de puertas, cambio de combinaciones y llaves.',
            'Pintura'        => 'Pintura de interiores, exteriores y superficies de madera/metal.',
            'Jardinería'     => 'Servicio de poda, siembra y limpieza de jardines.',
        ];

        foreach ($categorias as $nombre => $descripcion) {
            // El slug va explícito: DatabaseSeeder usa WithoutModelEvents y el
            // evento creating() de Categoria no se dispara aquí.
            Categoria::firstOrCreate(
                ['slug' => Str::slug($nombre)],
                ['nombre' => $nombre, 'descripcion' => $descripcion]
            );
        }
    }
}
