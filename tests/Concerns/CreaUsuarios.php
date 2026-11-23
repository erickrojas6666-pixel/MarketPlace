<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait CreaUsuarios
{
    /**
     * Crea un usuario real con el rol indicado (cliente, prestador, admin).
     * No usa UserFactory porque esa fábrica escribe "name" y tu tabla usa "nombre".
     */
    protected function crearUsuario(string $rol = 'cliente', array $extra = []): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate($rol, 'web');

        $user = User::create([
            'nombre'   => $extra['nombre'] ?? 'Usuario Prueba',
            'email'    => $extra['email'] ?? 'prueba+'.uniqid().'@example.com',
            'password' => Hash::make($extra['password'] ?? 'Password123!'),
        ]);

        // email_verified_at no es "fillable" a propósito (ver User.php).
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($rol);

        return $user;
    }
}
