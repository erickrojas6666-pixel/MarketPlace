<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat por pedido (RF-22). La tabla se creó en 2026_11_09_000006 y ningún código la
     * usaba todavía, así que se ajusta sin migrar datos:
     *  - adjunto_ruta / adjunto_hash: imagen opcional (disco privado, mismo trato que la evidencia).
     *  - leido_at: reemplaza al booleano `leido` y dice CUÁNDO se leyó (sirve para "no leídos").
     *  - índice (pedido_id, id): paginar "mensajes después del id N" sin recorrer toda la tabla.
     */
    public function up(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->string('adjunto_ruta')->nullable()->after('contenido');
            $table->string('adjunto_hash', 64)->nullable()->after('adjunto_ruta');
            $table->timestamp('leido_at')->nullable()->after('adjunto_hash');

            $table->index(['pedido_id', 'id'], 'idx_mensajes_pedido_id');
        });

        Schema::table('mensajes', function (Blueprint $table) {
            $table->dropColumn('leido');
        });
    }

    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->boolean('leido')->default(false)->after('contenido');
        });

        Schema::table('mensajes', function (Blueprint $table) {
            $table->dropIndex('idx_mensajes_pedido_id');
            $table->dropColumn(['adjunto_ruta', 'adjunto_hash', 'leido_at']);
        });
    }
};
