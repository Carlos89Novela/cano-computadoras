<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para Declarar 'token_seguimiento' como Obligatorio (NOT NULL).
 *
 * Una vez que todas las órdenes preexistentes fueron pobladas exitosamente con su ULID,
 * esta migración ajusta la definición de la columna para impedir valores nulos
 * a nivel de esquema de base de datos.
 */
return new class extends Migration
{
    /**
     * Modifica la columna token_seguimiento para requerir obligatoriedad NOT NULL.
     */
    public function up(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->string('token_seguimiento', 64)
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Revierte la columna a permitir valores nulos.
     */
    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->string('token_seguimiento', 64)
                ->nullable()
                ->change();
        });
    }
};
