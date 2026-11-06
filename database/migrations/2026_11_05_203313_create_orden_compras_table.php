<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('autorizado_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('folio', 50)->unique();
            $table->enum('estado', [
                'borrador',
                'solicitada',
                'autorizada',
                'recibida_parcial',
                'recibida_total',
                'cancelada',
            ])->default('borrador')->index();

            $table->date('fecha_emision');
            $table->date('fecha_entrega_esperada')->nullable();
            $table->timestamp('autorizada_at')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuento_total', 14, 2)->default(0);
            $table->decimal('iva_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->string('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 10, 4)->default(1.0000);

            $table->text('notas')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};
