<?php

namespace Tests\Feature\Casos;

use App\Models\SuscripcionesPush;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaUsuarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * CP-PUS-01 · Suscripción push y rechazo de endpoint no permitido (REQ-07).
 *
 * Aquí van los pasos 3, 6 y 7 (servidor). Los pasos 1, 2, 4 y 5 (llaves VAPID,
 * permiso del navegador, push:probar y clic en la notificación) son manuales.
 */
#[Group('pdf'), Group('db'), Group('CP-PUS-01')]
class CpPus01PushTest extends TestCase
{
    use CreaUsuarios, PreparaBaseDeDatos;

    private const SECRETO = 'secretoAuthFicticio';

    private function cuerpo(string $endpoint, array $keys = []): array
    {
        return [
            'endpoint' => $endpoint,
            'keys'     => array_merge(['p256dh' => 'BClavePublicaFicticia123', 'auth' => self::SECRETO], $keys),
        ];
    }

    public function test_un_endpoint_de_fcm_se_guarda_con_el_secreto_cifrado(): void
    {
        $cliente  = $this->crearUsuario('cliente', ['email' => 'cliente.prueba@example.com']);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';

        $this->actingAs($cliente)->postJson('/push/suscripciones', $this->cuerpo($endpoint))
            ->assertCreated()
            ->assertJson(['ok' => true]);

        // Paso 3: 1 suscripción, host fcm.googleapis.com, secreto cifrado.
        $this->assertSame(1, SuscripcionesPush::count());

        $fila = SuscripcionesPush::firstOrFail();
        $this->assertSame($cliente->id, (int) $fila->user_id);
        $this->assertSame('fcm.googleapis.com', parse_url($fila->endpoint, PHP_URL_HOST));
        $this->assertNotSame(self::SECRETO, $fila->auth_token_cifrado);
        $this->assertStringNotContainsString(self::SECRETO, $fila->auth_token_cifrado);
        $this->assertSame(self::SECRETO, Crypt::decryptString($fila->auth_token_cifrado));
    }

    public function test_pasos_6_y_7_endpoint_ajeno_o_sin_https_da_422_sin_filas(): void
    {
        $cliente = $this->crearUsuario('cliente');

        // Paso 6: https pero de un host no permitido.
        $this->actingAs($cliente)->postJson('/push/suscripciones', $this->cuerpo('https://example.com/push/prueba'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('endpoint');

        // Paso 7: host permitido pero sin HTTPS.
        $this->actingAs($cliente)->postJson('/push/suscripciones', $this->cuerpo('http://fcm.googleapis.com/prueba'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('endpoint');

        $this->assertSame(0, SuscripcionesPush::count());
    }

    public static function endpointsHostiles(): array
    {
        return [
            'sufijo engañoso'        => ['https://fcm.googleapis.com.evil.example/x'],
            'host en la ruta'        => ['https://evil.example/fcm.googleapis.com'],
            'credenciales falsas'    => ['https://fcm.googleapis.com@evil.example/x'],
            'prefijo pegado'         => ['https://evilfcm.googleapis.com/x'],
            'IPv4 loopback'          => ['https://127.0.0.1/x'],
            'metadatos de la nube'   => ['https://169.254.169.254/latest/meta-data'],
            'localhost'              => ['https://localhost/x'],
            'IPv6 loopback'          => ['https://[::1]/x'],
            'red interna'            => ['https://10.0.0.5/x'],
            'esquema ftp'            => ['ftp://fcm.googleapis.com/x'],
            'esquema file'           => ['file:///etc/passwd'],
            'javascript'             => ['javascript:alert(1)'],
            'sin esquema'            => ['fcm.googleapis.com/x'],
        ];
    }

    #[DataProvider('endpointsHostiles')]
    public function test_los_endpoints_hostiles_se_rechazan_anti_ssrf(string $endpoint): void
    {
        $cliente = $this->crearUsuario('cliente');

        $this->actingAs($cliente)->postJson('/push/suscripciones', $this->cuerpo($endpoint))
            ->assertStatus(422)
            ->assertJsonValidationErrors('endpoint');

        $this->assertSame(0, SuscripcionesPush::count());
    }

    public function test_faltan_las_claves_o_el_endpoint_es_demasiado_largo(): void
    {
        $cliente = $this->crearUsuario('cliente');

        $this->actingAs($cliente)->postJson('/push/suscripciones', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);

        $this->actingAs($cliente)->postJson('/push/suscripciones', $this->cuerpo('https://fcm.googleapis.com/'.str_repeat('a', 2100)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('endpoint');

        $this->assertSame(0, SuscripcionesPush::count());
    }

    public function test_un_invitado_no_puede_suscribirse(): void
    {
        $this->postJson('/push/suscripciones', $this->cuerpo('https://fcm.googleapis.com/fcm/send/x'))
            ->assertUnauthorized();

        $this->assertSame(0, SuscripcionesPush::count());
    }

    public function test_se_conservan_como_maximo_5_dispositivos_los_mas_recientes(): void
    {
        $cliente = $this->crearUsuario('cliente');

        for ($i = 1; $i <= 7; $i++) {
            $this->actingAs($cliente)
                ->postJson('/push/suscripciones', $this->cuerpo("https://fcm.googleapis.com/fcm/send/dispositivo-{$i}"))
                ->assertCreated();
        }

        $endpoints = $cliente->suscripcionesPush()->pluck('endpoint')->all();

        $this->assertCount(5, $endpoints);
        $this->assertNotContains('https://fcm.googleapis.com/fcm/send/dispositivo-1', $endpoints);
        $this->assertNotContains('https://fcm.googleapis.com/fcm/send/dispositivo-2', $endpoints);
        $this->assertContains('https://fcm.googleapis.com/fcm/send/dispositivo-7', $endpoints);
    }

    public function test_suscribirse_dos_veces_con_el_mismo_endpoint_no_duplica(): void
    {
        $cliente  = $this->crearUsuario('cliente');
        $endpoint = 'https://fcm.googleapis.com/fcm/send/mismo';

        $this->actingAs($cliente)->postJson('/push/suscripciones', $this->cuerpo($endpoint))->assertCreated();
        $this->actingAs($cliente)->postJson('/push/suscripciones', $this->cuerpo($endpoint))->assertCreated();

        $this->assertSame(1, SuscripcionesPush::where('endpoint', $endpoint)->count());
    }

    public function test_un_dispositivo_pertenece_a_una_sola_cuenta(): void
    {
        $a        = $this->crearUsuario('cliente');
        $b        = $this->crearUsuario('cliente');
        $endpoint = 'https://fcm.googleapis.com/fcm/send/compartido';

        $this->actingAs($a)->postJson('/push/suscripciones', $this->cuerpo($endpoint))->assertCreated();
        $this->actingAs($b)->postJson('/push/suscripciones', $this->cuerpo($endpoint))->assertCreated();

        $this->assertSame(0, $a->suscripcionesPush()->count(), 'La cuenta anterior deja de recibir avisos.');
        $this->assertSame(1, $b->suscripcionesPush()->count());
    }

    public function test_darse_de_baja_solo_borra_las_suscripciones_propias(): void
    {
        $a        = $this->crearUsuario('cliente');
        $b        = $this->crearUsuario('cliente');
        $propio   = 'https://fcm.googleapis.com/fcm/send/de-a';
        $ajeno    = 'https://fcm.googleapis.com/fcm/send/de-b';

        $this->actingAs($a)->postJson('/push/suscripciones', $this->cuerpo($propio))->assertCreated();
        $this->actingAs($b)->postJson('/push/suscripciones', $this->cuerpo($ajeno))->assertCreated();

        // A intenta borrar la de B: no pasa nada.
        $this->actingAs($a)->deleteJson('/push/suscripciones', ['endpoint' => $ajeno])->assertOk();
        $this->assertSame(1, $b->suscripcionesPush()->count());

        // A borra la suya.
        $this->actingAs($a)->deleteJson('/push/suscripciones', ['endpoint' => $propio])->assertOk();
        $this->assertSame(0, $a->suscripcionesPush()->count());
    }
}
