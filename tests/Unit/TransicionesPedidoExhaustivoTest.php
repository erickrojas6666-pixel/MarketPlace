<?php

namespace Tests\Unit;

use App\Models\Pedido;
use App\Support\Pedidos\TransicionesPedido as T;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Pruebas de propiedades sobre TODA la máquina de estados (REQ-04), en las 8 combinaciones
 * de módulos (cotizaciones, escrow, disputas). No necesitan base de datos.
 */
#[Group('duras')]
class TransicionesPedidoExhaustivoTest extends TestCase
{
    private const FINALES = ['cancelado', 'confirmado', 'rechazado'];

    public static function configuraciones(): array
    {
        $salida = [];

        foreach ([false, true] as $cotizaciones) {
            foreach ([false, true] as $escrow) {
                foreach ([false, true] as $disputas) {
                    $nombre = sprintf('cotiz=%d escrow=%d disputas=%d', $cotizaciones, $escrow, $disputas);
                    $salida[$nombre] = [$cotizaciones, $escrow, $disputas];
                }
            }
        }

        return $salida;
    }

    private function aplicar(bool $cotizaciones, bool $escrow, bool $disputas): void
    {
        config([
            'marketplace.cotizaciones_habilitadas' => $cotizaciones,
            'marketplace.escrow_habilitado'        => $escrow,
            'marketplace.disputas_habilitadas'     => $disputas,
        ]);
    }

    private function todosLosActores(): array
    {
        return [T::CLIENTE, T::PRESTADOR, T::MEDIADOR, T::SISTEMA];
    }

    /** @return list<string> estados alcanzables desde $inicio (incluido) */
    private function alcanzables(string $inicio, array $actores): array
    {
        $vistos = [$inicio];
        $cola   = [$inicio];

        while ($cola !== []) {
            $actual = array_shift($cola);

            foreach (T::permitidas($actual, $actores) as $destino) {
                if (! in_array($destino, $vistos, true)) {
                    $vistos[] = $destino;
                    $cola[]   = $destino;
                }
            }
        }

        return $vistos;
    }

    #[DataProvider('configuraciones')]
    public function test_los_estados_finales_no_tienen_salida_para_nadie(bool $c, bool $e, bool $d): void
    {
        $this->aplicar($c, $e, $d);

        foreach (self::FINALES as $final) {
            $this->assertSame([], T::permitidas($final, $this->todosLosActores()), "'{$final}' no debe tener salidas.");
        }
    }

    #[DataProvider('configuraciones')]
    public function test_todo_destino_es_un_estado_valido_y_no_hay_autotransiciones(bool $c, bool $e, bool $d): void
    {
        $this->aplicar($c, $e, $d);

        foreach (Pedido::ESTADOS as $origen) {
            foreach (T::permitidas($origen, $this->todosLosActores()) as $destino) {
                $this->assertContains($destino, Pedido::ESTADOS, "'{$origen}' -> '{$destino}': destino fuera del catálogo.");
                $this->assertNotSame($origen, $destino, "'{$origen}' no debe poder pasar a sí mismo.");
            }
        }
    }

    #[DataProvider('configuraciones')]
    public function test_el_trabajo_siempre_puede_llegar_a_confirmado(bool $c, bool $e, bool $d): void
    {
        $this->aplicar($c, $e, $d);

        $this->assertContains(
            Pedido::CONFIRMADO,
            $this->alcanzables(Pedido::PENDIENTE, $this->todosLosActores()),
            'Desde pendiente debe poder llegarse a confirmado con esta configuración.'
        );
    }

    #[DataProvider('configuraciones')]
    public function test_ningun_estado_alcanzable_queda_atrapado(bool $c, bool $e, bool $d): void
    {
        $this->aplicar($c, $e, $d);

        foreach ($this->alcanzables(Pedido::PENDIENTE, $this->todosLosActores()) as $estado) {
            if (in_array($estado, self::FINALES, true)) {
                continue;
            }

            $llegables = $this->alcanzables($estado, $this->todosLosActores());

            $this->assertNotEmpty(
                array_intersect(self::FINALES, $llegables),
                "Desde '{$estado}' no se puede llegar a ningún estado final."
            );
        }
    }

    #[DataProvider('configuraciones')]
    public function test_cada_actor_tiene_sus_limites(bool $c, bool $e, bool $d): void
    {
        $this->aplicar($c, $e, $d);

        foreach (Pedido::ESTADOS as $origen) {
            $delCliente   = T::permitidas($origen, [T::CLIENTE]);
            $delPrestador = T::permitidas($origen, [T::PRESTADOR]);

            foreach ([Pedido::EN_PROGRESO, Pedido::COMPLETADO, Pedido::PAGADO] as $prohibido) {
                $this->assertNotContains($prohibido, $delCliente, "El cliente no debe llevar '{$origen}' a '{$prohibido}'.");
            }

            foreach ([Pedido::CONFIRMADO, Pedido::PAGADO] as $prohibido) {
                $this->assertNotContains($prohibido, $delPrestador, "El prestador no debe llevar '{$origen}' a '{$prohibido}'.");
            }
        }
    }

    public function test_sin_actores_o_con_actores_desconocidos_no_se_permite_nada(): void
    {
        $this->aplicar(true, true, true);

        foreach (Pedido::ESTADOS as $origen) {
            $this->assertSame([], T::permitidas($origen, []), "Sin actores, '{$origen}' no debe tener salidas.");
            $this->assertSame([], T::permitidas($origen, ['admin', 'CLIENTE', 'root', '']), "Actores falsos en '{$origen}'.");

            foreach (Pedido::ESTADOS as $destino) {
                $this->assertFalse(T::permite($origen, $destino, []));
            }
        }
    }

    public function test_estados_raros_no_permiten_transicion(): void
    {
        $this->aplicar(true, true, true);

        $raros = ['', ' ', 'PENDIENTE', 'Pendiente', 'pendiente ', "pendiente'; DROP TABLE pedidos;--", 'desconocido', '0', 'null'];

        foreach ($raros as $raro) {
            $this->assertSame([], T::permitidas($raro, $this->todosLosActores()), "Origen raro: '{$raro}'.");
            $this->assertFalse(T::permite(Pedido::PENDIENTE, $raro, $this->todosLosActores()), "Destino raro: '{$raro}'.");
        }
    }
}
