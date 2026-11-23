<?php

namespace Tests\Feature\Casos;

use App\Models\Pedido;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaEscenarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * CP-FUN-01 · Solicitar un servicio con conexión (REQ-03). Funcional positivo, prioridad Alta.
 */
#[Group('pdf'), Group('db'), Group('CP-FUN-01')]
class CpFun01SolicitarServicioTest extends TestCase
{
    use CreaEscenarios, PreparaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurarMarketplace();
    }

    public function test_el_cliente_solicita_un_servicio_y_el_prestador_recibe_la_notificacion(): void
    {
        $e = $this->escenario();

        // Paso 1: login por API y token Bearer.
        $login = $this->postJson('/api/auth/login', [
            'email'    => $e->cliente->email,
            'password' => 'Password123!',
        ])->assertOk();
        $auth = ['Authorization' => 'Bearer '.$login->json('token')];

        $pedidosAntes = Pedido::count();

        // Paso 2: POST /api/pedidos.
        $crear = $this->postJson('/api/pedidos', [
            'servicio_id'        => $e->servicio->id,
            'direccion_servicio' => 'Calle Falsa 123',
        ], $auth)->assertCreated();

        $this->assertSame('pendiente', $this->dato($crear, 'estado'));
        $id = (int) $this->dato($crear, 'id');

        // Paso 3: GET /api/pedidos lista ese pedido.
        $lista = $this->getJson('/api/pedidos', $auth)->assertOk();
        $ids   = collect($lista->json('data'))->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->assertContains($id, $ids);

        // Paso 4: BD. Exactamente 1 pedido nuevo y 1 notificación para el prestador.
        $this->assertSame($pedidosAntes + 1, Pedido::count());

        $pedido = Pedido::findOrFail($id);
        $this->assertSame($e->cliente->id, (int) $pedido->cliente_id);
        $this->assertSame('pendiente', $pedido->estado);
        $this->assertSame('Calle Falsa 123', $pedido->direccion_servicio);

        $this->assertSame(1, $e->prestador->notifications()->count(), 'El prestador debe tener 1 notificación.');
        $this->assertSame(0, $e->cliente->notifications()->count(), 'El cliente no debe recibir notificación al solicitar.');
    }

    public function test_las_acciones_permitidas_dependen_de_quien_consulta(): void
    {
        $e  = $this->escenario();
        $id = (int) $this->dato($this->solicitarPedidoApi($e)->assertCreated(), 'id');

        $paraCliente = $this->dato($this->api($e->cliente)->getJson("/api/pedidos/{$id}")->assertOk(), 'acciones_permitidas');
        $paraPrestador = $this->dato($this->api($e->prestador)->getJson("/api/pedidos/{$id}")->assertOk(), 'acciones_permitidas');

        $this->assertContains('cancelado', $paraCliente);
        $this->assertNotContains('aceptado', $paraCliente);
        $this->assertEqualsCanonicalizing(['aceptado', 'rechazado'], $paraPrestador);
    }

    public function test_sin_token_no_se_puede_solicitar(): void
    {
        $e = $this->escenario();

        $this->sinSesion()->postJson('/api/pedidos', [
            'servicio_id'        => $e->servicio->id,
            'direccion_servicio' => 'Calle Falsa 123',
        ])->assertUnauthorized();

        $this->assertSame(0, Pedido::count());
    }

    public function test_no_se_puede_contratar_el_propio_servicio(): void
    {
        $e = $this->escenario();

        $this->solicitarPedidoApi($e, $e->prestador)
            ->assertStatus(422)
            ->assertJsonValidationErrors('servicio_id');

        $this->assertSame(0, Pedido::count());
    }

    public function test_un_servicio_inexistente_se_rechaza(): void
    {
        $e = $this->escenario();

        $this->solicitarPedidoApi($e, null, ['servicio_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('servicio_id');
    }

    public function test_un_servicio_inactivo_no_se_puede_contratar(): void
    {
        $e = $this->escenario(500.00, false);

        $this->solicitarPedidoApi($e)
            ->assertStatus(422)
            ->assertJsonValidationErrors('servicio_id');

        $this->assertSame(0, Pedido::count());
    }

    public static function perfilesNoVerificados(): array
    {
        return ['pendiente' => ['pendiente'], 'rechazado' => ['rechazado']];
    }

    #[DataProvider('perfilesNoVerificados')]
    public function test_un_prestador_no_verificado_no_puede_recibir_pedidos(string $estadoPerfil): void
    {
        $e = $this->escenario(500.00, true, $estadoPerfil);

        $this->solicitarPedidoApi($e)
            ->assertStatus(422)
            ->assertJsonValidationErrors('servicio_id');

        $this->assertSame(0, Pedido::count());
    }

    public static function fechasInvalidas(): array
    {
        return [
            'fecha pasada'      => ['2020-01-01'],
            'texto'             => ['mañana temprano'],
            'fecha imposible'   => ['2026-02-31'],
        ];
    }

    #[DataProvider('fechasInvalidas')]
    public function test_la_fecha_deseada_debe_ser_hoy_o_futura(string $fecha): void
    {
        $e = $this->escenario();

        $this->solicitarPedidoApi($e, null, ['fecha_deseada' => $fecha])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_deseada');
    }

    public function test_una_cadena_con_codigo_sql_se_guarda_como_texto_inofensivo(): void
    {
        $e         = $this->escenario();
        $direccion = "Calle 1'; DROP TABLE pedidos;-- <script>alert(1)</script>";

        $id = (int) $this->dato($this->solicitarPedidoApi($e, null, ['direccion_servicio' => $direccion])->assertCreated(), 'id');

        $this->assertSame($direccion, Pedido::findOrFail($id)->direccion_servicio);
        $this->assertSame(1, Pedido::count());
    }
}
