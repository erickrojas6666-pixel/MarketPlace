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
            'Cerrajería'     => 'Apertura de puertas, cambio de chapas y duplicado de llaves.',
            'Electricidad'   => 'Instalaciones, reparaciones y mantenimiento eléctrico.',
            'Jardinería'     => 'Poda, mantenimiento y diseño de jardines.',
            'Pintura'        => 'Pintura de interiores y exteriores.',
            'Plomería y Gas' => 'Fugas, cañerías, boilers e instalaciones de gas.',
        ];

        foreach ($categorias as $nombre => $descripcion) {
            Categoria::firstOrCreate(
                ['slug' => Str::slug($nombre)],
                ['nombre' => $nombre, 'descripcion' => $descripcion]
            );
        }
    }
}
