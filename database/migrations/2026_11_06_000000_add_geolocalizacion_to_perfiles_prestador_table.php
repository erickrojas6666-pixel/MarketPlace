<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubicación del prestador para poder filtrar el catálogo por cercanía.
     *
     * En instalaciones nuevas, create_perfiles_prestador_table ya incluye estas
     * columnas y el índice; aquí solo se agregan en bases antiguas que no los tienen.
     */
    public function up(): void
    {
        $tabla = 'perfiles_prestador';

        Schema::table($tabla, function (Blueprint $table) use ($tabla) {
            if (! Schema::hasColumn($tabla, 'latitud')) {
                $table->decimal('latitud', 10, 7)->nullable()->after('ubicacion_aproximada');
            }
            if (! Schema::hasColumn($tabla, 'longitud')) {
                $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            }
            if (! Schema::hasColumn($tabla, 'radio_cobertura_km')) {
                $table->unsignedSmallInteger('radio_cobertura_km')->default(10)->after('longitud');
            }
            if (! Schema::hasIndex($tabla, 'perfiles_prestador_lat_lng_index')) {
                $table->index(['latitud', 'longitud'], 'perfiles_prestador_lat_lng_index');
            }
        });
    }

    public function down(): void
    {
        $tabla = 'perfiles_prestador';

        Schema::table($tabla, function (Blueprint $table) use ($tabla) {
            if (Schema::hasIndex($tabla, 'perfiles_prestador_lat_lng_index')) {
                $table->dropIndex('perfiles_prestador_lat_lng_index');
            }
            $columnas = array_values(array_filter(
                ['latitud', 'longitud', 'radio_cobertura_km'],
                fn ($c) => Schema::hasColumn($tabla, $c)
            ));
            if ($columnas) {
                $table->dropColumn($columnas);
            }
        });
    }
};
