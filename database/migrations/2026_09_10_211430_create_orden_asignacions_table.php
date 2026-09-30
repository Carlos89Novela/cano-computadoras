<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla 'orden_asignaciones'.
 *
 * Implementa el historial y control de asignaciones técnicas de órdenes de servicio:
 * - orden_servicio_id: Clave foránea a la orden (eliminación en cascada si se depura la orden).
 * - empleado_id: Clave foránea al usuario técnico responsable ('restrictOnDelete' para integridad).
 * - asignado_por_id: Clave foránea al supervisor o administrador que delegó el trabajo.
 * - asignado_at: Marca temporal de inicio de la asignación.
 * - finalizado_at: Marca temporal en que la asignación concluyó (por reasignación o entrega).
 * - activo: Bandera booleana (true = técnico asignado vigente).
 * - observaciones: Instrucciones u orientaciones preliminares del supervisor.
 * - Índices compuestos: Optimizan la consulta del técnico activo por orden y la carga de trabajo del empleado.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para crear la tabla de asignaciones.
     */
    public function up(): void
    {
        Schema::create('orden_asignaciones', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('orden_servicio_id')
                ->constrained('orden_servicios')
                ->cascadeOnDelete();

            $table->foreignId('empleado_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('asignado_por_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('asignado_at');
            $table->timestamp('finalizado_at')->nullable();

            $table->boolean('activo')
                ->default(true);

            $table->text('observaciones')
                ->nullable();

            $table->timestamps();

            // Índices compuestos para alto rendimiento en consultas de supervisión
            $table->index([
                'orden_servicio_id',
                'activo',
            ]);

            $table->index([
                'empleado_id',
                'activo',
            ]);

            $table->index('asignado_at');
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_asignaciones');
    }
};

