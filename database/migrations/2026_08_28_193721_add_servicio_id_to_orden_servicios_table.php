<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Asociar un 'servicio_id' Preliminar en 'orden_servicios'.
 *
 * Agrega la relación foránea nullable hacia la tabla 'servicios', permitiendo
 * que el cliente o el recepcionista elija un tipo de servicio de catálogo
 * al dar de alta la orden, manteniendo 'nullOnDelete' para evitar pérdida de datos
 * históricos si el servicio llegase a eliminarse.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para agregar la clave foránea servicio_id.
     */
    public function up(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->foreignId('servicio_id')
                ->nullable()
                ->after('equipo_id')
                ->constrained('servicios')
                ->nullOnDelete();
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la relación.
     */
    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->dropForeign(['servicio_id']);
            $table->dropColumn('servicio_id');
        });
    }
};
