<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidencia fotográfica de un pedido (antes / durante / después).
     * Son fotos del interior de un domicilio: viven en el disco PRIVADO y solo
     * se leen con URL temporal firmada, tras autorizar cada acceso.
     */
    public function up(): void
    {
        Schema::create('fotos_evidencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();

            // Si la cuenta se elimina, la evidencia del pedido se conserva (disputas, auditoría).
            $table->foreignId('subida_por')->nullable()->constrained('users')->nullOnDelete();

            $table->string('fase', 12)->default('durante'); // antes | durante | despues
            $table->string('ruta');
            $table->string('hash_sha256', 64);
            $table->string('pie', 160)->nullable();
            $table->timestamps();

            $table->index('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_evidencia');
    }
};
