<?php

namespace Tests\Feature;

use App\Models\Pedido;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaEscenarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * Sección 7 del documento: observaciones de la revisión del código.
 * Un fallo aquí NO es un error de la prueba: confirma la observación.
 */
#[Group('observacion')]
class ObservacionesRevisionTest extends TestCase
{
    use CreaEscenarios, PreparaBaseDeDatos;

    /** Observación 2: la ruta web responde 403 y la API 404 ante un pedido ajeno. */
    #[Group('db')]
    public function test_obs2_la_ruta_web_da_403_y_la_api_da_404_ante_un_pedido_ajeno(): void
    {
        $this->configurarMarketplace();

        $e      = $this->escenario();
        $pedido = $this->crearPedidoDirecto($e);
        $ajeno  = $this->crearUsuario('cliente');

        $this->actingAs($ajeno)->patch("/pedidos/{$pedido->id}/estado", ['estado' => 'cancelado'])->assertForbidden();
        $this->cambiarEstadoApi($ajeno, $pedido->id, 'cancelado')->assertNotFound();

        $this->assertSame('pendiente', Pedido::findOrFail($pedido->id)->estado);
    }

    /** Observación 3: .env.example no debe traer APP_DEBUG=true. */
    public function test_obs3_env_example_no_activa_el_modo_debug(): void
    {
        $ruta = base_path('.env.example');

        if (! is_file($ruta)) {
            $this->markTestSkipped('No existe .env.example.');
        }

        $this->assertDoesNotMatchRegularExpression(
            '/^\s*APP_DEBUG\s*=\s*true\s*$/mi',
            (string) file_get_contents($ruta),
            '.env.example trae APP_DEBUG=true: antes de liberar debe ser false.'
        );
    }

    /** Observación 4: el escrow viene apagado por defecto. */
    public function test_obs4_el_escrow_esta_desactivado_por_defecto(): void
    {
        $fuente = (string) file_get_contents(config_path('marketplace.php'));

        $this->assertMatchesRegularExpression(
            "/'escrow_habilitado'\s*=>\s*\(bool\)\s*env\('MARKETPLACE_ESCROW',\s*false\)/",
            $fuente,
            'El valor por defecto de MARKETPLACE_ESCROW cambió: actualiza los casos de pago.'
        );
    }

    /** Observación 4 (efecto): con el escrow apagado, pagar NO cambia el pedido a pagado. */
    #[Group('db')]
    public function test_obs4_con_escrow_apagado_el_pago_no_cambia_el_pedido(): void
    {
        $this->configurarMarketplace(['marketplace.escrow_habilitado' => false]);

        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);

        $this->pagarApi($e->cliente, $pedidoId)->assertCreated();

        $this->assertSame('aceptado', Pedido::findOrFail($pedidoId)->estado);
    }

    /** Observación 5: en modo Mercado Pago el webhook no responde 200 si no está configurado. */
    public function test_obs5_el_webhook_de_mercado_pago_no_acepta_nada_sin_configurar(): void
    {
        $this->configurarMarketplace(['marketplace.pasarela' => 'simulada']);

        $this->postJson('/api/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '123']])
            ->assertStatus(503);
    }
}
