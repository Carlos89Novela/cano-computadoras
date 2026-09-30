<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Agregar la Bandera 'es_propietario' en 'users'.
 *
 * Establece la distinción jerárquica máxima de seguridad para cuentas de usuario:
 * - es_propietario: Booleano indexado (false por defecto). Los usuarios con esta marca
 *   ostentan el rol de administradores dueños, facultados para asignar y revocar roles
 *   y permisos delegables, abrir sucursales y exentos de revocaciones por administradores delegados.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para agregar la bandera de propietario.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table
                ->boolean('es_propietario')
                ->default(false)
                ->index()
                ->after('email_verified_at');
        });
    }

    /**
     * Revierte las operaciones de migración eliminando el índice y la columna.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex([
                'es_propietario',
            ]);

            $table->dropColumn('es_propietario');
        });
    }
};

