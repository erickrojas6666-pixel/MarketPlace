<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubicación del prestador para poder filtrar el catálogo por cercanía.
     *
     * latitud/longitud: decimal(10,7) da ~1 cm de precisión; la app las guarda
     * redondeadas a 3 decimales (~110 m) para no revelar el domicilio exacto.
     * radio_cobertura_km: hasta dónde está dispuesto a desplazarse el prestador.
     */
    public function up(): void
    {
        Schema::table('perfiles_prestador', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable()->after('ubicacion_aproximada');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            $table->unsignedSmallInteger('radio_cobertura_km')->default(10)->after('longitud');

            $table->index(['latitud', 'longitud'], 'perfiles_prestador_lat_lng_index');
        });
    }

    public function down(): void
    {
        Schema::table('perfiles_prestador', function (Blueprint $table) {
            $table->dropIndex('perfiles_prestador_lat_lng_index');
            $table->dropColumn(['latitud', 'longitud', 'radio_cobertura_km']);
        });
    }
};
