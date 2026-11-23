<?php

namespace Tests\Feature\Casos;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * CP-PWA-02 · Service Worker, caché y modo offline (REQ-03 y REQ-08).
 *
 * La Fase 1 pide probar Service Worker, caché y modo offline desde DevTools
 * (pestaña Application, casilla "Offline" en Network). CP-RES-01 ya cubre el
 * reenvío del lado del servidor; esta clase cubre, a nivel de código, las
 * piezas que ese caso no toca: el propio Service Worker, sus estrategias de
 * caché y la página de respaldo offline.html.
 *
 * Lo que SÍ se automatiza aquí (lectura de archivos, sin navegador real):
 *   - Que public/sw.js exista, precachee los assets de /build y registre una
 *     ruta de fallback a /offline.html para navegaciones.
 *   - Que haya una estrategia de caché para imágenes/fuentes (runtime caching).
 *   - Que public/offline.html exista y sea accesible como documento (role,
 *     idioma, botón para reintentar).
 *   - Que el registro del Service Worker esté protegido por detección de
 *     características (no rompe en navegadores sin soporte).
 *
 * Lo que NO se automatiza, y debe verificarse a mano con captura de
 * evidencia (DevTools > Application > Service Workers / Cache Storage, y
 * Network > Offline), como exige la Fase 1:
 *   - Que el SW se instale y active realmente en el navegador.
 *   - Que, con la casilla "Offline" activada, la navegación caiga en
 *     offline.html y los assets precacheados se sirvan desde Cache Storage.
 *   - Que una actualización del SW (nueva versión) dispare el flujo de
 *     "hay una actualización disponible" que usePwa.js expone a la UI.
 * Se documenta esta omisión en vez de darla por hecha, tal como pide la Fase 1.
 */
#[Group('CP-PWA-02'), Group('estatica')]
class CpPwa02ServiceWorkerCacheOfflineTest extends TestCase
{
    private function contenidoSw(): string
    {
        $ruta = public_path('sw.js');

        if (! is_file($ruta)) {
            $this->markTestSkipped('No existe public/sw.js (se genera con "npm run build").');
        }

        return (string) file_get_contents($ruta);
    }

    public function test_el_service_worker_usa_offline_html_como_respaldo_de_navegacion(): void
    {
        $this->assertStringContainsString(
            '/offline.html',
            $this->contenidoSw(),
            'sw.js no referencia /offline.html como página de respaldo offline.'
        );
    }

    public function test_el_service_worker_precachea_los_assets_del_build(): void
    {
        $sw = $this->contenidoSw();

        $this->assertMatchesRegularExpression(
            '/"revision"\s*:\s*"[0-9a-f]+"\s*,\s*"url"\s*:\s*"\/build\/assets\//',
            $sw,
            'No se encontró una lista de precacheo con revision/url para /build/assets.'
        );
    }

    public function test_el_service_worker_cachea_imagenes_y_fuentes_para_uso_sin_conexion(): void
    {
        $sw = $this->contenidoSw();

        $this->assertStringContainsString('oficiosya-estaticos', $sw, 'Falta la caché de runtime para imágenes/fuentes propias.');
        $this->assertStringContainsString('fonts.bunny.net', $sw, 'Falta la caché de runtime para la fuente externa.');
    }

    public function test_la_pagina_offline_existe_y_es_accesible(): void
    {
        $ruta = public_path('offline.html');

        if (! is_file($ruta)) {
            $this->markTestSkipped('No existe public/offline.html.');
        }

        $html = (string) file_get_contents($ruta);

        $this->assertMatchesRegularExpression('/<html\s+lang="es"/', $html, 'offline.html debe declarar lang="es".');
        $this->assertStringContainsString('role="main"', $html, 'El contenedor principal de offline.html debe tener role="main".');
        $this->assertMatchesRegularExpression('/<button[^>]*>\s*Reintentar\s*<\/button>/', $html, 'Falta el botón "Reintentar" en offline.html.');
    }

    public function test_el_registro_del_service_worker_esta_protegido_por_deteccion_de_soporte(): void
    {
        $ruta = resource_path('js/composables/usePwa.js');

        if (! is_file($ruta)) {
            $this->markTestSkipped('No existe resources/js/composables/usePwa.js.');
        }

        $js = (string) file_get_contents($ruta);

        $this->assertStringContainsString(
            "'serviceWorker' in navigator",
            $js,
            'El registro del Service Worker debe comprobar que el navegador lo soporte antes de llamarlo (compatibilidad).'
        );
    }
}
