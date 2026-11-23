<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Prepara la BD de cada prueba, sea cual sea el motor:
 *  - SQLite (memoria): esquema nuevo con migrate:fresh en cada prueba.
 *  - MySQL: usa una transaccion que se revierte al terminar (la BD debe terminar en _test).
 *
 * Laravel ejecuta automaticamente setUp<NombreDelTrait>() al arrancar cada prueba.
 */
trait PreparaBaseDeDatos
{
    protected function setUpPreparaBaseDeDatos(): void
    {
        $conexion = DB::connection();

        if ($conexion->getDriverName() === 'sqlite') {
            $this->artisan('migrate:fresh');

            return;
        }

        $base = (string) $conexion->getDatabaseName();

        if (! str_ends_with($base, '_test')) {
            $this->fail("Por seguridad, las pruebas solo corren contra una BD cuyo nombre termine en _test (actual: {$base}).");
        }

        DB::beginTransaction();
        $this->beforeApplicationDestroyed(fn () => DB::rollBack());
    }
}
