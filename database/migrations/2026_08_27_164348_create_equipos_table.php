<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla 'equipos'.
 *
 * Estructura la tabla de dispositivos físicos registrados por los clientes:
 * - id: Identificador único autoincremental primario.
 * - user_id: Clave foránea al usuario cliente dueño del equipo (con eliminación en cascada).
 * - tipo: Clasificación del hardware (Laptop, PC escritorio, All-in-One, Otro).
 * - marca: Fabricante del dispositivo (Dell, HP, Lenovo, Apple, etc.).
 * - modelo: Línea o modelo específico del equipo.
 * - numero_serie: Identificador de serie del fabricante (opcional).
 * - descripcion: Observaciones estéticas o características particulares del equipo (opcional).
 * - timestamps: Marcas de tiempo de creación y modificación.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para crear la tabla de equipos.
     */
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table): void {
            $table->id();

            // Vínculo foráneo con el cliente propietario
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('tipo');
            $table->string('marca');
            $table->string('modelo');
            $table->string('numero_serie')->nullable();
            $table->text('descripcion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
