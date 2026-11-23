<?php

namespace Tests\Feature\Casos;

use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\CreaUsuarios;
use Tests\Concerns\PreparaBaseDeDatos;
use Tests\TestCase;

/**
 * CN-SEG-02 · Límite de intentos de inicio de sesión (REQ-01). Seguridad negativa.
 * Máximo 5 intentos fallidos por correo e IP por minuto; mismo mensaje exista o no el correo.
 */
#[Group('pdf'), Group('db'), Group('CN-SEG-02')]
class CnSeg02LimiteLoginTest extends TestCase
{
    use CreaUsuarios, PreparaBaseDeDatos;

    private const CORREO = 'cliente.prueba@example.com';
    private const BUENA  = 'Correcta-12345';

    private function fallar(string $correo = self::CORREO, string $clave = 'incorrecta-123')
    {
        return $this->postJson('/api/auth/login', ['email' => $correo, 'password' => $clave]);
    }

    public function test_el_sexto_intento_se_bloquea_y_tras_esperar_se_puede_entrar(): void
    {
        $this->crearUsuario('cliente', ['email' => self::CORREO, 'password' => self::BUENA]);

        // Paso 1: intentos 1 a 5 -> 401.
        for ($i = 1; $i <= 5; $i++) {
            $this->fallar()
                ->assertStatus(401)
                ->assertJsonPath('message', 'Credenciales incorrectas.');
        }

        // Paso 2: el sexto -> 429 con Retry-After.
        $bloqueado = $this->fallar()->assertStatus(429)->assertHeader('Retry-After');
        $espera    = (int) $bloqueado->headers->get('Retry-After');

        $this->assertGreaterThanOrEqual(1, $espera);
        $this->assertLessThanOrEqual(60, $espera);
        $this->assertStringContainsString('Demasiados intentos', (string) $bloqueado->json('message'));

        // Incluso con la contraseña correcta, mientras dure el bloqueo no entra.
        $this->fallar(self::CORREO, self::BUENA)->assertStatus(429);

        // Paso 3: un correo que no existe responde igual que uno existente (no se enumeran usuarios).
        $this->fallar('no.existe@example.com')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales incorrectas.');

        // Paso 4: pasado el tiempo de Retry-After, la contraseña correcta devuelve token.
        $this->travel($espera + 1)->seconds();

        $this->fallar(self::CORREO, self::BUENA)
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'expires_at', 'usuario' => ['id', 'email']]);
    }

    public function test_el_limite_no_distingue_mayusculas_en_el_correo(): void
    {
        $this->crearUsuario('cliente', ['email' => self::CORREO, 'password' => self::BUENA]);

        foreach ([self::CORREO, strtoupper(self::CORREO), self::CORREO, strtoupper(self::CORREO), self::CORREO] as $correo) {
            $this->fallar($correo)->assertStatus(401);
        }

        $this->fallar(strtoupper(self::CORREO))->assertStatus(429);
    }

    public function test_un_intento_correcto_reinicia_el_contador(): void
    {
        $this->crearUsuario('cliente', ['email' => self::CORREO, 'password' => self::BUENA]);

        for ($i = 0; $i < 4; $i++) {
            $this->fallar()->assertStatus(401);
        }

        $this->fallar(self::CORREO, self::BUENA)->assertOk();

        for ($i = 0; $i < 4; $i++) {
            $this->fallar()->assertStatus(401);
        }
    }

    public function test_el_bloqueo_de_un_correo_no_afecta_a_otro_usuario(): void
    {
        $this->crearUsuario('cliente', ['email' => self::CORREO, 'password' => self::BUENA]);
        $this->crearUsuario('cliente', ['email' => 'otra.persona@example.com', 'password' => self::BUENA]);

        for ($i = 0; $i < 5; $i++) {
            $this->fallar()->assertStatus(401);
        }

        $this->fallar()->assertStatus(429);
        $this->fallar('otra.persona@example.com', self::BUENA)->assertOk();
    }

    public function test_el_contador_se_guarda_por_correo_e_ip(): void
    {
        $this->crearUsuario('cliente', ['email' => self::CORREO, 'password' => self::BUENA]);

        $this->fallar()->assertStatus(401);

        // La llave del contador incluye correo e IP (127.0.0.1 en las pruebas).
        $llave = \Illuminate\Support\Str::transliterate(strtolower(self::CORREO).'|127.0.0.1');
        $this->assertSame(1, RateLimiter::attempts($llave));
    }
}
