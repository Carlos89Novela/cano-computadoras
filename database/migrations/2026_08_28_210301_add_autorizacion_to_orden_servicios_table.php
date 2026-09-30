<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Agregar Campos de Autorización de Presupuesto en 'orden_servicios'.
 *
 * Añade soporte para el flujo de aprobación comercial por parte del cliente:
 * - autorizacion: Estado de la decisión del cliente ('pendiente', 'autorizada', 'rechazada').
 * - fecha_autorizacion: Marca temporal exacta del momento en que el cliente emitió su respuesta.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para incorporar campos de autorización.
     */
    public function up(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->string('autorizacion')
                ->default('pendiente')
                ->after('estado');

            $table->timestamp('fecha_autorizacion')
                ->nullable()
                ->after('autorizacion');
        });
    }

    /**
     * Revierte las operaciones de migración eliminando las columnas agregadas.
     */
    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->dropColumn([
                'autorizacion',
                'fecha_autorizacion',
            ]);
        });
    }
};

