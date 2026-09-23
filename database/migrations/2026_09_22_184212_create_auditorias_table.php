<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table): void {
            $table->id();

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

            $table->string('modelo_tipo')->nullable();
            $table->unsignedBigInteger('modelo_id')->nullable();

            $table->text('descripcion');
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->json('metadatos')->nullable();

            $table->text('motivo')->nullable();

            $table->string('direccion_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ruta')->nullable();
            $table->string('metodo_http', 10)->nullable();

            $table
                ->string('resultado', 30)
                ->default('exitoso');

            $table->timestamp('created_at')->useCurrent();

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

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
