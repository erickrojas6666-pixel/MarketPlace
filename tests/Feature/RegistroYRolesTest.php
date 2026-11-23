<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\PreparaBaseDeDatos;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

#[Group('db')]
class RegistroYRolesTest extends TestCase
{
    use CreaUsuarios, PreparaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cliente', 'prestador', 'admin'] as $rol) {
            Role::findOrCreate($rol, 'web');
        }
    }

    private function datosRegistro(array $cambios = []): array
    {
        return array_merge([
            'nombre'                => 'Ana Prueba',
            'email'                 => 'ana.prueba@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tipo_usuario'          => 'cliente',
        ], $cambios);
    }

    public function test_un_cliente_se_registra_e_inicia_sesion(): void
    {
        $this->post('/register', $this->datosRegistro())
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ana.prueba@example.com']);
        $this->assertTrue(User::where('email', 'ana.prueba@example.com')->first()->hasRole('cliente'));
    }

    public function test_un_prestador_queda_con_perfil_pendiente_de_verificacion(): void
    {
        $this->post('/register', $this->datosRegistro([
            'email'        => 'pepe.prestador@example.com',
            'tipo_usuario' => 'prestador',
        ]));

        $user = User::where('email', 'pepe.prestador@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('prestador'));
        $this->assertDatabaseHas('perfiles_prestador', [
            'user_id'             => $user->id,
            'estado_verificacion' => 'pendiente',
        ]);
    }

    public function test_no_se_puede_registrar_como_admin(): void
    {
        $this->post('/register', $this->datosRegistro(['tipo_usuario' => 'admin']))
            ->assertSessionHasErrors('tipo_usuario');

        $this->assertDatabaseMissing('users', ['email' => 'ana.prueba@example.com']);
    }

    public function test_no_acepta_un_correo_repetido(): void
    {
        $this->crearUsuario('cliente', ['email' => 'repetido@example.com']);

        $this->post('/register', $this->datosRegistro(['email' => 'repetido@example.com']))
            ->assertSessionHasErrors('email');
    }

    public function test_exige_confirmar_la_contrasena(): void
    {
        $this->post('/register', $this->datosRegistro(['password_confirmation' => 'otra-distinta']))
            ->assertSessionHasErrors('password');
    }

    public function test_un_cliente_no_entra_al_panel_de_administracion(): void
    {
        $this->actingAs($this->crearUsuario('cliente'))
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_un_cliente_no_puede_publicar_servicios(): void
    {
        $this->actingAs($this->crearUsuario('cliente'))
            ->post('/servicios', [])
            ->assertForbidden();
    }

    public function test_el_login_web_funciona_con_credenciales_validas(): void
    {
        $user = $this->crearUsuario('cliente', [
            'email'    => 'login.web@example.com',
            'password' => 'Password123!',
        ]);

        $this->post('/login', [
            'email'    => 'login.web@example.com',
            'password' => 'Password123!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }
}
