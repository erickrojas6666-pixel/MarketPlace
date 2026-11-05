<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pedidos')) {
            return;
        }

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();

            // Coincide con Pedido::ESTADOS.
            $table->enum('estado', [
                'pendiente',
                'rechazado',
                'aceptado',
                'en_progreso',
                'completado',
                'cancelado',
            ])->default('pendiente')->index();

            $table->string('direccion_servicio');
            $table->dateTime('fecha_programada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
