<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RF-05/06: carga de documentos y estado "en revisión".
     *
     * No se toca el ENUM de estado_verificacion (cambiarlo es distinto en MySQL y Postgres):
     * "en revisión" = pendiente + verificacion_enviada_at con valor.
     */
    public function up(): void
    {
        Schema::table('perfiles_prestador', function (Blueprint $table) {
            $table->timestamp('verificacion_enviada_at')->nullable();
        });

        Schema::table('documentos_verificacion', function (Blueprint $table) {
            $table->string('motivo', 255)->nullable();      // motivo si el revisor lo rechaza
            $table->timestamp('purgado_at')->nullable();    // retención: archivo ya eliminado
        });
    }

    public function down(): void
    {
        Schema::table('documentos_verificacion', function (Blueprint $table) {
            $table->dropColumn(['motivo', 'purgado_at']);
        });

        Schema::table('perfiles_prestador', function (Blueprint $table) {
            $table->dropColumn('verificacion_enviada_at');
        });
    }
};
