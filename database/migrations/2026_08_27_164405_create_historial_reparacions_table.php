<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla 'historial_reparacions'.
 *
 * Registra la bitácora cronológica inmutable de eventos y transiciones de estado:
 * - id: Identificador único autoincremental primario.
 * - orden_servicio_id: Clave foránea a la orden a la que pertenece el evento.
 * - estado: Estado de la orden en el momento del registro.
 * - comentarios: Notas técnicas o bitácora de la intervención realizada.
 * - timestamps: Marcas temporales exactas del evento registrado.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para crear la tabla de historial de reparaciones.
     */
    public function up(): void
    {
        Schema::create('historial_reparacions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_servicio_id')->constrained()->onDelete('cascade');
            $table->string('estado');
            $table->text('comentarios')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_reparacions');
    }
};

