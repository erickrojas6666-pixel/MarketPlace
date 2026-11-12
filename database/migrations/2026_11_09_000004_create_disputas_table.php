<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('abierta_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('mediador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo');
            $table->enum('estado', [
                'abierta',
                'en_revision',
                'resuelta_favor_cliente',
                'resuelta_favor_prestador',
                'cancelada',
            ])->default('abierta');
            $table->text('resolucion_notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputas');
    }
};
