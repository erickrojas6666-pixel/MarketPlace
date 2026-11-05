<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('perfiles_prestador')) {
            return;
        }

        Schema::create('perfiles_prestador', function (Blueprint $table) {
            $table->id();

            // Un usuario tiene como máximo un perfil de prestador.
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->text('descripcion')->nullable();
            $table->string('ubicacion_aproximada')->nullable();

            // Coincide con PerfilPrestador::ESTADOS.
            $table->enum('estado_verificacion', ['pendiente', 'verificado', 'rechazado'])
                ->default('pendiente')
                ->index();
            $table->text('motivo_rechazo')->nullable();

            $table->boolean('es_pro')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfiles_prestador');
    }
};
