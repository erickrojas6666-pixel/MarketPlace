<?php

namespace Tests\Feature\Casos;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * CP-PWA-03 · Almacenamiento local (IndexedDB) (REQ-03 y REQ-08).
 *
 * La categoría "Almacenamiento" de la Fase 1 pide revisar qué guarda la app
 * en el dispositivo (DevTools > Application > IndexedDB / Storage) y que ese
 * almacenamiento no mezcle datos entre usuarios.
 *
 * Lo que SÍ se automatiza aquí (lectura de código fuente):
 *   - Que la base "oficiosya" declare los tres almacenes esperados: cache
 *     (instantáneas), cola (acciones pendientes) y meta (datos internos).
 *   - Que exista una rutina de limpieza total (limpiarTodo) y que se dispare
 *     al cerrar sesión o al detectar que el dispositivo tenía datos de otro
 *     usuario, para que no se mezclen datos entre cuentas.
 *   - Que abrir la base no rompa la app cuando IndexedDB no está disponible
 *     (p. ej. modo privado de Safari).
 *
 * Lo que NO se automatiza, y queda como verificación manual con evidencia
 * (DevTools > Application > IndexedDB > oficiosya), tal como pide la Fase 1:
 *   - Confirmar visualmente que los registros de "cola" y "cache" se crean,
 *     se actualizan y se borran en el navegador real.
 *   - Medir la cuota de almacenamiento usada (Application > Storage) y
 *     confirmar que no se acerca al límite del navegador.
 *   - Repetir la prueba en Chrome, Firefox y Safari (ver CP-PWA-04).
 */
#[Group('CP-PWA-03'), Group('estatica')]
class CpPwa03AlmacenamientoIndexedDbTest extends TestCase
{
    private function contenido(string $relativo): string
    {
        $ruta = resource_path($relativo);

        if (! is_file($ruta)) {
            $this->markTestSkipped("No existe resources/{$relativo}.");
        }

        return (string) file_get_contents($ruta);
    }

    public function test_la_base_indexeddb_declara_los_tres_almacenes_esperados(): void
    {
        $js = $this->contenido('js/offline/db.js');

        foreach (["'cache'", "'cola'", "'meta'"] as $almacen) {
            $this->assertStringContainsString($almacen, $js, "Falta el almacén {$almacen} en db.js.");
        }

        $this->assertMatchesRegularExpression(
            "/createObjectStore\('cola',\s*\{\s*keyPath:\s*'id',\s*autoIncrement:\s*true\s*\}\)/",
            $js,
            'El almacén "cola" debe tener id autoincremental para conservar el orden de envío.'
        );
    }

    public function test_cerrar_sesion_limpia_los_datos_locales_del_dispositivo(): void
    {
        $js = $this->contenido('js/offline/sync.js');

        $this->assertStringContainsString('limpiarDatosLocales', $js, 'Falta la rutina de limpieza de datos locales.');
        $this->assertMatchesRegularExpression(
            '/if\s*\(nuevo\s*===\s*null\)\s*\{[^}]*limpiarDatosLocales/s',
            $js,
            'Al cerrar sesión (usuario = null) debe llamarse limpiarDatosLocales() para no dejar datos de la cuenta anterior.'
        );
        $this->assertMatchesRegularExpression(
            '/meta\.valor\s*!==\s*nuevo\)\s*await\s*limpiarDatosLocales/',
            $js,
            'Si el dispositivo tenía datos de otro usuario, deben limpiarse antes de usar la base.'
        );
    }

    public function test_la_apertura_de_la_base_no_falla_si_indexeddb_no_esta_disponible(): void
    {
        $js = $this->contenido('js/offline/db.js');

        $this->assertStringContainsString(
            "typeof indexedDB === 'undefined'",
            $js,
            'db.js debe comprobar que IndexedDB exista antes de usarla (Safari en modo privado puede no tenerla).'
        );
    }
}
