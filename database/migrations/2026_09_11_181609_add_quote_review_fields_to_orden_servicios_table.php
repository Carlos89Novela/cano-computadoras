<?php

use App\Enums\EstadoRevisionCotizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Agregar Campos de Revisión y Dictamen de Cotizaciones en 'orden_servicios'.
 *
 * Implementa las columnas de auditoría y flujo para la validación previa de presupuestos:
 * - estado_revision_cotizacion: Estado de la cotización ('sin_solicitar', 'pendiente', 'aprobada', 'rechazada').
 * - cotizacion_revisada_por_id: Clave foránea al supervisor o administrador que dictaminó la cotización.
 * - cotizacion_revisada_at: Marca de tiempo exacta del dictamen de supervisión.
 * - observacion_revision_cotizacion: Notas técnicas o motivos de devolución emitidos por el supervisor.
 * - Índice específico: Acelera la consulta DataTables del supervisor sobre cotizaciones en estado 'pendiente'.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para incorporar campos de revisión de cotización.
     */
    public function up(): void
    {
        Schema::table(
            'orden_servicios',
            function (Blueprint $table): void {
                $table->string(
                    'estado_revision_cotizacion'
                )
                    ->default(
                        EstadoRevisionCotizacion::SIN_SOLICITAR->value
                    )
                    ->after('costo_estimado');

                $table->foreignId(
                    'cotizacion_revisada_por_id'
                )
                    ->nullable()
                    ->after('estado_revision_cotizacion')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'cotizacion_revisada_at'
                )
                    ->nullable()
                    ->after('cotizacion_revisada_por_id');

                $table->text(
                    'observacion_revision_cotizacion'
                )
                    ->nullable()
                    ->after('cotizacion_revisada_at');

                $table->index(
                    'estado_revision_cotizacion',
                    'ordenes_revision_cotizacion_index'
                );
            }
        );
    }

    /**
     * Revierte las operaciones de migración eliminando los campos e índice.
     */
    public function down(): void
    {
        Schema::table(
            'orden_servicios',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'ordenes_revision_cotizacion_index'
                );

                $table->dropConstrainedForeignId(
                    'cotizacion_revisada_por_id'
                );

                $table->dropColumn([
                    'estado_revision_cotizacion',
                    'cotizacion_revisada_at',
                    'observacion_revision_cotizacion',
                ]);
            }
        );
    }
};
