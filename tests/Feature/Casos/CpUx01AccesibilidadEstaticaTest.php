<?php

namespace Tests\Feature\Casos;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * CP-UX-01 · Mensajes sin conexión y accesibilidad de la solicitud (REQ-08 y REQ-03).
 *
 * El caso es de navegador (Chrome, DevTools, Lighthouse, teclado, Android) y NO se puede
 * automatizar con PHPUnit. Estas pruebas solo revisan el CÓDIGO FUENTE para confirmar
 * que los textos y atributos que el caso espera existen. NO sustituyen la ejecución manual.
 */
#[Group('pdf'), Group('CP-UX-01'), Group('estatica')]
class CpUx01AccesibilidadEstaticaTest extends TestCase
{
    /** @return array<string,string> ruta => contenido */
    private function fuentes(): array
    {
        $dir = resource_path('js');

        if (! is_dir($dir)) {
            $this->markTestSkipped('No existe resources/js en este proyecto.');
        }

        $salida = [];
        $it     = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $archivo) {
            if (in_array($archivo->getExtension(), ['vue', 'js'], true)) {
                $salida[$archivo->getPathname()] = (string) file_get_contents($archivo->getPathname());
            }
        }

        return $salida;
    }

    private function archivosCon(string $texto): array
    {
        return array_keys(array_filter($this->fuentes(), fn ($c) => str_contains($c, $texto)));
    }

    public function test_la_banda_sin_conexion_existe_y_es_accesible(): void
    {
        $candidatos = $this->archivosCon('Sin conexión');
        $this->assertNotEmpty($candidatos, 'No se encontró el texto «Sin conexión» en resources/js.');

        $accesible = array_filter($candidatos, function ($ruta) {
            $c = file_get_contents($ruta);

            return str_contains($c, 'role="status"') && str_contains($c, 'aria-live="polite"');
        });

        $this->assertNotEmpty($accesible, 'La banda sin conexión debe tener role="status" y aria-live="polite".');
    }

    public function test_el_texto_largo_de_la_banda_coincide_con_el_caso(): void
    {
        $this->assertNotEmpty(
            $this->archivosCon('Algunas funciones no estarán disponibles hasta que vuelvas a estar en línea'),
            'Falta el texto de la banda definido en CP-UX-01 (paso 3).'
        );
    }

    public function test_el_formulario_avisa_la_direccion_vacia(): void
    {
        $this->assertNotEmpty($this->archivosCon('Escribe la dirección del servicio'), 'Falta el mensaje del paso 4.');
    }

    public function test_el_aviso_de_guardado_sin_conexion_existe(): void
    {
        $this->assertNotEmpty($this->archivosCon('guardamos tu solicitud'), 'Falta el aviso del paso 5.');
    }

    public function test_el_contador_de_acciones_pendientes_existe(): void
    {
        $this->assertNotEmpty($this->archivosCon('de enviar'), 'Falta el texto «… pendiente de enviar» del paso 5.');
    }

    /**
     * Observación 6 del documento: las etiquetas del formulario de Dashboard.vue deben
     * asociarse a su campo (atributo for, o envolver al campo) para tener nombre accesible.
     */
    #[Group('observacion')]
    public function test_cada_label_del_dashboard_queda_asociado_a_su_campo(): void
    {
        $ruta = null;
        foreach (array_keys($this->fuentes()) as $candidata) {
            if (str_ends_with(str_replace('\\', '/', $candidata), 'Pages/Dashboard.vue')) {
                $ruta = $candidata;
            }
        }

        if ($ruta === null) {
            $this->markTestSkipped('No se encontró Pages/Dashboard.vue.');
        }

        $contenido = (string) file_get_contents($ruta);
        preg_match_all('/<label\b([^>]*)>(.*?)<\/label>/s', $contenido, $coincidencias, PREG_SET_ORDER);

        $this->assertNotEmpty($coincidencias, 'Dashboard.vue no tiene etiquetas <label>.');

        $sueltas = [];
        foreach ($coincidencias as $c) {
            $conFor    = (bool) preg_match('/(^|\s):?for\s*=/', $c[1]);
            $envuelve  = (bool) preg_match('/<(input|select|textarea)\b/i', $c[2]);

            if (! $conFor && ! $envuelve) {
                $sueltas[] = trim(preg_replace('/\s+/', ' ', strip_tags($c[2])));
            }
        }

        $this->assertSame([], $sueltas, 'Etiquetas sin asociar a un campo: '.implode(' | ', $sueltas));
    }
}
