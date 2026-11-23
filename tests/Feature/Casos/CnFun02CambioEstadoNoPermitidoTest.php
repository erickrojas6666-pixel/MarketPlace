<?php

namespace Tests\Feature\Casos;

use App\Models\Pedido;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaEscenarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * CN-FUN-02 · Cambio de estado no permitido (REQ-04). Funcional negativo, prioridad Alta.
 */
#[Group('pdf'), Group('db'), Group('CN-FUN-02')]
class CnFun02CambioEstadoNoPermitidoTest extends TestCase
{
    use CreaEscenarios, PreparaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurarMarketplace();
    }

    public function test_un_pedido_pendiente_no_pasa_a_confirmado_y_no_se_notifica(): void
    {
        $e  = $this->escenario();
        $id = (int) $this->dato($this->solicitarPedidoApi($e)->assertCreated(), 'id');

        $notifPrestador = $e->prestador->notifications()->count();
        $notifCliente   = $e->cliente->notifications()->count();

        // Paso 2: PATCH con {"estado": "confirmado"} y el token del cliente.
        $this->cambiarEstadoApi($e->cliente, $id, 'confirmado')
            ->assertStatus(422)
            ->assertJsonPath('message', "No puedes cambiar un pedido 'pendiente' a 'confirmado'.");

        // Paso 3: GET muestra que sigue pendiente.
        $consulta = $this->api($e->cliente)->getJson("/api/pedidos/{$id}")->assertOk();
        $this->assertSame('pendiente', $this->dato($consulta, 'estado'));

        // Paso 4: BD sin cambios y sin notificaciones nuevas.
        $this->assertSame('pendiente', Pedido::findOrFail($id)->estado);
        $this->assertSame($notifPrestador, $e->prestador->notifications()->count());
        $this->assertSame($notifCliente, $e->cliente->notifications()->count());
    }

    public static function destinosProhibidosDesdePendiente(): array
    {
        return [
            'en_progreso' => ['en_progreso'],
            'completado'  => ['completado'],
            'confirmado'  => ['confirmado'],
            'pagado'      => ['pagado'],
            'en_disputa'  => ['en_disputa'],
            'aceptado'    => ['aceptado'],   // solo el prestador acepta
            'cotizado'    => ['cotizado'],   // módulo de cotizaciones apagado
            'rechazado'   => ['rechazado'],  // solo el prestador rechaza
        ];
    }

    #[DataProvider('destinosProhibidosDesdePendiente')]
    public function test_el_cliente_no_puede_saltar_desde_pendiente(string $destino): void
    {
        $e  = $this->escenario();
        $id = (int) $this->dato($this->solicitarPedidoApi($e)->assertCreated(), 'id');

        $this->cambiarEstadoApi($e->cliente, $id, $destino)->assertStatus(422);

        $this->assertSame('pendiente', Pedido::findOrFail($id)->estado);
    }

    public static function estadosInvalidos(): array
    {
        return [
            'inventado'          => ['inventado'],
            'mayusculas'         => ['PENDIENTE'],
            'vacio'              => [''],
            'con codigo sql'     => ["cancelado'; DROP TABLE pedidos;--"],
        ];
    }

    #[DataProvider('estadosInvalidos')]
    public function test_un_estado_fuera_del_catalogo_se_rechaza_con_422(string $estado): void
    {
        $e  = $this->escenario();
        $id = $this->crearPedidoDirecto($e)->id;

        $this->cambiarEstadoApi($e->cliente, $id, $estado)
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');

        $this->assertSame('pendiente', Pedido::findOrFail($id)->estado);
    }

    public function test_un_pedido_cancelado_no_se_puede_reabrir(): void
    {
        $e  = $this->escenario();
        $id = $this->crearPedidoDirecto($e)->id;

        $this->cambiarEstadoApi($e->cliente, $id, 'cancelado')->assertOk();

        foreach (['pendiente', 'aceptado', 'en_progreso', 'confirmado'] as $destino) {
            $this->cambiarEstadoApi($e->cliente, $id, $destino)->assertStatus(422);
            $this->cambiarEstadoApi($e->prestador, $id, $destino)->assertStatus(422);
        }

        $this->assertSame('cancelado', Pedido::findOrFail($id)->estado);
    }

    public function test_el_prestador_no_puede_cancelar_ni_confirmar(): void
    {
        $e  = $this->escenario();
        $id = $this->crearPedidoDirecto($e)->id;

        $this->cambiarEstadoApi($e->prestador, $id, 'cancelado')->assertStatus(422);
        $this->cambiarEstadoApi($e->prestador, $id, 'confirmado')->assertStatus(422);

        $this->assertSame('pendiente', Pedido::findOrFail($id)->estado);
    }
}
