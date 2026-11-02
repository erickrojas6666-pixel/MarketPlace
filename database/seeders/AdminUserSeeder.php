<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Asegurar que el rol 'admin' exista
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // 2. Crear o recuperar el usuario administrador usando 'nombre'
        $admin = User::firstOrCreate(
            ['email' => 'admin@oficiosya.com'],
            [
                'nombre' => 'Administrador', // <-- Ajustado de 'name' a 'nombre'
                'password' => Hash::make('Admin123456!'),
                'email_verified_at' => now(),
            ]
        );

        // 3. Asignar el rol de admin
        $admin->assignRole($adminRole);

        $this->command->info('Usuario administrador creado correctamente: admin@oficiosya.com');
    }
}