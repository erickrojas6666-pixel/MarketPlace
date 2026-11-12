<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resenas', function (Blueprint $table) {
            $table->id();

            // Una sola reseña por pedido.
            $table->foreignId('pedido_id')->unique()->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();

            $table->unsignedTinyInteger('calificacion')->comment('Puntuación de 1 a 5');
            $table->text('comentario')->nullable();
            $table->timestamps();
        });

        // Sintaxis válida en PostgreSQL y MySQL 8.
        DB::statement('ALTER TABLE resenas ADD CONSTRAINT resenas_calificacion_rango CHECK (calificacion BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('resenas');
    }
};
