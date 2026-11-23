<?php

namespace Tests\Feature\Casos;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * CP-PWA-05 · Rendimiento y accesibilidad con Lighthouse (REQ-08).
 *
 * La Fase 1 pide correr Lighthouse (categorías PWA, Rendimiento y
 * Accesibilidad) desde Chrome DevTools y adjuntar el reporte. Lighthouse
 * necesita un Chrome real renderizando la página; no existe en este entorno
 * de PHPUnit, así que esa corrida NO se automatiza aquí y debe hacerse a
 * mano, con el reporte de Lighthouse (o su captura) como evidencia.
 *
 * Lo que SÍ se automatiza aquí son señales estáticas que suelen hacer fallar
 * esos audits si faltan, para detectarlas antes de correr Lighthouse:
 *   - Accesibilidad: el viewport no debe desactivar el zoom (audit
 *     "user-scalable"), y <html> debe declarar idioma (audit "html-has-lang").
 *   - Rendimiento: la fuente externa usa preconnect (audit
 *     "uses-rel-preconnect") en vez de bloquear el primer render sin avisar
 *     antes al navegador.
 *
 * Defecto conocido (ligado al criterio de aceptación, punto 7 del reporte):
 * CpUx01AccesibilidadEstaticaTest::test_cada_label_del_dashboard_queda_asociado_a_su_campo
 * documenta un hallazgo de accesibilidad (WCAG 1.3.1 / 3.3.2) que, según la
 * revisión, sigue sin corregirse. No es bloqueante, pero debe registrarse en
 * la bitácora de incidencias (INC-XXX) y, si no se corrige antes de la
 * entrega, justificarse explícitamente en la sección de criterios de
 * aceptación del reporte final; no basta con que esta suite lo detecte.
 */
#[Group('CP-PWA-05'), Group('estatica')]
class CpPwa05RendimientoAccesibilidadTest extends TestCase
{
    private function appBlade(): string
    {
        $ruta = resource_path('views/app.blade.php');

        if (! is_file($ruta)) {
            $this->markTestSkipped('No existe resources/views/app.blade.php.');
        }

        return (string) file_get_contents($ruta);
    }

    public function test_el_viewport_no_desactiva_el_zoom(): void
    {
        $html = $this->appBlade();

        $this->assertMatchesRegularExpression('/<meta\s+name="viewport"/', $html, 'Falta <meta name="viewport">.');
        $this->assertDoesNotMatchRegularExpression(
            '/user-scalable\s*=\s*["\']?no/i',
            $html,
            'El viewport desactiva el zoom (user-scalable=no): falla el audit de accesibilidad de Lighthouse.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/maximum-scale\s*=\s*["\']?1(\.0)?["\']?\b/i',
            $html,
            'maximum-scale=1 impide el zoom en algunos navegadores: falla el audit de accesibilidad de Lighthouse.'
        );
    }

    public function test_el_documento_declara_idioma(): void
    {
        $this->assertMatchesRegularExpression(
            '/<html\s+lang="[^"]+"/',
            $this->appBlade(),
            'Falta lang en <html>: el audit "html-has-lang" de Lighthouse falla sin esto.'
        );
    }

    public function test_la_fuente_externa_usa_preconnect(): void
    {
        $html = $this->appBlade();

        $this->assertStringContainsString(
            '<link rel="preconnect" href="https://fonts.bunny.net">',
            $html,
            'Falta preconnect a la fuente externa: Lighthouse marca "uses-rel-preconnect" sin esto.'
        );
    }
}