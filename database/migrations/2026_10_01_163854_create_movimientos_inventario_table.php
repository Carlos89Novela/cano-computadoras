<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ---------------------------------------------------------
    // CREAR MOVIMIENTOS DE INVENTARIO
    // Conserva la bitácora inmutable de toda modificación en
    // las existencias de productos por almacén.
    // ---------------------------------------------------------

    public function up(): void
    {
        Schema::create(
            'movimientos_inventario',
            function (Blueprint $table): void {
                $table->id();

                // ---------------------------------------------
                // CONTEXTO MULTIEMPRESA
                // Identifica la empresa propietaria de la
                // operación de inventario.
                // ---------------------------------------------

                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // ALMACÉN AFECTADO
                // Identifica el almacén cuya existencia fue
                // modificada por el movimiento.
                // ---------------------------------------------

                $table->foreignId('almacen_id')
                    ->constrained('almacenes')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // PRODUCTO AFECTADO
                // Identifica el producto involucrado en el
                // movimiento de inventario.
                // ---------------------------------------------

                $table->foreignId('producto_id')
                    ->constrained('productos')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // GRUPO DE MOVIMIENTOS
                // Permite relacionar varios movimientos que
                // pertenecen a una misma operación.
                // ---------------------------------------------

                $table->uuid('grupo_id')
                    ->index();

                // ---------------------------------------------
                // FOLIO
                // Identificador legible de la operación dentro
                // de la empresa.
                // ---------------------------------------------

                $table->string(
                    'folio',
                    60
                );

                // ---------------------------------------------
                // TIPO DE MOVIMIENTO
                // Distingue entradas, salidas, ajustes,
                // consumos, devoluciones y transferencias.
                // ---------------------------------------------

                $table->string(
                    'tipo',
                    40
                )->index();

                // ---------------------------------------------
                // CANTIDAD DEL MOVIMIENTO
                // Mantiene una cantidad positiva. El tipo del
                // movimiento determina si suma o resta saldo.
                // ---------------------------------------------

                $table->decimal(
                    'cantidad',
                    18,
                    4
                );

                // ---------------------------------------------
                // SALDO ANTERIOR Y NUEVO
                // Conserva la fotografía matemática antes y
                // después de aplicar el movimiento.
                // ---------------------------------------------

                $table->decimal(
                    'existencia_anterior',
                    18,
                    4
                );

                $table->decimal(
                    'existencia_nueva',
                    18,
                    4
                );

                // ---------------------------------------------
                // COSTO UNITARIO
                // Guarda el costo relacionado con el
                // movimiento cuando resulte aplicable.
                // ---------------------------------------------

                $table->decimal(
                    'costo_unitario',
                    18,
                    4
                )->nullable();

                // ---------------------------------------------
                // REFERENCIA POLIMÓRFICA
                // Permite relacionar el movimiento con una
                // compra, orden, ajuste o transferencia.
                // ---------------------------------------------

                $table->string(
                    'referencia_tipo',
                    150
                )->nullable();

                $table->unsignedBigInteger(
                    'referencia_id'
                )->nullable();

                // ---------------------------------------------
                // MOTIVO
                // Conserva la justificación operativa del
                // movimiento de inventario.
                // ---------------------------------------------

                $table->text('motivo');

                // ---------------------------------------------
                // USUARIO RESPONSABLE
                // Registra quién ejecutó la operación.
                // ---------------------------------------------

                $table->foreignId('usuario_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                // ---------------------------------------------
                // FECHA OPERATIVA
                // Separa el momento real del movimiento de la
                // marca técnica de creación del registro.
                // ---------------------------------------------

                $table->timestamp(
                    'ocurrido_at'
                )->index();

                // ---------------------------------------------
                // BITÁCORA INMUTABLE
                // Solo requiere created_at porque estos
                // movimientos no deben actualizarse.
                // ---------------------------------------------

                $table->timestamp(
                    'created_at'
                )->nullable();

                // ---------------------------------------------
                // FOLIO ÚNICO POR EMPRESA
                // Un mismo folio puede existir en empresas
                // diferentes, pero no repetirse en una.
                // ---------------------------------------------

                $table->unique(
                    [
                        'empresa_id',
                        'folio',
                    ],
                    'movimientos_empresa_folio_unique'
                );

                // ---------------------------------------------
                // HISTORIAL POR PRODUCTO Y ALMACÉN
                // Optimiza la consulta cronológica del kardex.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'almacen_id',
                        'producto_id',
                        'ocurrido_at',
                    ],
                    'movimientos_kardex_index'
                );

                // ---------------------------------------------
                // CONSULTA POR REFERENCIA
                // Facilita recuperar los movimientos de una
                // operación externa relacionada.
                // ---------------------------------------------

                $table->index(
                    [
                        'referencia_tipo',
                        'referencia_id',
                    ],
                    'movimientos_referencia_index'
                );

                // ---------------------------------------------
                // CONSULTA POR GRUPO
                // Permite reconstruir transferencias y otras
                // operaciones compuestas.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'grupo_id',
                    ],
                    'movimientos_empresa_grupo_index'
                );
            }
        );
    }

    // ---------------------------------------------------------
    // REVERTIR MOVIMIENTOS
    // Elimina la bitácora durante una reversión controlada.
    // ---------------------------------------------------------

    public function down(): void
    {
        Schema::dropIfExists(
            'movimientos_inventario'
        );
    }
};
