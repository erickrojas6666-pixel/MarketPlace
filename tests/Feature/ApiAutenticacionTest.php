<?php

namespace Tests\Feature;

use Tests\Concerns\PreparaBaseDeDatos;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

/**
 * Necesita la BD de pruebas (marketplace_test) con el esquema real.
 * Cada prueba se revierte sola (DatabaseTransactions).
 */
#[Group('db')]
class ApiAutenticacionTest extends TestCase
{
    use CreaUsuarios, PreparaBaseDeDatos;

    public function test_login_correcto_devuelve_un_token(): void
    {
        $user = $this->crearUsuario('cliente', [
            'email'    => 'cliente.api@example.com',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'cliente.api@example.com',
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'token', 'token_type', 'expires_at',
                'usuario' => ['id', 'nombre', 'email', 'roles'],
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('usuario.id', $user->id);
    }

    public function test_login_con_clave_incorrecta_devuelve_401(): void
    {
        $this->crearUsuario('cliente', ['email' => 'otro@example.com']);

        $this->postJson('/api/auth/login', [
            'email'    => 'otro@example.com',
            'password' => 'clave-equivocada',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales incorrectas.');
    }

    public function test_un_correo_inexistente_da_el_mismo_mensaje_que_una_clave_mala(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'no-existe@example.com',
            'password' => 'lo-que-sea',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales incorrectas.');
    }

    public function test_se_bloquea_el_login_tras_5_intentos_fallidos(): void
    {
        $this->crearUsuario('cliente', ['email' => 'bloqueo@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => 'bloqueo@example.com',
                'password' => 'mala',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', [
            'email'    => 'bloqueo@example.com',
            'password' => 'mala',
        ])->assertStatus(429);
    }

    public function test_me_devuelve_el_usuario_autenticado(): void
    {
        $user = $this->crearUsuario('prestador');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('usuario.email', $user->email)
            ->assertJsonPath('usuario.roles.0', 'prestador');
    }

    public function test_logout_responde_correctamente(): void
    {
        Sanctum::actingAs($this->crearUsuario('cliente'), ['*']);

        $this->postJson('/api/auth/logout')->assertOk();
    }
}
