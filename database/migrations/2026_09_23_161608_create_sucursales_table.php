<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla de 'sucursales'.
 *
 * Estructura las sedes o unidades de negocio operativas dependientes de una empresa:
 * - empresa_id: Clave foránea a la empresa matriz propietaria ('restrictOnDelete' para protección).
 * - codigo: Clave alfanumérica única por empresa (ej. SUC-001).
 * - nombre: Denominación comercial de la sucursal.
 * - direccion, ciudad, estado, codigo_postal: Ubicación física de la sucursal.
 * - es_principal: Booleano que distingue la sede central / matriz.
 * - activo: Bandera booleana de estado operativo (baja lógica).
 * - desactivado_por_id / desactivado_at / motivo_desactivacion: Auditoría obligatoria de cierre o suspensión.
 * - Restricciones de unicidad compuesta: [empresa_id, codigo].
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para estructurar la tabla de sucursales.
     */
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table): void {
            $table->id();

            // Relación con la empresa propietaria
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->restrictOnDelete();

            $table->string('codigo', 30);
            $table->string('nombre', 150);

            // Datos de contacto y ubicación física
            $table->string('telefono', 30)->nullable();
            $table->string('correo')->nullable();
            $table->text('direccion')->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('estado', 100)->nullable();
            $table->string('codigo_postal', 10)->nullable();

            // Atributos de sede y estado operativo
            $table->boolean('es_principal')
                ->default(false);

            $table->boolean('activo')
                ->default(true)
                ->index();

            // Trazabilidad de creación, modificación y desactivación
            $table->foreignId('creado_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('actualizado_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('desactivado_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('desactivado_at')->nullable();
            $table->text('motivo_desactivacion')->nullable();

            $table->timestamps();

            // Unicidad del código por empresa
            $table->unique(
                ['empresa_id', 'codigo'],
                'sucursales_empresa_codigo_unique'
            );

            // Índice compuesto para listado de sucursales activas por empresa
            $table->index(
                ['empresa_id', 'activo'],
                'sucursales_empresa_activo_index'
            );
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
