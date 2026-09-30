<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Agregar la Columna 'role' en la Tabla 'users'.
 *
 * Agrega un campo de texto simple para el rol base de usuario ('cliente' por defecto),
 * sirviendo como indicador legacy / auxiliar en conjunto con los roles dinámicos de Spatie.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración para agregar la columna role.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')
                ->default('cliente')
                ->after('password');
        });
    }

    /**
     * Revierte las operaciones de migración eliminando la columna role.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};

