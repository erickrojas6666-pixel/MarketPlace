<?php

namespace Tests\Feature\Casos;

use App\Models\Pedido;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaEscenarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * CP-RES-01 · Pedido creado sin conexión se envía una sola vez (REQ-03 y REQ-08).
 *
 * Aquí se prueba SOLO la parte de servidor (pasos 6 y 7 del caso): la cola offline
 * reenvía POST /pedidos (ruta web, JSON) con la cabecera Idempotency-Key.
 * Los pasos 1 a 5 (modo avión, IndexedDB, evento online) son manuales en Chrome.
 */
#[Group('pdf'), Group('db'), Group('CP-RES-01')]
class CpRes01ColaOfflineIdempotenciaTest extends TestCase
{
    use CreaEscenarios, PreparaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurarMarketplace();
    }

    private function enviar(object $e, ?string $clave, ?\App\Models\User $usuario = null)
    {
        $cabeceras = $clave !== null ? ['Idempotency-Key' => $clave] : [];

        return $this->actingAs($usuario ?? $e->cliente)->postJson('/pedidos', [
            'servicio_id'        => $e->servicio->id,
            'direccion_servicio' => 'Calle Falsa 123',
        ], $cabeceras);
    }

    public function test_el_reenvio_con_la_misma_clave_devuelve_duplicado_y_no_crea_otro_pedido(): void
    {
        $e    = $this->escenario();
        $uuid = (string) Str::uuid();

        // Paso 6: la cola envía el pedido una vez.
        $primero = $this->enviar($e, $uuid)->assertCreated()->assertJsonPath('duplicado', false);

        // Paso 7: «Replay XHR» con la misma clave.
        $replay = $this->enviar($e, $uuid)->assertOk()->assertJsonPath('duplicado', true);

        $this->assertSame($primero->json('pedido_id'), $replay->json('pedido_id'));
        $this->assertSame(1, Pedido::where('cliente_id', $e->cliente->id)->count());
        $this->assertSame('pendiente', Pedido::findOrFail($primero->json('pedido_id'))->estado);
        $this->assertSame(1, $e->prestador->notifications()->count(), 'El prestador se notifica una sola vez.');
    }

    public function test_muchos_reenvios_siguen_dando_un_solo_pedido(): void
    {
        $e    = $this->escenario();
        $uuid = (string) Str::uuid();

        $this->enviar($e, $uuid)->assertCreated();

        for ($i = 0; $i < 5; $i++) {
            $this->enviar($e, $uuid)->assertOk()->assertJsonPath('duplicado', true);
        }

        $this->assertSame(1, Pedido::count());
        $this->assertSame(1, $e->prestador->notifications()->count());
    }

    public function test_claves_distintas_crean_pedidos_distintos(): void
    {
        $e = $this->escenario();

        $this->enviar($e, (string) Str::uuid())->assertCreated();
        $this->enviar($e, (string) Str::uuid())->assertCreated();

        $this->assertSame(2, Pedido::count());
    }

    public function test_la_misma_clave_de_otro_usuario_no_choca(): void
    {
        $e    = $this->escenario();
        $uuid = (string) Str::uuid();
        $otro = $this->crearUsuario('cliente');

        $this->enviar($e, $uuid)->assertCreated();
        $this->enviar($e, $uuid, $otro)->assertCreated()->assertJsonPath('duplicado', false);

        $this->assertSame(2, Pedido::count());
    }

    public function test_sin_clave_o_con_clave_malformada_no_hay_proteccion_y_se_documenta(): void
    {
        $e = $this->escenario();

        $this->enviar($e, null)->assertCreated();
        $this->enviar($e, null)->assertCreated();
        $this->enviar($e, 'corta')->assertCreated();   // < 16 caracteres: se ignora
        $this->enviar($e, 'corta')->assertCreated();

        $this->assertSame(4, Pedido::count(), 'Sin una Idempotency-Key válida cada envío crea un pedido.');
    }

    public function test_un_envio_invalido_no_consume_la_clave(): void
    {
        $e    = $this->escenario();
        $uuid = (string) Str::uuid();

        $this->actingAs($e->cliente)->postJson('/pedidos', [
            'servicio_id'        => $e->servicio->id,
            'direccion_servicio' => '',
        ], ['Idempotency-Key' => $uuid])->assertStatus(422);

        $this->enviar($e, $uuid)->assertCreated()->assertJsonPath('duplicado', false);
        $this->assertSame(1, Pedido::count());
    }
}
