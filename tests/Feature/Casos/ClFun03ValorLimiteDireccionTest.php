<?php

namespace Tests\Feature\Casos;

use App\Models\Pedido;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaEscenarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * CL-FUN-03 · Valor límite de la dirección del servicio: 255 y 256 caracteres (REQ-03).
 */
#[Group('pdf'), Group('db'), Group('CL-FUN-03')]
class ClFun03ValorLimiteDireccionTest extends TestCase
{
    use CreaEscenarios, PreparaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurarMarketplace();
    }

    public function test_255_caracteres_se_aceptan_y_256_se_rechazan(): void
    {
        $e = $this->escenario();

        // Pasos 2 y 3: 255 «A» -> 201.
        $ok = $this->solicitarPedidoApi($e, null, ['direccion_servicio' => str_repeat('A', 255)])->assertCreated();
        $id = (int) $this->dato($ok, 'id');

        // Pasos 4 y 5: 256 «A» -> 422 en direccion_servicio, sin pedido nuevo.
        $this->solicitarPedidoApi($e, null, ['direccion_servicio' => str_repeat('A', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('direccion_servicio');

        $this->assertSame(1, Pedido::where('cliente_id', $e->cliente->id)->count());
        $this->assertSame(255, mb_strlen(Pedido::findOrFail($id)->direccion_servicio));
    }

    public static function limites(): array
    {
        return [
            '1 carácter'        => [str_repeat('A', 1), 201],
            '254 caracteres'    => [str_repeat('A', 254), 201],
            '255 caracteres'    => [str_repeat('A', 255), 201],
            '256 caracteres'    => [str_repeat('A', 256), 422],
            '1000 caracteres'   => [str_repeat('A', 1000), 422],
            '255 con ñ'         => [str_repeat('ñ', 255), 201],
            '256 con ñ'         => [str_repeat('ñ', 256), 422],
        ];
    }

    #[DataProvider('limites')]
    public function test_valor_limite(string $direccion, int $esperado): void
    {
        $e = $this->escenario();

        $r = $this->solicitarPedidoApi($e, null, ['direccion_servicio' => $direccion])->assertStatus($esperado);

        if ($esperado === 201) {
            $this->assertSame($direccion, Pedido::findOrFail((int) $this->dato($r, 'id'))->direccion_servicio);
        } else {
            $r->assertJsonValidationErrors('direccion_servicio');
            $this->assertSame(0, Pedido::count());
        }
    }

    public static function valoresInvalidos(): array
    {
        return [
            'nulo'          => [null],
            'vacío'         => [''],
            'solo espacios' => ['     '],
            'número'        => [12345],
            'booleano'      => [true],
            'arreglo'       => [['Calle', 'Falsa']],
        ];
    }

    #[DataProvider('valoresInvalidos')]
    public function test_una_direccion_vacia_o_de_tipo_incorrecto_se_rechaza(mixed $valor): void
    {
        $e = $this->escenario();

        $this->solicitarPedidoApi($e, null, ['direccion_servicio' => $valor])
            ->assertStatus(422)
            ->assertJsonValidationErrors('direccion_servicio');

        $this->assertSame(0, Pedido::count());
    }

    public function test_sin_el_campo_direccion_se_rechaza(): void
    {
        $e = $this->escenario();

        $this->api($e->cliente)->postJson('/api/pedidos', ['servicio_id' => $e->servicio->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('direccion_servicio');
    }
}
