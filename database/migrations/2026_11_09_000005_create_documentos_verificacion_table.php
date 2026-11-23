<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_verificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_id')->constrained('perfiles_prestador')->cascadeOnDelete();
            $table->enum('tipo', [
                'ine_frente',
                'ine_reverso',
                'comprobante_domicilio',
                'referencia_personal',
                'antecedentes',
            ]);
            $table->text('ruta_cifrada');
            $table->string('hash_sha256', 64);
            $table->enum('estado', ['pendiente', 'valido', 'rechazado'])->default('pendiente');
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->date('vence_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_verificacion');
    }
};
