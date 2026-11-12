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
        $rolAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Contraseña inicial: cámbiala en cuanto entres (o define ADMIN_SEED_PASSWORD en .env).
        $admin = User::firstOrCreate(
            ['email' => 'admin@oficiosya.com'],
            [
                'nombre'   => 'Administrador',
                'password' => Hash::make(env('ADMIN_SEED_PASSWORD', 'Admin123456!')),
            ]
        );

        // email_verified_at no es asignable en masa (ver User::$fillable).
        if ($admin->email_verified_at === null) {
            $admin->forceFill(['email_verified_at' => now()])->save();
        }

        $admin->assignRole($rolAdmin);

        $this->command?->info('Administrador listo: admin@oficiosya.com');
    }
}
