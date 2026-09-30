<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla de 'almacenes'.
 *
 * Modela los espacios físicos o lógicos de almacenamiento de refacciones y productos:
 * - empresa_id: Clave foránea a la empresa propietaria ('restrictOnDelete').
 * - sucursal_id: Clave foránea opcional a la sucursal física donde se ubica el almacén.
 * - codigo: Identificador alfanumérico único por empresa (ej. ALM-MATRIZ).
 * - nombre: Denominación descriptiva del almacén.
 * - tipo: Clasificación operativa ('principal', 'taller', 'transito', 'merma', etc.).
 * - es_virtual: Bandera para almacenes de tránsito, consignación o virtuales.
 * - permite_existencias: Bandera que controla si el almacén gestiona stock cuantificable.
 * - activo: Bandera booleana de habilitación (baja lógica).
 * - desactivado_por_id / desactivado_at / motivo_desactivacion: Auditoría de cierre de almacén.
 * - Restricciones de unicidad: [empresa_id, codigo].
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para estructurar la tabla de almacenes.
     */
    public function up(): void
    {
        Schema::create('almacenes', function (Blueprint $table): void {
            $table->id();

            // Relaciones con empresa y sucursal
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->restrictOnDelete();

            $table->foreignId('sucursal_id')
                ->nullable()
                ->constrained('sucursales')
                ->restrictOnDelete();

            $table->string('codigo', 40);
            $table->string('nombre', 150);

            // Tipología y características de inventario
            $table->string('tipo', 30)
                ->default('principal')
                ->index();

            $table->boolean('es_virtual')
                ->default(false);

            $table->boolean('permite_existencias')
                ->default(true);

            $table->boolean('activo')
                ->default(true)
                ->index();

            // Trazabilidad de usuarios responsables
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

            $table->timestamp('desactivado_at')->nullable();
            $table->text('motivo_desactivacion')->nullable();

            $table->timestamps();

            // Unicidad del código de almacén en la empresa
            $table->unique(
                ['empresa_id', 'codigo'],
                'almacenes_empresa_codigo_unique'
            );

            // Índice compuesto para consultas de almacenes por empresa y sucursal
            $table->index(
                ['empresa_id', 'sucursal_id', 'activo'],
                'almacenes_empresa_sucursal_activo_index'
            );
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('almacenes');
    }
};

