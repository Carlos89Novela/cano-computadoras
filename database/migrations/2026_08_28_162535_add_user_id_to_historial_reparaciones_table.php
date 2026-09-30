<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Corregir Pluralización y Vincular 'user_id' en 'historial_reparaciones'.
 *
 * Realiza dos tareas de mantenimiento estructural:
 * 1. Renombra la tabla 'historial_reparacions' a la convención española correcta 'historial_reparaciones'.
 * 2. Agrega la clave foránea 'user_id' hacia 'users' con 'nullOnDelete', permitiendo registrar
 *    con precisión el usuario o técnico responsable de cada entrada en la bitácora histórica.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para renombrar tabla y asociar user_id.
     */
    public function up(): void
    {
        // Corrige el nombre en caso de provenir de la migración inicial en inglés
        if (
            Schema::hasTable('historial_reparacions') &&
            ! Schema::hasTable('historial_reparaciones')
        ) {
            Schema::rename(
                'historial_reparacions',
                'historial_reparaciones'
            );
        }

        // Incorpora la clave foránea del autor del evento histórico
        if (
            Schema::hasTable('historial_reparaciones') &&
            ! Schema::hasColumn('historial_reparaciones', 'user_id')
        ) {
            Schema::table('historial_reparaciones', function (Blueprint $table): void {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('orden_servicio_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Revierte las operaciones de migración.
     */
    public function down(): void
    {
        if (
            Schema::hasTable('historial_reparaciones') &&
            Schema::hasColumn('historial_reparaciones', 'user_id')
        ) {
            Schema::table('historial_reparaciones', function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        if (
            Schema::hasTable('historial_reparaciones') &&
            ! Schema::hasTable('historial_reparacions')
        ) {
            Schema::rename(
                'historial_reparaciones',
                'historial_reparacions'
            );
        }
    }
};

