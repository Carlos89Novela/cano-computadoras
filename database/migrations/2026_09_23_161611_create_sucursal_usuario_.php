<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla Pivote 'sucursal_usuario'.
 *
 * Administra la vinculación de colaboradores, supervisores y gerentes a sucursales:
 * - sucursal_id / user_id: Claves foráneas que componen la relación muchos a muchos.
 * - es_principal: Booleano que define si esta es la sucursal base o de adscripción principal del empleado.
 * - es_gerente: Booleano que designa al usuario como responsable / gerente de la sucursal.
 * - activo: Bandera booleana para habilitar o inhabilitar la adscripción.
 * - asignado_por_id / asignado_at / finalizado_at: Trazabilidad del periodo de adscripción.
 * - Índices compuestos: Optimizan la consulta del gerente activo y la pertenencia del usuario.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para estructurar la tabla pivote de sucursal y usuario.
     */
    public function up(): void
    {
        Schema::create(
            'sucursal_usuario',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('sucursal_id')
                    ->constrained('sucursales')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->boolean('es_principal')
                    ->default(false);

                $table->boolean('es_gerente')
                    ->default(false);

                $table->boolean('activo')
                    ->default(true);

                $table->foreignId('asignado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('asignado_at')
                    ->useCurrent();

                $table->timestamp('finalizado_at')
                    ->nullable();

                $table->timestamps();

                // Evita asignaciones duplicadas de un usuario a la misma sucursal
                $table->unique(
                    ['sucursal_id', 'user_id'],
                    'sucursal_usuario_unique'
                );

                // Índice para consultar sucursales activas de un colaborador
                $table->index(
                    ['user_id', 'activo'],
                    'sucursal_usuario_user_activo_index'
                );

                // Índice para ubicar al gerente activo de una sucursal
                $table->index(
                    ['sucursal_id', 'es_gerente', 'activo'],
                    'sucursal_usuario_gerente_index'
                );
            }
        );
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursal_usuario');
    }
};
