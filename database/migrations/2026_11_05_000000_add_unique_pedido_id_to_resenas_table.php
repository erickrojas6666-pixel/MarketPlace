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
     * Se hace en una migración nueva (y no editando la de create_resenas_table)
     * porque esa ya pudo ejecutarse en bases existentes.
     */
    public function up(): void
    {
        // Si ya hay duplicados, se conserva la reseña más antigua de cada pedido;
        // de lo contrario el índice único fallaría.
        DB::statement(
            'DELETE r1 FROM resenas r1
             INNER JOIN resenas r2
                ON r1.pedido_id = r2.pedido_id AND r1.id > r2.id'
        );

        Schema::table('resenas', function (Blueprint $table) {
            $table->unique('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::table('resenas', function (Blueprint $table) {
            $table->dropUnique(['pedido_id']);
        });
    }
};
