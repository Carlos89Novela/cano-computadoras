<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabla de Encabezado de Transferencias
        Schema::create('transferencias_inventario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();

            $table->foreignId('almacen_origen_id')
                ->constrained('almacenes')
                ->restrictOnDelete();

            $table->foreignId('almacen_destino_id')
                ->constrained('almacenes')
                ->restrictOnDelete();

            $table->uuid('grupo_id')->index();
            $table->string('folio', 50)->unique();
            $table->string('motivo', 1000);
            $table->unsignedInteger('total_lineas');
            $table->decimal('costo_total', 14, 4)->default(0);

            $table->foreignId('creado_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('transferido_at')->index();
            $table->timestamps();

            $table->index(['empresa_id', 'almacen_origen_id'], 'transf_emp_alm_orig_idx');
            $table->index(['empresa_id', 'almacen_destino_id'], 'transf_emp_alm_dest_idx');
        });

        // 2. Tabla de Detalle de Líneas de Transferencia
        Schema::create('transferencia_inventario_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transferencia_inventario_id')
                ->constrained('transferencias_inventario', indexName: 'transf_det_header_fk')
                ->cascadeOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos', indexName: 'transf_det_prod_fk')
                ->restrictOnDelete();

            $table->decimal('cantidad', 12, 4);
            $table->decimal('costo_unitario', 12, 4);
            $table->decimal('subtotal', 14, 4);
            $table->timestamps();

            $table->index(['transferencia_inventario_id', 'producto_id'], 'transf_det_prod_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transferencia_inventario_detalles');
        Schema::dropIfExists('transferencias_inventario');
    }
};
