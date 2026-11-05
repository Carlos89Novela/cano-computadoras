<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ---------------------------------------------------------
    // CREAR DETALLES DE RECEPCIÓN
    // Conserva cada producto, cantidad y costo incluido dentro
    // de una recepción de inventario.
    // ---------------------------------------------------------

    public function up(): void
    {
        Schema::create(
            'recepcion_inventario_detalles',
            function (Blueprint $table): void {
                $table->id();

                // ---------------------------------------------
                // RECEPCIÓN
                // Relaciona la línea con el encabezado general
                // de la operación.
                // ---------------------------------------------

                $table->foreignId(
                    'recepcion_inventario_id'
                )
                    ->constrained(
                        'recepciones_inventario'
                    )
                    ->cascadeOnDelete();

                // ---------------------------------------------
                // PRODUCTO
                // Identifica el producto recibido en esta línea.
                // ---------------------------------------------

                $table->foreignId('producto_id')
                    ->constrained('productos')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // CANTIDAD
                // Mantiene la cantidad recibida con precisión
                // de hasta cuatro posiciones decimales.
                // ---------------------------------------------

                $table->decimal(
                    'cantidad',
                    18,
                    4
                );

                // ---------------------------------------------
                // COSTO UNITARIO
                // Conserva el costo de adquisición utilizado
                // para actualizar el costo promedio.
                // ---------------------------------------------

                $table->decimal(
                    'costo_unitario',
                    18,
                    4
                );

                // ---------------------------------------------
                // SUBTOTAL
                // Guarda el resultado de multiplicar cantidad
                // por costo unitario.
                // ---------------------------------------------

                $table->decimal(
                    'subtotal',
                    18,
                    4
                );

                // ---------------------------------------------
                // MARCAS TÉCNICAS
                // Conservan cuándo se creó y actualizó la línea.
                // ---------------------------------------------

                $table->timestamps();

                // ---------------------------------------------
                // PRODUCTO ÚNICO POR RECEPCIÓN
                // Evita duplicar un producto dentro del mismo
                // documento. Si vuelve a escanearse, la interfaz
                // aumentará la cantidad de la línea existente.
                // ---------------------------------------------

                $table->unique(
                    [
                        'recepcion_inventario_id',
                        'producto_id',
                    ],
                    'recepcion_detalle_producto_unique'
                );

                // ---------------------------------------------
                // CONSULTA POR PRODUCTO
                // Facilita recuperar todas las recepciones
                // históricas de un producto.
                // ---------------------------------------------

                $table->index(
                    [
                        'producto_id',
                        'created_at',
                    ],
                    'recepcion_detalles_producto_fecha_index'
                );
            }
        );
    }

    // ---------------------------------------------------------
    // REVERTIR DETALLES DE RECEPCIÓN
    // Elimina primero las líneas para permitir posteriormente
    // la eliminación del encabezado.
    // ---------------------------------------------------------

    public function down(): void
    {
        Schema::dropIfExists(
            'recepcion_inventario_detalles'
        );
    }
};
