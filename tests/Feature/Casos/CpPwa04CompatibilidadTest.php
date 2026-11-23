<?php

namespace Tests\Feature\Casos;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * CP-PWA-04 · Compatibilidad entre navegadores (REQ-08).
 *
 * La categoría "Compatibilidad" de la Fase 1 pide repetir la prueba en varios
 * navegadores/plataformas (Chrome, Firefox, Safari/iOS, Edge, Android), ya
 * que no todos implementan igual las APIs de una PWA: Safari no dispara
 * "beforeinstallprompt", Firefox de escritorio no soporta Background Sync,
 * etc. Eso requiere un navegador real por plataforma y no se puede
 * automatizar con PHPUnit ni con esta suite.
 *
 * Lo que SÍ se automatiza aquí: que el código tenga una ruta de salida
 * (detección de características / try-catch) para cada API que NO está
 * disponible en todos los navegadores, de forma que la app degrade sin
 * romperse en los que no la soportan, en vez de asumir que todos la tienen.
 *
 * Lo que NO se automatiza, y queda pendiente como matriz manual con
 * evidencia (una captura por navegador/plataforma), tal como pide la Fase 1:
 *   - Chrome/Edge de escritorio y Android: instalación, push, Background Sync.
 *   - Firefox de escritorio: todo lo anterior salvo Background Sync.
 *   - Safari/iOS: "Agregar a inicio" manual (sin beforeinstallprompt), sin
 *     Background Sync y con límites más estrictos de IndexedDB en privado.
 * Se deja constancia de esta omisión en vez de darla por cubierta.
 */
#[Group('CP-PWA-04'), Group('estatica')]
class CpPwa04CompatibilidadTest extends TestCase
{
    private function contenido(string $relativo): string
    {
        $ruta = resource_path($relativo);

        if (! is_file($ruta)) {
            $this->markTestSkipped("No existe resources/{$relativo}.");
        }

        return (string) file_get_contents($ruta);
    }

    public function test_el_prompt_de_instalacion_no_se_asume_disponible_en_todos_los_navegadores(): void
    {
        $js = $this->contenido('js/composables/usePwa.js');

        // Safari/iOS no disparan beforeinstallprompt: debe existir una guía alterna (mostrarGuiaIOS).
        $this->assertStringContainsString('beforeinstallprompt', $js);
        $this->assertStringContainsString('mostrarGuiaIOS', $js, 'Falta una ruta alterna para iOS, que no dispara beforeinstallprompt.');
        $this->assertStringContainsString('esStandalone', $js);
    }

    public function test_el_background_sync_tiene_una_alternativa_para_navegadores_sin_soporte(): void
    {
        $js = $this->contenido('js/offline/sync.js');

        $this->assertMatchesRegularExpression(
            '/reg\?\.sync\?\.register/',
            $js,
            'El registro de Background Sync debe usar encadenamiento opcional (solo Chromium lo soporta).'
        );
        $this->assertStringContainsString(
            "window.addEventListener('online'",
            $js,
            'Debe existir un respaldo por el evento "online" para navegadores sin Background Sync (Firefox, Safari).'
        );
    }

    public function test_el_candado_entre_pestanas_tiene_una_alternativa_sin_el(): void
    {
        $js = $this->contenido('js/offline/sync.js');

        $this->assertStringContainsString(
            'navigator.locks?.request',
            $js,
            'navigator.locks no está en todos los navegadores: debe comprobarse antes de usarlo.'
        );
    }
}