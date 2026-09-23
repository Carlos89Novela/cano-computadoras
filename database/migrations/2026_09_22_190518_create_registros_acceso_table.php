<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'registros_acceso',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('correo_intentado')->nullable();
                $table->string('sesion_id', 255)->nullable();

                $table->string('evento', 50);
                $table->string('resultado', 30);

                $table->string('direccion_ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('ruta')->nullable();
                $table->string('metodo_http', 10)->nullable();

                $table->string('motivo_fallo')->nullable();
                $table->json('metadatos')->nullable();

                $table->timestamp('ocurrido_at')->useCurrent();

                $table->index(
                    ['user_id', 'ocurrido_at'],
                    'registros_acceso_usuario_fecha_index'
                );

                $table->index(
                    ['correo_intentado', 'ocurrido_at'],
                    'registros_acceso_correo_fecha_index'
                );

                $table->index(
                    ['evento', 'resultado', 'ocurrido_at'],
                    'registros_acceso_evento_resultado_index'
                );

                $table->index(
                    ['direccion_ip', 'ocurrido_at'],
                    'registros_acceso_ip_fecha_index'
                );

                $table->index('sesion_id');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_acceso');
    }
};
