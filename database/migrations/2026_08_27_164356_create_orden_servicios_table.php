<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación Inicial de la Tabla 'orden_servicios'.
 *
 * Establece la estructura primaria para el seguimiento de reparaciones en taller:
 * - id: Identificador único autoincremental primario.
 * - folio: Código alfanumérico único para identificación comercial y de taller.
 * - user_id: Clave foránea al cliente titular de la orden.
 * - equipo_id: Clave foránea al equipo físico ingresado a servicio.
 * - problema_reportado: Descripción inicial de la falla capturada por el cliente.
 * - diagnostico: Dictamen técnico emitido tras la revisión en taller.
 * - costo_estimado: Presupuesto preliminar propuesto para el cliente (decimal 10,2).
 * - costo_final: Importe final facturado/cobrado al concluir los trabajos (decimal 10,2).
 * - estado: Estado del flujo operativo (inicialmente 'Recibido').
 * - fecha_ingreso: Fecha de recepción física del equipo.
 * - fecha_entrega: Fecha de devolución final del equipo reparado al cliente.
 * - timestamps: Marcas de tiempo de creación y modificación.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para crear la tabla de órdenes de servicio.
     */
    public function up(): void
    {
        Schema::create('orden_servicios', function (Blueprint $table): void {
            $table->id();
            $table->string('folio')->unique();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->foreignId('equipo_id')->constrained()->onDelete('cascade');

            $table->text('problema_reportado');

            $table->text('diagnostico')->nullable();

            $table->decimal('costo_estimado', 10, 2)->nullable();
            $table->decimal('costo_final', 10, 2)->nullable();

            $table->string('estado')->default('Recibido');
            $table->date('fecha_ingreso');
            $table->date('fecha_entrega')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_servicios');
    }
};

