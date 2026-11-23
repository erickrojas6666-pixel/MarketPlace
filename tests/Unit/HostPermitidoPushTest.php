<?php

namespace Tests\Unit;

use App\Services\WebPushService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Anti-SSRF de Web Push (REQ-07): WebPushService::hostPermitido. Sin base de datos.
 */
#[Group('duras'), Group('CP-PUS-01')]
class HostPermitidoPushTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webpush.hosts_permitidos' => ['fcm.googleapis.com', 'push.services.mozilla.com']]);
    }

    public static function permitidos(): array
    {
        return [
            'fcm exacto'            => ['https://fcm.googleapis.com/fcm/send/abc'],
            'fcm en mayúsculas'     => ['https://FCM.GOOGLEAPIS.COM/fcm/send/abc'],
            'mozilla exacto'        => ['https://push.services.mozilla.com/wpush/v2/abc'],
            'subdominio de mozilla' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'],
        ];
    }

    #[DataProvider('permitidos')]
    public function test_acepta_los_servicios_conocidos(string $endpoint): void
    {
        $this->assertTrue(WebPushService::hostPermitido($endpoint), $endpoint);
    }

    public static function rechazados(): array
    {
        return [
            'sin https'               => ['http://fcm.googleapis.com/x'],
            'sufijo engañoso'         => ['https://fcm.googleapis.com.evil.example/x'],
            'prefijo pegado'          => ['https://evilfcm.googleapis.com/x'],
            'mozilla sin punto'       => ['https://notpush.services.mozilla.com/x'],
            'credenciales falsas'     => ['https://fcm.googleapis.com@evil.example/x'],
            'host en la ruta'         => ['https://evil.example/fcm.googleapis.com'],
            'host en la query'        => ['https://evil.example/?h=fcm.googleapis.com'],
            'IPv4 privada'            => ['https://10.0.0.5/x'],
            'loopback'                => ['https://127.0.0.1/x'],
            'metadatos de la nube'    => ['https://169.254.169.254/latest/meta-data'],
            'localhost'               => ['https://localhost/x'],
            'IPv6'                    => ['https://[::1]/x'],
            'esquema file'            => ['file:///etc/passwd'],
            'esquema javascript'      => ['javascript:alert(1)'],
            'sin esquema'             => ['fcm.googleapis.com/x'],
            'vacío'                   => [''],
            'solo https'              => ['https://'],
            'espacios'                => ['   '],
        ];
    }

    #[DataProvider('rechazados')]
    public function test_rechaza_todo_lo_demas(string $endpoint): void
    {
        $this->assertFalse(WebPushService::hostPermitido($endpoint), "Debió rechazarse: «{$endpoint}»");
    }

    public function test_con_la_lista_vacia_se_rechaza_todo(): void
    {
        config(['services.webpush.hosts_permitidos' => []]);

        $this->assertFalse(WebPushService::hostPermitido('https://fcm.googleapis.com/x'));
    }

    public function test_la_configuracion_real_incluye_fcm(): void
    {
        $real = require config_path('services.php');

        $this->assertContains(
            'fcm.googleapis.com',
            (array) ($real['webpush']['hosts_permitidos'] ?? []),
            'CP-PUS-01 espera que fcm.googleapis.com esté permitido en config/services.php.'
        );
    }
}
