<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas_cobertura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_prestador_id')->constrained('perfiles_prestador')->cascadeOnDelete();
            $table->string('ciudad');
            $table->string('zona')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas_cobertura');
    }
};
