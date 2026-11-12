<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de roles y permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Crear Permisos Básicos
        Permission::firstOrCreate(['name' => 'publicar servicios']);
        Permission::firstOrCreate(['name' => 'contratar servicios']);
        Permission::firstOrCreate(['name' => 'gestionar plataforma']);

        // 2. Crear Roles y Asignar Permisos
        $roleCliente = Role::firstOrCreate(['name' => 'cliente']);
        $roleCliente->givePermissionTo('contratar servicios');

        $rolePrestador = Role::firstOrCreate(['name' => 'prestador']);
        $rolePrestador->givePermissionTo(['publicar servicios', 'contratar servicios']);

        // Roles faltantes de la imagen
        $roleVerificador = Role::firstOrCreate(['name' => 'verificador']);
        $roleMediador = Role::firstOrCreate(['name' => 'mediador']);
        $roleFinanzas = Role::firstOrCreate(['name' => 'finanzas']);
        
        $roleSuperadmin = Role::firstOrCreate(['name' => 'superadmin']);
        $roleSuperadmin->givePermissionTo(Permission::all());

        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->givePermissionTo(Permission::all());

        $roleModerador = Role::firstOrCreate(['name' => 'moderador']);
        $roleAnalista = Role::firstOrCreate(['name' => 'analista']);
    }
}