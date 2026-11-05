<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla Raíz de 'empresas'.
 *
 * Establece el modelo multitenant o multi-empresa de la plataforma:
 * - id: Identificador único autoincremental primario de la empresa.
 * - nombre: Nombre comercial único de la organización.
 * - razon_social: Denominación jurídica / fiscal registrada ante las autoridades tributarias.
 * - rfc: Registro Federal de Contribuyentes o identificador fiscal homólogo.
 * - telefono, correo, direccion_fiscal: Información de contacto y fiscal corporativa.
 * - activo: Bandera booleana de estado operativo para habilitación en plataforma.
 * - creado_por_id / actualizado_por_id: Trazabilidad de usuarios administradores autores.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para estructurar la tabla de empresas.
     */
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();

            // Identificación comercial y fiscal
            $table->string('nombre', 150);
            $table->string('razon_social', 200)->nullable();
            $table->string('rfc', 20)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo')->nullable();
            $table->text('direccion_fiscal')->nullable();

            // Estado de activación
            $table->boolean('activo')
                ->default(true)
                ->index();

            // Trazabilidad de autoría
            $table->foreignId('creado_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('actualizado_por_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Unicidad del nombre corporativo
            $table->unique(
                'nombre',
                'empresas_nombre_unique'
            );
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
