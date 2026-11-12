<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();

            // Mismos valores que Pedido::ESTADOS.
            $table->enum('estado', [
                'pendiente',
                'cotizado',
                'rechazado',
                'aceptado',
                'pagado',
                'en_progreso',
                'completado',
                'confirmado',
                'en_disputa',
                'cancelado',
            ])->default('pendiente')->index();

            $table->text('direccion_servicio');
            $table->dateTime('fecha_programada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
