<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubicación del prestador para poder filtrar el catálogo por cercanía.
     */
    public function up(): void
    {
        Schema::table('perfiles_prestador', function (Blueprint $table) {
            // Agregamos las columnas solo si no existen previamente
            if (!Schema::hasColumn('perfiles_prestador', 'latitud')) {
                $table->decimal('latitud', 10, 7)->nullable()->after('ubicacion_aproximada');
            }

            if (!Schema::hasColumn('perfiles_prestador', 'longitud')) {
                $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            }

            if (!Schema::hasColumn('perfiles_prestador', 'radio_cobertura_km')) {
                $table->unsignedSmallInteger('radio_cobertura_km')->default(10)->after('longitud');
            }

            // Para el índice, verificamos si ya existe para evitar errores
            $indexName = 'perfiles_prestador_lat_lng_index';
            if (!collect(Schema::getIndexes('perfiles_prestador'))->pluck('name')->contains($indexName)) {
                $table->index(['latitud', 'longitud'], $indexName);
            }
        });
    }

    public function down(): void
    {
        Schema::table('perfiles_prestador', function (Blueprint $table) {
            // Eliminamos el índice y las columnas de forma segura
            $table->dropIndex('perfiles_prestador_lat_lng_index');
            $table->dropColumn(['latitud', 'longitud', 'radio_cobertura_km']);
        });
    }
};
