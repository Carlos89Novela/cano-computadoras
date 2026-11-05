<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ---------------------------------------------------------
    // CREAR EXISTENCIAS POR ALMACÉN
    // Mantiene el saldo consolidado de cada producto dentro
    // de un almacén específico de la empresa.
    // ---------------------------------------------------------

    public function up(): void
    {
        Schema::create(
            'existencias_almacen',
            function (Blueprint $table): void {
                $table->id();

                // ---------------------------------------------
                // EMPRESA
                // Mantiene el aislamiento multiempresa y
                // facilita las consultas de inventario.
                // ---------------------------------------------

                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // ALMACÉN
                // Identifica el espacio físico o virtual en
                // el que se encuentra la existencia.
                // ---------------------------------------------

                $table->foreignId('almacen_id')
                    ->constrained('almacenes')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // PRODUCTO
                // Identifica el artículo cuyo saldo se está
                // controlando dentro del almacén.
                // ---------------------------------------------

                $table->foreignId('producto_id')
                    ->constrained('productos')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // CANTIDAD ACTUAL
                // Utiliza cuatro decimales para permitir tanto
                // piezas como unidades fraccionarias.
                // ---------------------------------------------

                $table->decimal(
                    'cantidad',
                    18,
                    4
                )->default(0);

                // ---------------------------------------------
                // CANTIDAD RESERVADA
                // Representa unidades comprometidas que aún
                // permanecen físicamente en el almacén.
                // ---------------------------------------------

                $table->decimal(
                    'cantidad_reservada',
                    18,
                    4
                )->default(0);

                // ---------------------------------------------
                // COSTO PROMEDIO
                // Mantiene el costo promedio ponderado del
                // producto en este almacén.
                // ---------------------------------------------

                $table->decimal(
                    'costo_promedio',
                    18,
                    4
                )->default(0);

                // ---------------------------------------------
                // ÚLTIMO USUARIO
                // Registra quién realizó la modificación más
                // reciente del saldo consolidado.
                // ---------------------------------------------

                $table->foreignId(
                    'actualizado_por_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                // ---------------------------------------------
                // EXISTENCIA ÚNICA
                // Cada producto solo puede tener un registro
                // de saldo dentro de cada almacén.
                // ---------------------------------------------

                $table->unique(
                    [
                        'empresa_id',
                        'almacen_id',
                        'producto_id',
                    ],
                    'existencias_empresa_almacen_producto_unique'
                );

                // ---------------------------------------------
                // CONSULTA POR PRODUCTO
                // Agiliza la consulta del inventario total de
                // un producto dentro de una empresa.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'producto_id',
                    ],
                    'existencias_empresa_producto_index'
                );

                // ---------------------------------------------
                // CONSULTA POR ALMACÉN
                // Facilita listados completos de existencias
                // pertenecientes a un almacén.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'almacen_id',
                    ],
                    'existencias_empresa_almacen_index'
                );
            }
        );
    }

    // ---------------------------------------------------------
    // REVERTIR EXISTENCIAS
    // Elimina la tabla durante una reversión de migraciones.
    // ---------------------------------------------------------

    public function down(): void
    {
        Schema::dropIfExists(
            'existencias_almacen'
        );
    }
};
