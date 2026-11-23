<?php

namespace Tests\Feature\Casos;

use App\Models\Pago;
use App\Models\Transaccione;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaEscenarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * CP-INT-01 · Pago retenido con idempotencia (REQ-05). Integración, prioridad Alta.
 * Configuración del caso: MARKETPLACE_ESCROW=true, pasarela simulada, comisión 12 %.
 */
#[Group('pdf'), Group('db'), Group('CP-INT-01')]
class CpInt01PagoRetenidoTest extends TestCase
{
    use CreaEscenarios, PreparaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurarMarketplace(['marketplace.escrow_habilitado' => true]);
    }

    public function test_el_pago_queda_retenido_y_repetirlo_con_la_misma_clave_no_duplica(): void
    {
        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);   // Paso 1
        $uuid     = (string) Str::uuid();              // 36 caracteres

        // Paso 2: primer pago.
        $primero = $this->pagarApi($e->cliente, $pedidoId, $uuid)->assertCreated();

        $this->assertSame('retenido', $this->dato($primero, 'estado'));
        $this->assertSame('simulado', $this->dato($primero, 'metodo'));
        $this->assertEquals(500.0, $this->dato($primero, 'monto'));

        $movimientos = $this->dato($primero, 'movimientos');
        $this->assertCount(1, $movimientos);
        $this->assertSame('cobro_cliente', $movimientos[0]['tipo']);
        $this->assertEquals(500.0, $movimientos[0]['monto']);

        // Paso 3: misma petición, mismo UUID -> 200 con el mismo pago.
        $segundo = $this->pagarApi($e->cliente, $pedidoId, $uuid)->assertOk();
        $this->assertSame($this->dato($primero, 'id'), $this->dato($segundo, 'id'));

        // Paso 4: el pedido pasó a "pagado".
        $pedido = $this->api($e->cliente)->getJson("/api/pedidos/{$pedidoId}")->assertOk();
        $this->assertSame('pagado', $this->dato($pedido, 'estado'));

        // Paso 5: 1 solo pago y 1 solo cobro_cliente.
        $this->assertSame(1, Pago::where('pedido_id', $pedidoId)->count());

        $pago = Pago::where('pedido_id', $pedidoId)->firstOrFail();
        $this->assertSame(1, Transaccione::where('pago_id', $pago->id)->where('tipo', 'cobro_cliente')->count());
        $this->assertSame(1, Transaccione::where('pago_id', $pago->id)->count());
    }

    public function test_pagar_dos_veces_sin_clave_no_duplica_el_cobro(): void
    {
        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);

        $this->pagarApi($e->cliente, $pedidoId)->assertCreated();
        $this->pagarApi($e->cliente, $pedidoId)->assertStatus(422);

        $this->assertSame(1, Pago::where('pedido_id', $pedidoId)->count());
    }

    public function test_un_pedido_sin_aceptar_no_se_puede_pagar(): void
    {
        $e  = $this->escenario(500.00);
        $id = $this->crearPedidoDirecto($e)->id;   // pendiente

        $this->pagarApi($e->cliente, $id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('pedido_id');

        $this->assertSame(0, Pago::count());
    }

    public function test_un_servicio_sin_precio_no_se_puede_pagar(): void
    {
        $e  = $this->escenario(null);
        $id = $this->pedidoAceptadoPorApi($e);

        $this->pagarApi($e->cliente, $id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('pedido_id');

        $this->assertSame(0, Pago::count());
    }

    public function test_solo_el_cliente_del_pedido_puede_pagarlo(): void
    {
        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);
        $tercero  = $this->crearUsuario('cliente');

        $this->pagarApi($tercero, $pedidoId)->assertNotFound();
        $this->pagarApi($e->prestador, $pedidoId)->assertNotFound();

        $this->assertSame(0, Pago::count());
    }

    public function test_sin_token_no_se_puede_pagar(): void
    {
        $e  = $this->escenario(500.00);
        $id = $this->pedidoAceptadoPorApi($e);

        $this->sinSesion()->postJson('/api/pagos', ['pedido_id' => $id])->assertUnauthorized();
    }

    public function test_el_pedido_debe_existir_y_ser_numerico(): void
    {
        $e = $this->escenario();

        $this->api($e->cliente)->postJson('/api/pagos', ['pedido_id' => 999999])
            ->assertStatus(422)->assertJsonValidationErrors('pedido_id');

        $this->api($e->cliente)->postJson('/api/pagos', ['pedido_id' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors('pedido_id');

        $this->api($e->cliente)->postJson('/api/pagos', [])
            ->assertStatus(422)->assertJsonValidationErrors('pedido_id');
    }
}
