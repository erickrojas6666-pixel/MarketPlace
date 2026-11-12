<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // En PostgreSQL el tipo geography viene de PostGIS.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        }

        Schema::create('direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('calle_cifrada');
            $table->text('referencias_cifradas')->nullable();
            $table->string('cp', 10);
            $table->string('colonia')->nullable();
            $table->string('municipio')->nullable();
            $table->string('estado')->nullable();
            $table->geography('ubicacion_exacta', 'point', 4326);
            $table->timestamps();

            $table->spatialIndex('ubicacion_exacta', 'idx_direcciones_ubicacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direcciones');
    }
};
