<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_por_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('recepcion_inventario_id')->nullable()->constrained('recepciones_inventario')->nullOnDelete();
            $table->foreignId('orden_compra_id')->nullable()->constrained('ordenes_compra')->nullOnDelete();
            
            $table->uuid('uuid_sat')->nullable()->unique();
            $table->string('serie', 20)->nullable();
            $table->string('folio', 50)->nullable();
            $table->string('xml_path')->nullable();
            $table->dateTime('fecha_emision');
            $table->date('fecha_vencimiento');
            $table->decimal('subtotal', 14, 4);
            $table->decimal('iva_total', 14, 4)->default(0);
            $table->decimal('total', 14, 4);
            $table->decimal('monto_pagado', 14, 4)->default(0);
            $table->decimal('saldo_pendiente', 14, 4);
            
            $table->enum('estado', ['pendiente', 'parcial', 'pagada', 'cancelada'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->foreignId('registrado_por_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'estado', 'fecha_vencimiento'], 'idx_cxp_filtro_vencimiento');
        });

        Schema::create('cuenta_por_pagar_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_por_pagar_id')->constrained('cuentas_por_pagar')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha_pago');
            $table->decimal('monto', 14, 4);
            $table->string('forma_pago_sat', 10)->default('03'); // 03: Transferencia
            $table->string('referencia_bancaria', 100)->nullable();
            $table->string('comprobante_path')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuenta_por_pagar_pagos');
        Schema::dropIfExists('cuentas_por_pagar');
    }
};