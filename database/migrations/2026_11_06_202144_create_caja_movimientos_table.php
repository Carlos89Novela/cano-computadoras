<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caja_movimientos')) {
            Schema::create('caja_movimientos', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('caja_turno_id')->constrained('caja_turnos')->cascadeOnDelete();
                $table->foreignId('user_id')->comment('Usuario que realiza el movimiento')->constrained('users')->restrictOnDelete();
                $table->foreignId('autorizado_por_id')->nullable()->comment('Supervisor que aprueba')->constrained('users')->nullOnDelete();

                $table->enum('tipo', ['ingreso', 'retiro'])->index();
                $table->string('concepto', 100);
                $table->decimal('monto', 12, 2);
                $table->text('motivo');
                $table->string('comprobante_numero', 50)->nullable();
                $table->timestamp('realizado_at')->index();
                $table->timestamps();

                $table->index(['empresa_id', 'sucursal_id', 'caja_turno_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_movimientos');
    }
};
