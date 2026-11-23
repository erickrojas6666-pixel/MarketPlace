<?php

namespace Tests\Feature\Casos;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Transaccione;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaEscenarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * CN-SEG-01 · Acceso a recursos ajenos (REQ-03, REQ-04 y REQ-05). Seguridad negativa.
 * La API debe responder 404 (no 403) para no revelar que el recurso existe.
 */
#[Group('pdf'), Group('db'), Group('CN-SEG-01')]
class CnSeg01RecursosAjenosTest extends TestCase
{
    use CreaEscenarios, PreparaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurarMarketplace(['marketplace.escrow_habilitado' => true]);
    }

    public function test_un_tercero_recibe_404_identico_al_de_un_id_inexistente(): void
    {
        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);                                        // Paso 1
        $pagoId   = (int) $this->dato($this->pagarApi($e->cliente, $pedidoId)->assertCreated(), 'id');
        $otro     = $this->crearUsuario('cliente', ['email' => 'otro.cliente@example.com']); // Paso 2

        $casos = [
            'GET pedido'   => ['getJson',   "/api/pedidos/{$pedidoId}",        '/api/pedidos/999999',        []],
            'PATCH estado' => ['patchJson', "/api/pedidos/{$pedidoId}/estado", '/api/pedidos/999999/estado', ['estado' => 'cancelado']],
            'GET pago'     => ['getJson',   "/api/pagos/{$pagoId}",            '/api/pagos/999999',          []],
            'POST liberar' => ['postJson',  "/api/pagos/{$pagoId}/liberar",    '/api/pagos/999999/liberar',  []],
        ];

        foreach ($casos as $nombre => [$metodo, $ajena, $inexistente, $datos]) {
            // Pasos 3 y 4.
            $deAjeno     = $this->api($otro)->{$metodo}($ajena, $datos);
            $deInexiste  = $this->api($otro)->{$metodo}($inexistente, $datos);

            $deAjeno->assertNotFound();
            $deInexiste->assertNotFound();

            $this->assertSame(
                $deInexiste->getContent(),
                $deAjeno->getContent(),
                "{$nombre}: la respuesta de un recurso ajeno debe ser idéntica a la de uno inexistente."
            );

            // No se filtran detalles internos.
            foreach (['App\\Models', 'SQLSTATE', 'No query results', 'Illuminate', 'vendor'] as $pista) {
                $this->assertStringNotContainsString($pista, $deAjeno->getContent(), "{$nombre} revela «{$pista}».");
            }
        }

        // La BD no cambió.
        $this->assertSame('pagado', Pedido::findOrFail($pedidoId)->estado);
        $this->assertSame(1, Pago::where('pedido_id', $pedidoId)->count());
        $this->assertSame(1, Transaccione::where('pago_id', $pagoId)->count());
    }

    public function test_sin_token_todas_las_rutas_responden_401(): void
    {
        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);
        $pagoId   = (int) $this->dato($this->pagarApi($e->cliente, $pedidoId)->assertCreated(), 'id');

        // Paso 5.
        $this->sinSesion()->getJson("/api/pedidos/{$pedidoId}")->assertUnauthorized();
        $this->sinSesion()->patchJson("/api/pedidos/{$pedidoId}/estado", ['estado' => 'cancelado'])->assertUnauthorized();
        $this->sinSesion()->getJson("/api/pagos/{$pagoId}")->assertUnauthorized();
        $this->sinSesion()->postJson("/api/pagos/{$pagoId}/liberar")->assertUnauthorized();
        $this->sinSesion()->getJson('/api/pedidos')->assertUnauthorized();
        $this->sinSesion()->getJson('/api/pagos')->assertUnauthorized();
    }

    public function test_los_listados_no_incluyen_pedidos_ni_pagos_ajenos(): void
    {
        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);
        $this->pagarApi($e->cliente, $pedidoId)->assertCreated();
        $otro = $this->crearUsuario('cliente');

        $this->assertSame([], $this->api($otro)->getJson('/api/pedidos')->assertOk()->json('data'));
        $this->assertSame([], $this->api($otro)->getJson('/api/pagos')->assertOk()->json('data'));
    }

    public function test_las_dos_partes_si_ven_el_pedido_y_el_pago(): void
    {
        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);
        $pagoId   = (int) $this->dato($this->pagarApi($e->cliente, $pedidoId)->assertCreated(), 'id');

        $this->api($e->cliente)->getJson("/api/pedidos/{$pedidoId}")->assertOk();
        $this->api($e->prestador)->getJson("/api/pedidos/{$pedidoId}")->assertOk();
        $this->api($e->cliente)->getJson("/api/pagos/{$pagoId}")->assertOk();
        $this->api($e->prestador)->getJson("/api/pagos/{$pagoId}")->assertOk();
    }

    public function test_otro_prestador_tampoco_ve_ni_modifica_el_pedido(): void
    {
        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);
        $otro     = $this->crearUsuario('prestador');

        $this->api($otro)->getJson("/api/pedidos/{$pedidoId}")->assertNotFound();
        $this->cambiarEstadoApi($otro, $pedidoId, 'en_progreso')->assertNotFound();

        $this->assertSame('aceptado', Pedido::findOrFail($pedidoId)->estado);
    }

    public function test_el_id_de_la_ruta_no_numerico_da_404_sin_detalles(): void
    {
        $e = $this->escenario();

        foreach (['/api/pedidos/abc', '/api/pagos/abc', '/api/pedidos/1%20OR%201=1', '/api/pedidos/-1'] as $ruta) {
            $r = $this->api($e->cliente)->getJson($ruta);
            $r->assertNotFound();
            $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        }
    }
}
