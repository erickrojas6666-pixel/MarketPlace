<?php

namespace Tests\Unit;

use App\Models\Pedido;
use App\Support\Pedidos\TransicionesPedido as T;
use Tests\TestCase;

/**
 * Lógica pura de la máquina de estados: NO necesita base de datos.
 */
class TransicionesPedidoTest extends TestCase
{
    private function config(bool $cotiz = false, bool $escrow = false, bool $disputas = false): void
    {
        config([
            'marketplace.cotizaciones_habilitadas' => $cotiz,
            'marketplace.escrow_habilitado'        => $escrow,
            'marketplace.disputas_habilitadas'     => $disputas,
        ]);
    }

    public function test_el_cliente_puede_cancelar_un_pedido_pendiente(): void
    {
        $this->config();
        $this->assertTrue(T::permite(Pedido::PENDIENTE, Pedido::CANCELADO, [T::CLIENTE]));
    }

    public function test_el_prestador_no_puede_cancelar_un_pedido_pendiente(): void
    {
        $this->config();
        $this->assertFalse(T::permite(Pedido::PENDIENTE, Pedido::CANCELADO, [T::PRESTADOR]));
    }

    public function test_sin_cotizaciones_el_prestador_acepta_directamente(): void
    {
        $this->config(cotiz: false);

        $this->assertTrue(T::permite(Pedido::PENDIENTE, Pedido::ACEPTADO, [T::PRESTADOR]));
        $this->assertFalse(T::permite(Pedido::PENDIENTE, Pedido::COTIZADO, [T::PRESTADOR]));
    }

    public function test_con_cotizaciones_el_prestador_cotiza_en_vez_de_aceptar(): void
    {
        $this->config(cotiz: true);

        $this->assertTrue(T::permite(Pedido::PENDIENTE, Pedido::COTIZADO, [T::PRESTADOR]));
        $this->assertFalse(T::permite(Pedido::PENDIENTE, Pedido::ACEPTADO, [T::PRESTADOR]));
    }

    public function test_sin_escrow_el_prestador_pasa_de_aceptado_a_en_progreso(): void
    {
        $this->config(escrow: false);

        $this->assertTrue(T::permite(Pedido::ACEPTADO, Pedido::EN_PROGRESO, [T::PRESTADOR]));
        $this->assertFalse(T::permite(Pedido::ACEPTADO, Pedido::PAGADO, [T::SISTEMA]));
    }

    public function test_con_escrow_solo_el_sistema_marca_el_pedido_como_pagado(): void
    {
        $this->config(escrow: true);

        $this->assertTrue(T::permite(Pedido::ACEPTADO, Pedido::PAGADO, [T::SISTEMA]));
        $this->assertFalse(T::permite(Pedido::ACEPTADO, Pedido::PAGADO, [T::CLIENTE]));
        $this->assertFalse(T::permite(Pedido::ACEPTADO, Pedido::EN_PROGRESO, [T::PRESTADOR]));
    }

    public function test_cliente_y_sistema_pueden_confirmar_pero_el_prestador_no(): void
    {
        $this->config();

        $this->assertTrue(T::permite(Pedido::COMPLETADO, Pedido::CONFIRMADO, [T::CLIENTE]));
        $this->assertTrue(T::permite(Pedido::COMPLETADO, Pedido::CONFIRMADO, [T::SISTEMA]));
        $this->assertFalse(T::permite(Pedido::COMPLETADO, Pedido::CONFIRMADO, [T::PRESTADOR]));
    }

    public function test_la_disputa_solo_existe_si_el_modulo_esta_habilitado(): void
    {
        $this->config(disputas: false);
        $this->assertFalse(T::permite(Pedido::COMPLETADO, Pedido::EN_DISPUTA, [T::CLIENTE]));

        $this->config(disputas: true);
        $this->assertTrue(T::permite(Pedido::COMPLETADO, Pedido::EN_DISPUTA, [T::CLIENTE]));
    }

    public function test_no_se_puede_saltar_estados(): void
    {
        $this->config();

        $todos = [T::CLIENTE, T::PRESTADOR, T::MEDIADOR, T::SISTEMA];
        $this->assertFalse(T::permite(Pedido::PENDIENTE, Pedido::COMPLETADO, $todos));
        $this->assertFalse(T::permite(Pedido::PENDIENTE, Pedido::CONFIRMADO, $todos));
    }

    public function test_los_estados_finales_no_tienen_salida(): void
    {
        $this->config(cotiz: true, escrow: true, disputas: true);

        $todos = [T::CLIENTE, T::PRESTADOR, T::MEDIADOR, T::SISTEMA];

        foreach ([Pedido::CANCELADO, Pedido::CONFIRMADO, Pedido::RECHAZADO] as $final) {
            $this->assertSame([], T::permitidas($final, $todos), "El estado '{$final}' no debería tener salidas.");
        }
    }

    public function test_permitidas_devuelve_los_destinos_de_un_actor(): void
    {
        $this->config();

        $this->assertEqualsCanonicalizing(
            [Pedido::RECHAZADO, Pedido::ACEPTADO],
            T::permitidas(Pedido::PENDIENTE, [T::PRESTADOR])
        );
    }
}
