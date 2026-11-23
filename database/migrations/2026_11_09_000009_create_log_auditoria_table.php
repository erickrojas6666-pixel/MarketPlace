<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Registro inmutable (RF-52): solo se inserta. */
    public function up(): void
    {
        Schema::create('log_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion');
            $table->string('entidad');
            $table->unsignedBigInteger('entidad_id');
            $table->json('datos_antes')->nullable();
            $table->json('datos_despues')->nullable();
            $table->string('ip_origen', 45);
            $table->timestamp('creada_en')->useCurrent();

            $table->index(['entidad', 'entidad_id'], 'idx_auditoria_entidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_auditoria');
    }
};
