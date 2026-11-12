<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfiles_prestador', function (Blueprint $table) {
            $table->id();

            // Un usuario tiene como máximo un perfil de prestador.
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->text('descripcion')->nullable();
            $table->string('ubicacion_aproximada')->nullable();

            // latitud/longitud: la app las guarda redondeadas (~110 m) y nunca las expone.
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->unsignedSmallInteger('radio_cobertura_km')->default(10);

            // Coincide con PerfilPrestador::ESTADOS.
            $table->enum('estado_verificacion', ['pendiente', 'verificado', 'rechazado'])
                ->default('pendiente')
                ->index();
            $table->text('motivo_rechazo')->nullable();

            $table->boolean('es_pro')->default(false);
            $table->timestamps();

            $table->index(['latitud', 'longitud'], 'perfiles_prestador_lat_lng_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfiles_prestador');
    }
};
