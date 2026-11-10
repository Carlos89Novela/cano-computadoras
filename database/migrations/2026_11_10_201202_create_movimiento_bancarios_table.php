<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cuenta_bancaria_id')->constrained('cuentas_bancarias')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('Usuario que asienta el movimiento')->constrained('users')->restrictOnDelete();

            $table->enum('tipo', ['ingreso', 'egreso', 'transferencia'])->index();
            $table->decimal('monto', 14, 2);
            $table->decimal('saldo_anterior', 14, 2);
            $table->decimal('saldo_posterior', 14, 2);

            $table->date('fecha_operacion');
            $table->string('referencia', 100)->nullable()->comment('Folio SPEI, cheque o ticket');
            $table->string('concepto', 255);

            // Vinculaciones opcionales para trazabilidad contable
            $table->nullableMorphs('origen'); // venta, pago CXP, corte de caja
            $table->foreignId('cuenta_destino_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();

            $table->boolean('conciliado')->default(false)->index();
            $table->dateTime('conciliado_at')->nullable();
            $table->timestamps();

            $table->index(['cuenta_bancaria_id', 'fecha_operacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_bancarios');
    }
};