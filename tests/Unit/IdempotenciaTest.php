<?php

namespace Tests\Unit;

use App\Support\Idempotencia;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * App\Support\Idempotencia (REQ-03 y REQ-05). Sin base de datos: usa la caché "array".
 */
#[Group('duras'), Group('CP-RES-01')]
class IdempotenciaTest extends TestCase
{
    private function peticion(?string $clave, ?int $usuarioId = 1): Request
    {
        $r = Request::create('/x', 'POST');

        if ($clave !== null) {
            $r->headers->set('Idempotency-Key', $clave);
        }

        $r->setUserResolver(fn () => $usuarioId === null ? null : new GenericUser(['id' => $usuarioId]));

        return $r;
    }

    public static function clavesValidas(): array
    {
        return [
            'uuid'         => ['550e8400-e29b-41d4-a716-446655440000'],
            '16 caracteres' => [str_repeat('a', 16)],
            '64 caracteres' => [str_repeat('Z', 64)],
            'con guiones'  => ['abc-DEF-123-ghi-456'],
        ];
    }

    #[DataProvider('clavesValidas')]
    public function test_acepta_claves_bien_formadas(string $clave): void
    {
        $this->assertSame($clave, Idempotencia::clave($this->peticion($clave)));
    }

    public static function clavesInvalidas(): array
    {
        return [
            'vacía'              => [''],
            'muy corta'          => ['abc'],
            '15 caracteres'      => [str_repeat('a', 15)],
            '65 caracteres'      => [str_repeat('a', 65)],
            'con espacios'       => ['clave con espacios 1234567890'],
            'con guion bajo'     => ['clave_con_guion_bajo_123456'],
            'con punto y coma'   => ['clave;DROP-TABLE-x-1234567'],
            'con acentos'        => ['ñandú-ñandú-ñandú-ñandú'],
            'con comillas'       => ["abc'def\"ghi-123456789"],
        ];
    }

    #[DataProvider('clavesInvalidas')]
    public function test_ignora_claves_mal_formadas(string $clave): void
    {
        $this->assertNull(Idempotencia::clave($this->peticion($clave)));
    }

    public function test_sin_cabecera_no_hay_clave(): void
    {
        $this->assertNull(Idempotencia::clave($this->peticion(null)));
    }

    public function test_con_la_misma_clave_la_accion_corre_una_sola_vez(): void
    {
        $clave    = (string) Str::uuid();
        $llamadas = 0;
        $accion   = function () use (&$llamadas) {
            $llamadas++;

            return ['pedido_id' => 42];
        };

        $a = Idempotencia::ejecutar($this->peticion($clave), 'pedidos.store', $accion);
        $b = Idempotencia::ejecutar($this->peticion($clave), 'pedidos.store', $accion);
        $c = Idempotencia::ejecutar($this->peticion($clave), 'pedidos.store', $accion);

        $this->assertSame(1, $llamadas);
        $this->assertSame(['pedido_id' => 42], $a);
        $this->assertSame(['pedido_id' => 42, 'duplicado' => true], $b);
        $this->assertSame(['pedido_id' => 42, 'duplicado' => true], $c);
    }

    public function test_sin_clave_la_accion_corre_cada_vez(): void
    {
        $llamadas = 0;
        $accion   = function () use (&$llamadas) {
            return ['n' => ++$llamadas];
        };

        Idempotencia::ejecutar($this->peticion(null), 'x', $accion);
        Idempotencia::ejecutar($this->peticion(null), 'x', $accion);

        $this->assertSame(2, $llamadas);
    }

    public function test_la_clave_es_independiente_por_usuario_y_por_ambito(): void
    {
        $clave    = (string) Str::uuid();
        $llamadas = 0;
        $accion   = function () use (&$llamadas) {
            return ['n' => ++$llamadas];
        };

        Idempotencia::ejecutar($this->peticion($clave, 1), 'pedidos.store', $accion);
        Idempotencia::ejecutar($this->peticion($clave, 2), 'pedidos.store', $accion);       // otro usuario
        Idempotencia::ejecutar($this->peticion($clave, 1), 'api.pagos.store', $accion);     // otro ámbito

        $this->assertSame(3, $llamadas);
    }

    public function test_un_fallo_no_se_guarda_y_el_reintento_si_se_ejecuta(): void
    {
        $clave = (string) Str::uuid();

        try {
            Idempotencia::ejecutar($this->peticion($clave), 'x', function () {
                throw new \RuntimeException('falla');
            });
            $this->fail('Debió lanzar la excepción.');
        } catch (\RuntimeException) {
            // esperado
        }

        $resultado = Idempotencia::ejecutar($this->peticion($clave), 'x', fn () => ['ok' => 1]);

        $this->assertSame(['ok' => 1], $resultado);
    }

    public function test_el_resultado_guardado_no_cambia_aunque_la_accion_devuelva_otra_cosa(): void
    {
        $clave = (string) Str::uuid();

        Idempotencia::ejecutar($this->peticion($clave), 'x', fn () => ['valor' => 'primero']);
        $segundo = Idempotencia::ejecutar($this->peticion($clave), 'x', fn () => ['valor' => 'segundo']);

        $this->assertSame('primero', $segundo['valor']);
        $this->assertTrue($segundo['duplicado']);
    }
}
