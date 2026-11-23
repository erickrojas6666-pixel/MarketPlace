<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            // No se puede borrar un pago que ya tiene movimientos.
            $table->foreignId('pago_id')->constrained('pagos')->restrictOnDelete();
            $table->enum('tipo', [
                'cobro_cliente',
                'liberacion_prestador',
                'comision_plataforma',
                'reembolso_cliente',
            ]);
            $table->decimal('monto', 10, 2);
            $table->timestamp('creada_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacciones');
    }
};
