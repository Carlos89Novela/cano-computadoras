<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Blindar 'orden_servicios' Contra Eliminaciones Accidentales en Cascada.
 *
 * Modifica las restricciones de clave foránea `user_id` y `equipo_id`:
 * - Sustituye la política de eliminación automática `cascadeOnDelete` por `restrictOnDelete`.
 * - Garantiza la persistencia histórica de las órdenes de taller, bloqueando
 *   la eliminación física de un usuario cliente o de un equipo si cuentan
 *   con órdenes de servicio registradas en el sistema.
 */
return new class extends Migration
{
    /**
     * Reemplaza las claves foráneas en cascada por restricciones de eliminación estricta.
     */
    public function up(): void
    {
        // Elimina las restricciones foráneas previas con comportamiento en cascada
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->dropForeign([
                'user_id',
            ]);

            $table->dropForeign([
                'equipo_id',
            ]);
        });

        // Recrea las claves foráneas con protección estricta 'restrictOnDelete'
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('equipo_id')
                ->references('id')
                ->on('equipos')
                ->restrictOnDelete();
        });
    }

    /**
     * Revierte las restricciones restaurando el comportamiento en cascada previo.
     */
    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->dropForeign([
                'user_id',
            ]);

            $table->dropForeign([
                'equipo_id',
            ]);
        });

        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('equipo_id')
                ->references('id')
                ->on('equipos')
                ->cascadeOnDelete();
        });
    }
};

