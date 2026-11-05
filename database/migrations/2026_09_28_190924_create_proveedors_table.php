<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la tabla 'proveedores'.
 *
 * Define la estructura de persistencia para el módulo de compras y aprovisionamiento.
 * Cada proveedor pertenece obligatoriamente a una Empresa (modelo multi-empresa),
 * cuenta con un código único dentro del ámbito de dicha empresa y registra
 * información de contacto, fiscal y trazabilidad de desactivación auditada.
 */
return new class extends Migration
{
    /**
     * Ejecuta las migraciones creando la tabla 'proveedores'.
     */
    public function up(): void
    {
        Schema::create(
            'proveedores',
            function (Blueprint $table): void {
                // Identificador autoincremental primario de la tabla
                $table->id();

                // 1. Relación Multi-Empresa:
                // Se utiliza 'restrictOnDelete' para evitar la eliminación accidental
                // de una empresa si aún mantiene proveedores registrados.
                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->restrictOnDelete();

                // 2. Identificación comercial y legal del proveedor:
                // 'codigo': Clave alfanumérica única por empresa (ej. PROV-001) para referencia rápida.
                // 'nombre': Nombre comercial representativo para las listas y vistas operativas.
                $table->string('codigo', 40);
                $table->string('nombre', 200);

                // 'razon_social': Nombre fiscal registrado ante la autoridad tributaria.
                $table->string('razon_social', 200)
                    ->nullable();

                // 'rfc': Registro Federal de Contribuyentes u homólogo fiscal.
                $table->string('rfc', 20)
                    ->nullable();

                // 3. Información de contacto del proveedor:
                // Nombre de la persona o departamento de contacto de ventas/compras.
                $table->string('contacto', 150)
                    ->nullable();

                // Teléfono de contacto directo.
                $table->string('telefono', 30)
                    ->nullable();

                // Correo electrónico para órdenes de compra y cotizaciones.
                $table->string('correo')
                    ->nullable();

                // Dirección física o domicilio fiscal de entrega/recepción.
                $table->text('direccion')
                    ->nullable();

                // Observaciones o condiciones especiales de venta (días de entrega, créditos, etc.).
                $table->text('notas')
                    ->nullable();

                // 4. Estado operativo y desactivación lógica:
                // En lugar de borrar registros físicos (Hard Delete), los proveedores se activan o desactivan
                // para mantener la integridad histórica de las compras e inventarios.
                $table->boolean('activo')
                    ->default(true)
                    ->index();

                // 5. Trazabilidad de usuarios responsables (Auditoría):
                // 'creado_por_id': Usuario que dio de alta el proveedor.
                $table->foreignId('creado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                // 'actualizado_por_id': Último usuario que modificó los datos generales.
                $table->foreignId('actualizado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                // 'desactivado_por_id': Usuario que realizó la baja lógica.
                $table->foreignId('desactivado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                // Marca de tiempo exacta de la desactivación.
                $table->timestamp('desactivado_at')
                    ->nullable();

                // Justificación obligatoria del motivo por el cual se dio de baja el proveedor.
                $table->text('motivo_desactivacion')
                    ->nullable();

                // Marcas de tiempo de Laravel (created_at y updated_at)
                $table->timestamps();

                // 6. Índices y restricciones de unicidad:
                // Garantiza que un código no se repita dentro de la MISMA empresa,
                // permitiendo que distintas empresas utilicen nomenclaturas independientes.
                $table->unique(
                    [
                        'empresa_id',
                        'codigo',
                    ],
                    'proveedores_empresa_codigo_unique'
                );

                // Índice compuesto optimizado para la consulta principal del controlador (index):
                // filtra por empresa, filtra/ordena por estado activo y ordena por nombre.
                $table->index(
                    [
                        'empresa_id',
                        'activo',
                        'nombre',
                    ],
                    'proveedores_empresa_activo_nombre_index'
                );
            }
        );
    }

    /**
     * Revierte las migraciones eliminando la tabla 'proveedores'.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
