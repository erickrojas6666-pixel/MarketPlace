<?php

namespace Tests\Feature\Casos;

use App\Models\Pedido;
use App\Models\Transaccione;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaEscenarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * CP-INT-02 · Liberar el pago con comisión y rechazar la doble liberación (REQ-05).
 * Configuración del caso: pago de 500.00, comisión 12 %, escrow activo.
 */
#[Group('pdf'), Group('db'), Group('CP-INT-02')]
class CpInt02LiberarPagoTest extends TestCase
{
    use CreaEscenarios, PreparaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurarMarketplace(['marketplace.escrow_habilitado' => true]);
    }

    public function test_libera_440_al_prestador_y_60_de_comision_y_rechaza_la_doble_liberacion(): void
    {
        $e = $this->escenario(500.00);

        // Pasos previos y 1-2: pagado -> en_progreso -> completado -> confirmado.
        [$pedidoId, $pagoId] = $this->pedidoConfirmadoConPago($e);

        // Paso 3: el cliente libera.
        $liberado = $this->liberarApi($e->cliente, $pagoId)->assertOk();
        $this->assertSame('liberado', $this->dato($liberado, 'estado'));

        // Paso 4: movimientos y pedido.
        $liberacion = Transaccione::where('pago_id', $pagoId)->where('tipo', 'liberacion_prestador')->get();
        $comision   = Transaccione::where('pago_id', $pagoId)->where('tipo', 'comision_plataforma')->get();

        $this->assertCount(1, $liberacion);
        $this->assertCount(1, $comision);
        $this->assertEqualsWithDelta(440.00, (float) $liberacion[0]->monto, 0.001);
        $this->assertEqualsWithDelta(60.00, (float) $comision[0]->monto, 0.001);
        $this->assertEqualsWithDelta(500.00, (float) $liberacion[0]->monto + (float) $comision[0]->monto, 0.001);
        $this->assertSame('confirmado', Pedido::findOrFail($pedidoId)->estado);
        $this->assertSame(3, Transaccione::where('pago_id', $pagoId)->count());

        // Paso 5: repetir -> 422 y sin movimientos nuevos.
        $this->liberarApi($e->cliente, $pagoId)
            ->assertStatus(422)
            ->assertJsonValidationErrors('pago_id');

        $this->assertSame(3, Transaccione::where('pago_id', $pagoId)->count());
    }

    public static function comisiones(): array
    {
        //                          porcentaje, precio, neto, comisión
        return [
            '12 % de 500'                 => [12.0, 500.00, 440.00, 60.00],
            '2.5 % de 1000'               => [2.5, 1000.00, 975.00, 25.00],
            '0 %: no hay comisión'        => [0.0, 500.00, 500.00, 0.00],
            '100 %: todo es comisión'     => [100.0, 500.00, 0.00, 500.00],
            'mayor a 100 se limita a 100' => [150.0, 500.00, 0.00, 500.00],
            'negativa se limita a 0'      => [-5.0, 500.00, 500.00, 0.00],
            'redondeo a centavos'         => [12.0, 99.99, 87.99, 12.00],
        ];
    }

    #[DataProvider('comisiones')]
    public function test_la_comision_se_calcula_y_nunca_se_pierde_un_centavo(float $pct, float $precio, float $neto, float $comision): void
    {
        $this->configurarMarketplace([
            'marketplace.escrow_habilitado'   => true,
            'marketplace.comision_porcentaje' => $pct,
        ]);

        $e = $this->escenario($precio);
        [, $pagoId] = $this->pedidoConfirmadoConPago($e);

        $this->liberarApi($e->cliente, $pagoId)->assertOk();

        $liberacion = (float) Transaccione::where('pago_id', $pagoId)->where('tipo', 'liberacion_prestador')->value('monto');
        $filasComision = Transaccione::where('pago_id', $pagoId)->where('tipo', 'comision_plataforma')->get();

        $this->assertEqualsWithDelta($neto, $liberacion, 0.001, 'Monto neto para el prestador.');
        $this->assertCount($comision > 0 ? 1 : 0, $filasComision, 'Solo hay fila de comisión si es mayor a cero.');

        if ($comision > 0) {
            $this->assertEqualsWithDelta($comision, (float) $filasComision[0]->monto, 0.001);
        }

        $this->assertEqualsWithDelta($precio, $liberacion + (float) $filasComision->sum('monto'), 0.001, 'Neto + comisión = monto cobrado.');
    }

    public function test_no_se_libera_si_el_trabajo_no_esta_confirmado(): void
    {
        $e        = $this->escenario(500.00);
        $pedidoId = $this->pedidoAceptadoPorApi($e);
        $pagoId   = (int) $this->dato($this->pagarApi($e->cliente, $pedidoId)->assertCreated(), 'id');

        // pagado -> en_progreso -> completado (sin confirmar)
        $this->liberarApi($e->cliente, $pagoId)->assertStatus(422);

        $this->cambiarEstadoApi($e->prestador, $pedidoId, 'en_progreso')->assertOk();
        $this->cambiarEstadoApi($e->prestador, $pedidoId, 'completado')->assertOk();
        $this->liberarApi($e->cliente, $pagoId)->assertStatus(422);

        $this->assertSame(0, Transaccione::where('pago_id', $pagoId)->where('tipo', 'liberacion_prestador')->count());
    }

    public function test_el_prestador_no_puede_liberarse_el_pago_a_si_mismo(): void
    {
        $e = $this->escenario(500.00);
        [, $pagoId] = $this->pedidoConfirmadoConPago($e);

        $this->liberarApi($e->prestador, $pagoId)->assertNotFound();

        $this->assertSame(0, Transaccione::where('pago_id', $pagoId)->where('tipo', 'liberacion_prestador')->count());
    }

    public function test_un_tercero_no_puede_liberar_pero_un_administrador_si(): void
    {
        $e = $this->escenario(500.00);
        [, $pagoId] = $this->pedidoConfirmadoConPago($e);

        $this->liberarApi($this->crearUsuario('cliente'), $pagoId)->assertNotFound();
        $this->liberarApi($this->crearUsuario('admin'), $pagoId)->assertOk();

        $this->assertSame(1, Transaccione::where('pago_id', $pagoId)->where('tipo', 'liberacion_prestador')->count());
    }

    public function test_liberar_sin_token_o_un_pago_inexistente_falla(): void
    {
        $e = $this->escenario();

        $this->sinSesion()->postJson('/api/pagos/1/liberar')->assertUnauthorized();
        $this->liberarApi($e->cliente, 999999)->assertNotFound();
    }
}
