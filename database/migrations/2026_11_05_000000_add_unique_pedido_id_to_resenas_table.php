<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una sola reseña por pedido.
     */
    public function up(): void
    {
        // 1. Limpiar duplicados si los hubiera
        DB::statement(
            'DELETE FROM resenas WHERE id IN (
                SELECT id FROM (
                    SELECT r1.id FROM resenas r1
                    INNER JOIN resenas r2
                    ON r1.pedido_id = r2.pedido_id AND r1.id > r2.id
                ) AS duplicadas
            )'
        );

        // 2. Agregar el índice único solo si no existe
        Schema::table('resenas', function (Blueprint $table) {
            // MySQL crea los índices únicos con el nombre tabla_columna_unique por defecto
            $indexName = 'resenas_pedido_id_unique';

            // Verificamos si ya existe para evitar el error 1061
            if (!collect(Schema::getIndexes('resenas'))->pluck('name')->contains($indexName)) {
                $table->unique('pedido_id', $indexName);
            }
        });
    }

    public function down(): void
    {
        Schema::table('resenas', function (Blueprint $table) {
            $table->dropUnique('resenas_pedido_id_unique');
        });
    }
};
