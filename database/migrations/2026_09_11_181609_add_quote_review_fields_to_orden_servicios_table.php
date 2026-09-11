<?php

use App\Enums\EstadoRevisionCotizacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
