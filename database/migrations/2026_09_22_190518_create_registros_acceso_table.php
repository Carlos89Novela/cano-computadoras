<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la Creación de la Tabla de 'registros_acceso'.
 *
 * Implementa la bitácora especializada en seguridad perimetral y autenticación:
 * - user_id: Usuario autenticado (o null si el intento fue con un correo inexistente).
 * - correo_intentado: Dirección de correo enviada en el formulario de login.
 * - sesion_id: Identificador de sesión asociado al evento.
 * - evento: Tipo de evento ('inicio_exitoso', 'inicio_fallido', 'bloqueo_temporal', 'cierre_sesion').
 * - resultado: Desenlace de la operación ('exitoso', 'fallido', 'bloqueado').
 * - direccion_ip, user_agent, ruta, metodo_http: Telemetría de red del cliente.
 * - motivo_fallo: Explicación del rechazo ('Credenciales inválidas', 'Demasiados intentos', etc.).
 * - metadatos: JSON con datos complementarios (como segundos de bloqueo restantes).
 * - ocurrido_at: Marca de tiempo exacta del incidente de acceso.
 * - Índices estratégicos para detección de ataques distribuidos o de fuerza bruta por IP o cuenta.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para estructurar la tabla de accesos.
     */
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

                // Índices especializados para telemetría forense y métricas de seguridad
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

    /**
     * Revierte las operaciones de migración eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('registros_acceso');
    }
};
