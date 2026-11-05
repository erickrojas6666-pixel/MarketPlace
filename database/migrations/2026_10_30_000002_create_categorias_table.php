<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categorias')) {
            return;
        }

        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // El modelo la genera con Str::slug() al crear (ver Categoria::booted).
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
