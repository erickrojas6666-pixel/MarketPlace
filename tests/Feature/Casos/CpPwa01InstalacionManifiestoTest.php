<?php

namespace Tests\Feature\Casos;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * CP-PWA-01 · Instalación de la PWA: manifiesto e iconos (REQ-08).
 *
 * La categoría "Instalación" de la Fase 1 pide verificar el manifiesto
 * (nombre, iconos, colores) y la posibilidad de instalar la app (prompt de
 * Chrome/Edge en escritorio y Android, "Agregar a pantalla de inicio" en iOS).
 *
 * Lo que SÍ se automatiza aquí (estático, sin navegador):
 *   - Que public/manifest.json exista y tenga los campos que Lighthouse exige
 *     para el audit "Installable" (name, short_name, start_url, display,
 *     background_color, theme_color, un icono >= 192 y uno >= 512, "any").
 *   - Que exista al menos un icono "maskable" (lo pide el audit de iconos
 *     "maskable" de Lighthouse / Android adaptativo).
 *   - Que cada icono referenciado en el manifiesto exista físicamente en disco.
 *   - Que app.blade.php enlace el manifiesto y traiga apple-touch-icon
 *     (iOS no lee manifest.json: sin este link no se puede instalar en iOS).
 *
 * Lo que NO se automatiza, y queda pendiente como verificación manual (Chrome
 * DevTools > Application > Manifest, y el audit "Installable" de Lighthouse):
 *   - Que el navegador realmente dispare el evento beforeinstallprompt.
 *   - Que la instalación en Android/Chrome/Edge y el alta en iOS funcionen
 *     de punta a punta (requiere un dispositivo o emulador real).
 * Esta omisión se documenta explícitamente, tal como pide la Fase 1 para las
 * categorías que no se cubren con pruebas automatizadas.
 */
#[Group('CP-PWA-01'), Group('estatica')]
class CpPwa01InstalacionManifiestoTest extends TestCase
{
    private function manifiesto(): array
    {
        $ruta = public_path('manifest.json');

        if (! is_file($ruta)) {
            $this->markTestSkipped('No existe public/manifest.json.');
        }

        $datos = json_decode((string) file_get_contents($ruta), true);
        $this->assertIsArray($datos, 'manifest.json no es un JSON válido.');

        return $datos;
    }

    public function test_el_manifiesto_trae_los_campos_minimos_para_ser_instalable(): void
    {
        $m = $this->manifiesto();

        foreach (['name', 'short_name', 'start_url', 'display', 'background_color', 'theme_color', 'icons'] as $campo) {
            $this->assertArrayHasKey($campo, $m, "Falta el campo \"{$campo}\" en manifest.json.");
        }

        $this->assertNotSame('', trim((string) $m['name']), 'El campo "name" no puede estar vacío.');
        $this->assertContains(
            $m['display'],
            ['standalone', 'fullscreen', 'minimal-ui'],
            'display debe ser standalone/fullscreen/minimal-ui para que la app se sienta instalada.'
        );
    }

    public function test_el_manifiesto_tiene_un_icono_grande_any_y_uno_maskable(): void
    {
        $iconos = $this->manifiesto()['icons'] ?? [];
        $this->assertNotEmpty($iconos, 'manifest.json no trae iconos.');

        $tieneGrandeAny = false;
        $tieneMaskable  = false;

        foreach ($iconos as $icono) {
            $ancho = (int) explode('x', (string) ($icono['sizes'] ?? '0x0'))[0];

            if ($ancho >= 192 && ($icono['purpose'] ?? 'any') === 'any') {
                $tieneGrandeAny = true;
            }
            if (($icono['purpose'] ?? '') === 'maskable') {
                $tieneMaskable = true;
            }
        }

        $this->assertTrue($tieneGrandeAny, 'Falta un icono purpose=any de al menos 192x192 (lo exige el audit Installable de Lighthouse).');
        $this->assertTrue($tieneMaskable, 'Falta un icono purpose=maskable (icono adaptativo de Android).');
    }

    public function test_cada_icono_del_manifiesto_existe_en_disco(): void
    {
        $iconos = $this->manifiesto()['icons'] ?? [];

        foreach ($iconos as $icono) {
            $ruta = public_path(ltrim((string) ($icono['src'] ?? ''), '/'));
            $this->assertFileExists($ruta, "El icono \"{$icono['src']}\" está en el manifiesto pero no existe en /public.");
        }
    }

    public function test_el_layout_principal_enlaza_el_manifiesto_y_el_icono_de_ios(): void
    {
        $ruta = resource_path('views/app.blade.php');

        if (! is_file($ruta)) {
            $this->markTestSkipped('No existe resources/views/app.blade.php.');
        }

        $html = (string) file_get_contents($ruta);

        $this->assertMatchesRegularExpression('/<link\s+rel="manifest"\s+href="\/manifest\.json"/', $html, 'Falta <link rel="manifest"> en app.blade.php.');
        $this->assertMatchesRegularExpression('/<link\s+rel="apple-touch-icon"/', $html, 'Falta apple-touch-icon: sin él no se puede instalar en iOS.');
        $this->assertMatchesRegularExpression('/<meta\s+name="theme-color"/', $html, 'Falta <meta name="theme-color">.');
    }
}
