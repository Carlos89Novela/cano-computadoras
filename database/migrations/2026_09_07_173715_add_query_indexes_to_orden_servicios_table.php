<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Optimización de Consultas en 'orden_servicios'.
 *
 * Agrega índices compuestos estratégicos para acelerar las vistas y tableros operativos:
 * 1. ['fecha_ingreso', 'id']: Acelera la ordenación cronológica y paginación en listas FIFO de recepción.
 * 2. ['estado', 'fecha_ingreso', 'id']: Optimiza los filtros rápidos por estado operativo
 *    (ej. ver todas las órdenes 'En reparación' ordenadas por antigüedad de ingreso).
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para indexar columnas clave.
     */
    public function up(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            // Índice para ordenamiento cronológico de recepción
            $table->index(
                ['fecha_ingreso', 'id'],
                'orden_servicios_fecha_ingreso_id_index'
            );

            // Índice compuesto para filtrado por estado y ordenamiento de colas de trabajo
            $table->index(
                ['estado', 'fecha_ingreso', 'id'],
                'orden_servicios_estado_fecha_id_index'
            );
        });
    }

    /**
     * Revierte las operaciones de migración eliminando los índices.
     */
    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->dropIndex(
                'orden_servicios_estado_fecha_id_index'
            );

            $table->dropIndex(
                'orden_servicios_fecha_ingreso_id_index'
            );
        });
    }
};
