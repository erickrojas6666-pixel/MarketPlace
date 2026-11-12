<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Paquetes del servicio (básico / completo / avanzado). */
    public function up(): void
    {
        Schema::create('niveles_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->enum('nivel', ['basico', 'completo', 'avanzado']);
            $table->decimal('precio', 10, 2);
            $table->unsignedInteger('duracion_min')->default(60);
            $table->text('detalles')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveles_servicio');
    }
};
