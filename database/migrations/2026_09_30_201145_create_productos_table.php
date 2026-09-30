<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ---------------------------------------------------------
    // CREACIÓN DE LA TABLA
    // Registra el catálogo general de productos de una empresa.
    // Las cantidades por almacén se crearán en otra tabla.
    // ---------------------------------------------------------

    public function up(): void
    {
        Schema::create(
            'productos',
            function (Blueprint $table): void {
                $table->id();

                // -------------------------------------------------
                // EMPRESA Y CLASIFICACIÓN
                // Cada producto pertenece a una empresa y categoría.
                // El proveedor principal es opcional.
                // -------------------------------------------------

                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->restrictOnDelete();

                $table->foreignId('categoria_producto_id')
                    ->constrained('categorias_producto')
                    ->restrictOnDelete();

                $table->foreignId('proveedor_principal_id')
                    ->nullable()
                    ->constrained('proveedores')
                    ->nullOnDelete();

                // -------------------------------------------------
                // IDENTIFICADORES
                // SKU identifica internamente al producto.
                // El código de barras puede ser EAN, UPC u otro.
                // -------------------------------------------------

                $table->string('sku', 80);

                $table->string('codigo_barras', 100)
                    ->nullable();

                // -------------------------------------------------
                // INFORMACIÓN COMERCIAL
                // Datos compartidos entre todas las sucursales.
                // -------------------------------------------------

                $table->string('nombre', 200);

                $table->text('descripcion')
                    ->nullable();

                $table->string('marca', 120)
                    ->nullable();

                $table->string('modelo', 120)
                    ->nullable();

                $table->string('unidad_medida', 30)
                    ->default('pieza');

                // -------------------------------------------------
                // REFERENCIAS DE PRECIO
                // El costo y precio base sirven como referencias.
                // Las listas de precios se implementarán aparte.
                // -------------------------------------------------

                $table->decimal(
                    'costo_referencia',
                    12,
                    2
                )->nullable();

                $table->decimal(
                    'precio_base',
                    12,
                    2
                )->nullable();

                // -------------------------------------------------
                // CONTROL DEL CATÁLOGO
                // Desactivar conserva historial y relaciones.
                // -------------------------------------------------

                $table->boolean('activo')
                    ->default(true)
                    ->index();

                $table->foreignId('creado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('actualizado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('desactivado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('desactivado_at')
                    ->nullable();

                $table->text('motivo_desactivacion')
                    ->nullable();

                $table->timestamps();

                // -------------------------------------------------
                // RESTRICCIONES E ÍNDICES
                // SKU y código de barras son únicos por empresa.
                // -------------------------------------------------

                $table->unique(
                    [
                        'empresa_id',
                        'sku',
                    ],
                    'productos_empresa_sku_unique'
                );

                $table->unique(
                    [
                        'empresa_id',
                        'codigo_barras',
                    ],
                    'productos_empresa_codigo_barras_unique'
                );

                $table->index(
                    [
                        'empresa_id',
                        'categoria_producto_id',
                        'activo',
                    ],
                    'productos_empresa_categoria_activo_index'
                );

                $table->index(
                    [
                        'empresa_id',
                        'proveedor_principal_id',
                    ],
                    'productos_empresa_proveedor_index'
                );

                $table->index(
                    [
                        'empresa_id',
                        'nombre',
                    ],
                    'productos_empresa_nombre_index'
                );
            }
        );
    }

    // ---------------------------------------------------------
    // REVERSIÓN
    // Elimina la tabla cuando se revierte la migración.
    // ---------------------------------------------------------

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
