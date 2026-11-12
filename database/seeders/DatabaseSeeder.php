<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Imprescindibles (también en producción). El orden importa.
        $this->call([
            RoleSeeder::class,
            CategoriaSeeder::class,
            AdminUserSeeder::class,
        ]);

        // Datos de ejemplo solo fuera de producción.
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
