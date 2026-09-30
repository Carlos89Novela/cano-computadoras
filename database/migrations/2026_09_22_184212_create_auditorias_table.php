<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla Central de 'auditorias'.
 *
 * Implementa el repositorio inmutable de trazabilidad y gobernanza de datos:
 * - usuario_id: Actor autenticado que ejecutó la acción.
 * - usuario_afectado_id: Usuario sobre el que recayó el cambio (en caso de modificación de cuentas/roles).
 * - sesion_id, accion, modulo: Descriptores taxonómicos de la operación auditada.
 * - modelo_tipo, modelo_id: Vínculo polimórfico al registro afectado (Proveedor, Categoría, Orden, etc.).
 * - valores_anteriores / valores_nuevos: Instantáneas JSON completas de los cambios (delta).
 * - metadatos: Contexto JSON complementario sanitizado de datos sensibles.
 * - motivo: Justificación explícita aportada por el usuario al realizar operaciones críticas.
 * - direccion_ip, user_agent, ruta, metodo_http: Telemetría de red y HTTP.
 * - Índices múltiples de alto desempeño para búsquedas analíticas y auditorías forenses.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para estructurar la tabla de auditorías.
     */
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table): void {
            $table->id();

            // Identificación de los actores involucrados
            $table
                ->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('usuario_afectado_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('sesion_id', 255)->nullable();
            $table->string('accion', 120);
            $table->string('modulo', 80);

            // Relación polimórfica hacia la entidad auditada
            $table->string('modelo_tipo')->nullable();
            $table->unsignedBigInteger('modelo_id')->nullable();

            // Detalle descriptivo y deltas de cambios en formato JSON
            $table->text('descripcion');
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->json('metadatos')->nullable();

            // Justificación obligatoria para operaciones sensibles
            $table->text('motivo')->nullable();

            // Telemetría HTTP y de cliente
            $table->string('direccion_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ruta')->nullable();
            $table->string('metodo_http', 10)->nullable();

            $table
                ->string('resultado', 30)
                ->default('exitoso');

            $table->timestamp('created_at')->useCurrent();

            // Índices compuestos optimizados para consultas del visor de auditoría
            $table->index(
                ['modulo', 'accion', 'created_at'],
                'auditorias_modulo_accion_fecha_index'
            );

            $table->index(
                ['usuario_id', 'created_at'],
                'auditorias_usuario_fecha_index'
            );

            $table->index(
                ['usuario_afectado_id', 'created_at'],
                'auditorias_afectado_fecha_index'
            );

            $table->index(
                ['modelo_tipo', 'modelo_id'],
                'auditorias_modelo_index'
            );

            $table->index(
                ['resultado', 'created_at'],
                'auditorias_resultado_fecha_index'
            );
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};

