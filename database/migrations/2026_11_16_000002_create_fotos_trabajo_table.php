<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Portafolio público: muestra de trabajos realizados de cada servicio (RF-10, RF-19). */
    public function up(): void
    {
        Schema::create('fotos_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();

            // Ruta de la imagen principal en el disco público; la miniatura se deriva.
            $table->string('ruta');
            $table->string('hash_sha256', 64);
            $table->string('pie', 160)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['servicio_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_trabajo');
    }
};
