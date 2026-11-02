<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('resenas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('pedido_id')->constrained()->onDelete('cascade');
        $table->foreignId('cliente_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('servicio_id')->constrained()->onDelete('cascade');
        $table->tinyInteger('calificacion')->comment('Puntuación de 1 a 5');
        $table->text('comentario')->nullable();
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resenas');
    }
};
