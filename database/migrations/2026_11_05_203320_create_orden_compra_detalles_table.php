<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();

            $table->decimal('cantidad', 12, 4);
            $table->decimal('cantidad_recibida', 12, 4)->default(0);

            $table->decimal('costo_unitario', 14, 4);
            $table->decimal('descuento_unitario', 14, 4)->default(0);
            $table->decimal('tasa_iva', 5, 4)->default(0.1600);
            $table->decimal('monto_iva_unitario', 14, 4)->default(0);

            $table->decimal('subtotal', 14, 2);
            $table->decimal('total', 14, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_compra_detalles');
    }
};
