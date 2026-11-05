<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('servicios')) {
            return;
        }

        Schema::create('servicios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('perfil_prestador_id')
                ->constrained('perfiles_prestador')
                ->cascadeOnDelete();

            // No se puede borrar una categoría que todavía tiene servicios.
            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->string('titulo');
            $table->text('descripcion');
            $table->decimal('precio_base', 10, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Consulta del catálogo: activos, por categoría.
            $table->index(['activo', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
