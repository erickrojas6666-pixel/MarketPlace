<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /** Roles que usa la plataforma (el registro necesita "cliente" y "prestador"). */
    public const ROLES = [
        'cliente',
        'prestador',
        'verificador',
        'mediador',
        'finanzas',
        'superadmin',
        'admin',
        'moderador',
        'analista',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $nombre) {
            Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }
    }
}
