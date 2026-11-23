<?php

namespace Tests\Unit;

use App\Models\Pedido;
use App\Models\User;
use App\Notifications\EstadoPedidoActualizado;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Texto de la notificación push (CP-PUS-01, paso 5). Sin base de datos.
 */
#[Group('duras'), Group('CP-PUS-01')]
class NotificacionPedidoWebPushTest extends TestCase
{
    private function pedido(int $id): Pedido
    {
        $pedido     = new Pedido();
        $pedido->id = $id;

        return $pedido;
    }

    public function test_aceptado_dice_tu_pedido_ahora_esta_aceptado_y_abre_el_dashboard(): void
    {
        $payload = (new EstadoPedidoActualizado($this->pedido(7), Pedido::ACEPTADO))->toWebPush(new User());

        $this->assertSame('Tu pedido #7 ahora está: aceptado.', $payload['cuerpo']);
        $this->assertSame('/dashboard', $payload['url']);
        $this->assertSame('pedido-7', $payload['tag']);
    }

    public function test_pendiente_avisa_de_una_nueva_solicitud_al_prestador(): void
    {
        $payload = (new EstadoPedidoActualizado($this->pedido(12), Pedido::PENDIENTE))->toWebPush(new User());

        $this->assertSame('Tienes una nueva solicitud de servicio (#12).', $payload['cuerpo']);
    }

    public function test_el_mensaje_no_incluye_datos_sensibles(): void
    {
        $pedido                     = $this->pedido(9);
        $pedido->direccion_servicio = 'Calle Falsa 123';

        $payload = (new EstadoPedidoActualizado($pedido, Pedido::PAGADO))->toWebPush(new User());

        $texto = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Calle Falsa', $texto);
        $this->assertStringNotContainsString('$', $texto);
    }
}
