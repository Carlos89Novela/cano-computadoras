<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla 'servicios'.
 *
 * Estructura la tabla de catálogo de servicios ofrecidos por el taller:
 * - id: Identificador único autoincremental primario.
 * - nombre: Nombre comercial o técnico del servicio.
 * - descripcion: Detalle técnico opcional del alcance del servicio.
 * - precio: Tarifa base monetaria en formato decimal(8,2).
 * - activo: Bandera booleana para habilitar o inhabilitar la contratación del servicio.
 * - timestamps: Marcas de tiempo de creación y última actualización.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para crear la tabla de servicios.
     */
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 8, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
