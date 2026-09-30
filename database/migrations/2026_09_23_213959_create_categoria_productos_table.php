<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla de 'categorias_producto'.
 *
 * Estructura el catálogo jerárquico de categorías de productos y refacciones por empresa:
 * - empresa_id: Clave foránea a la empresa propietaria ('restrictOnDelete' para integridad).
 * - nombre: Denominación de la categoría (única en el ámbito de la empresa).
 * - descripcion: Alcance o tipo de artículos incluidos.
 * - activo: Bandera booleana de habilitación para ventas y taller (baja lógica).
 * - desactivado_por_id / desactivado_at / motivo_desactivacion: Auditoría del cese de uso de la categoría.
 * - Unicidad compuesta: [empresa_id, nombre].
 * - Índice compuesto optimizado: [empresa_id, activo, nombre] para paginación y ordenamiento del catálogo.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para estructurar la tabla de categorías de producto.
     */
    public function up(): void
    {
        Schema::create(
            'categorias_producto',
            function (Blueprint $table): void {
                $table->id();

                // Pertenencia a la empresa (multitenant)
                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->restrictOnDelete();

                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();

                // Estado de activación y baja lógica
                $table->boolean('activo')
                    ->default(true)
                    ->index();

                // Trazabilidad de usuarios
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

                // Unicidad del nombre por empresa
                $table->unique(
                    [
                        'empresa_id',
                        'nombre',
                    ],
                    'categorias_producto_empresa_nombre_unique'
                );

                // Índice compuesto de consulta para listados filtrados y paginados
                $table->index(
                    [
                        'empresa_id',
                        'activo',
                        'nombre',
                    ],
                    'categorias_producto_empresa_activo_index'
                );
            }
        );
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'categorias_producto'
        );
    }
};
