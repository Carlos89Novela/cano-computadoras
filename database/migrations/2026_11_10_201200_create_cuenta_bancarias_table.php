<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();

            $table->string('nombre_cuenta', 100)->comment('Ej. BBVA Operativa, Caja Chica');
            $table->string('banco', 60)->comment('BBVA, Santander, Banorte, Efectivo');
            $table->string('numero_cuenta', 30)->nullable();
            $table->string('clabe', 18)->nullable();
            $table->string('moneda', 3)->default('MXN');

            $table->decimal('saldo_inicial', 14, 2)->default(0.00);
            $table->decimal('saldo_actual', 14, 2)->default(0.00);

            $table->boolean('activa')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};