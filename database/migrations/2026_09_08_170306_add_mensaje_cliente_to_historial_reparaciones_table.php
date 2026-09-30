<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Agregar 'mensaje_cliente' en 'historial_reparaciones'.
 *
 * Añade soporte para comunicación bidireccional / avisos dirigidos al cliente:
 * - Separa las notas técnicas internas del taller (`comentarios`)
 *   del texto público o comunicado oficial visible para el cliente (`mensaje_cliente`).
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para incorporar el mensaje al cliente.
     */
    public function up(): void
    {
        Schema::table('historial_reparaciones', function (Blueprint $table): void {
            $table->text('mensaje_cliente')
                ->nullable()
                ->after('comentarios');
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la columna.
     */
    public function down(): void
    {
        Schema::table('historial_reparaciones', function (Blueprint $table): void {
            $table->dropColumn('mensaje_cliente');
        });
    }
};

