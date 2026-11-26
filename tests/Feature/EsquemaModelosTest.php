<?php

namespace Tests\Feature;

use App\Models\Mensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Evita que un modelo declare columnas que la base de datos no tiene (pasó con Mensaje:
 * el modelo hablaba de autor_id/cuerpo y la tabla tenía emisor_id/contenido).
 *
 * Agrega aquí cada modelo cuando su $fillable esté alineado con las migraciones.
 */
class EsquemaModelosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_fillable_de_cada_modelo_existe_como_columna(): void
    {
        foreach ([Mensaje::class] as $clase) {
            $modelo = new $clase;

            $this->assertTrue(
                Schema::hasColumns($modelo->getTable(), $modelo->getFillable()),
                "{$clase}: hay columnas en \$fillable que no existen en la tabla {$modelo->getTable()}."
            );
        }
    }

    public function test_mensajes_no_conserva_el_booleano_leido_y_si_leido_at(): void
    {
        $this->assertTrue(Schema::hasColumns('mensajes', ['emisor_id', 'contenido', 'adjunto_ruta', 'adjunto_hash', 'leido_at']));
        $this->assertFalse(Schema::hasColumn('mensajes', 'leido'));
    }
}
