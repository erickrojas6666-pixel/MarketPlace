<?php

namespace Tests\Concerns;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PerfilPrestador;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Datos de prueba ficticios (ver "Datos de prueba comunes" del documento de casos):
 * cliente, prestador verificado y el servicio «Plomería a domicilio» de 500.00.
 */
trait CreaEscenarios
{
    use CreaUsuarios;

    /** Configuracion base de los casos: pasarela simulada, comision 12 %, sin modulos extra. */
    protected function configurarMarketplace(array $extra = []): void
    {
        config(array_merge([
            'marketplace.cotizaciones_habilitadas' => false,
            'marketplace.escrow_habilitado'        => false,
            'marketplace.disputas_habilitadas'     => false,
            'marketplace.pasarela'                 => 'simulada',
            'marketplace.comision_porcentaje'      => 12.0,
            'app.debug'                            => false,
        ], $extra));
    }

    protected function escenario(?float $precio = 500.00, bool $activo = true, string $estadoPerfil = 'verificado'): object
    {
        $categoria = Categoria::forceCreate([
            'nombre' => 'Plomería '.Str::random(5),
            'slug'   => 'plomeria-'.Str::lower(Str::random(8)),
        ]);

        $prestador = $this->crearUsuario('prestador', ['nombre' => 'Prestador Prueba']);

        $perfil = PerfilPrestador::forceCreate([
            'user_id'             => $prestador->id,
            'estado_verificacion' => $estadoPerfil,
        ]);

        $servicio = Servicio::forceCreate([
            'perfil_prestador_id' => $perfil->id,
            'categoria_id'        => $categoria->id,
            'titulo'              => 'Plomería a domicilio',
            'descripcion'         => 'Servicio de prueba',
            'precio_base'         => $precio,
            'activo'              => $activo,
        ]);

        $cliente = $this->crearUsuario('cliente', ['nombre' => 'Cliente Prueba']);

        return (object) compact('categoria', 'prestador', 'perfil', 'servicio', 'cliente');
    }

    /** Autentica la siguiente peticion como este usuario (token Sanctum). */
    protected function api(User $usuario): static
    {
        app('auth')->forgetGuards();
        Sanctum::actingAs($usuario, ['*']);

        return $this;
    }

    /** Las siguientes peticiones API van sin token. */
    protected function sinSesion(): static
    {
        app('auth')->forgetGuards();

        return $this;
    }

    /** Lee un campo de la respuesta, con o sin envoltorio "data". */
    protected function dato(TestResponse $respuesta, string $clave): mixed
    {
        $json = $respuesta->json();

        return data_get($json, 'data.'.$clave, data_get($json, $clave));
    }

    /** Pedido creado directo en la BD (sin pasar por la API, sin notificaciones). */
    protected function crearPedidoDirecto(object $e, string $estado = Pedido::PENDIENTE, ?User $cliente = null): Pedido
    {
        return Pedido::forceCreate([
            'cliente_id'         => ($cliente ?? $e->cliente)->id,
            'servicio_id'        => $e->servicio->id,
            'estado'             => $estado,
            'direccion_servicio' => 'Calle Falsa 123',
            'fecha_programada'   => now()->addDay(),
        ]);
    }

    protected function solicitarPedidoApi(object $e, ?User $cliente = null, array $extra = [], array $cabeceras = []): TestResponse
    {
        return $this->api($cliente ?? $e->cliente)->postJson('/api/pedidos', array_merge([
            'servicio_id'        => $e->servicio->id,
            'direccion_servicio' => 'Calle Falsa 123',
        ], $extra), $cabeceras);
    }

    protected function cambiarEstadoApi(User $usuario, int $pedidoId, string $estado): TestResponse
    {
        return $this->api($usuario)->patchJson("/api/pedidos/{$pedidoId}/estado", ['estado' => $estado]);
    }

    protected function pagarApi(User $usuario, int $pedidoId, ?string $claveIdempotencia = null): TestResponse
    {
        $cabeceras = $claveIdempotencia !== null ? ['Idempotency-Key' => $claveIdempotencia] : [];

        return $this->api($usuario)->postJson('/api/pagos', ['pedido_id' => $pedidoId], $cabeceras);
    }

    protected function liberarApi(User $usuario, int $pagoId): TestResponse
    {
        return $this->api($usuario)->postJson("/api/pagos/{$pagoId}/liberar");
    }

    /** El cliente solicita y el prestador acepta. Devuelve el id del pedido. */
    protected function pedidoAceptadoPorApi(object $e): int
    {
        $pedidoId = (int) $this->dato($this->solicitarPedidoApi($e)->assertCreated(), 'id');

        $this->cambiarEstadoApi($e->prestador, $pedidoId, 'aceptado')->assertOk();

        return $pedidoId;
    }

    /**
     * Flujo completo con escrow activo: aceptado, pagado, en progreso, completado y confirmado.
     *
     * @return array{0:int,1:int} [pedido_id, pago_id]
     */
    protected function pedidoConfirmadoConPago(object $e): array
    {
        $pedidoId = $this->pedidoAceptadoPorApi($e);
        $pagoId   = (int) $this->dato($this->pagarApi($e->cliente, $pedidoId)->assertCreated(), 'id');

        $this->cambiarEstadoApi($e->prestador, $pedidoId, 'en_progreso')->assertOk();
        $this->cambiarEstadoApi($e->prestador, $pedidoId, 'completado')->assertOk();
        $this->cambiarEstadoApi($e->cliente, $pedidoId, 'confirmado')->assertOk();

        return [$pedidoId, $pagoId];
    }
}
