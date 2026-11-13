<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una sola reseña por pedido.
     *
     * En instalaciones nuevas, create_resenas_table ya crea el índice único;
     * esta migración solo actúa en bases antiguas que aún no lo tienen.
     */
    public function up(): void
    {
        if (Schema::hasIndex('resenas', ['pedido_id'], 'unique')) {
            return;
        }

        // Si ya hay duplicados, se conserva la reseña más antigua de cada pedido;
        // de lo contrario el índice único fallaría. Sintaxis válida en MySQL y PostgreSQL.
        DB::statement(
            'DELETE FROM resenas WHERE id IN (
                SELECT id FROM (
                    SELECT r1.id FROM resenas r1
                    INNER JOIN resenas r2
                        ON r1.pedido_id = r2.pedido_id AND r1.id > r2.id
                ) AS duplicadas
            )'
        );

        Schema::table('resenas', function (Blueprint $table) {
            $table->unique('pedido_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('resenas', ['pedido_id'], 'unique')) {
            return;
        }

        Schema::table('resenas', function (Blueprint $table) {
            $table->dropUnique(['pedido_id']);
        });
    }
};
