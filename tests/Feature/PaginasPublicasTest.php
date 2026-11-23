<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pruebas de humo SIN base de datos: páginas públicas y rutas protegidas.
 */
class PaginasPublicasTest extends TestCase
{
    public function test_la_portada_carga(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_las_pantallas_de_login_y_registro_cargan(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_el_healthcheck_responde(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_un_invitado_es_enviado_al_login_desde_el_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_un_invitado_no_entra_al_panel_de_administracion(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_un_invitado_no_puede_crear_pedidos(): void
    {
        $this->post('/pedidos', [])->assertRedirect(route('login'));
    }

    public function test_la_api_exige_token_en_rutas_privadas(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/pedidos')->assertUnauthorized();
        $this->getJson('/api/pagos')->assertUnauthorized();
    }

    public function test_el_login_de_la_api_valida_los_campos(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
