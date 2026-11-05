<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Migración para Incorporar 'token_seguimiento' en 'orden_servicios'.
 *
 * Implementa el mecanismo de seguimiento público por código QR / URL sin sesión:
 * 1. Agrega la columna nullable `token_seguimiento` (varchar 64).
 * 2. Migra los datos existentes por lotes (`chunkById`), poblando cada orden
 *    con un identificador único seguro ULID (`Str::ulid()`).
 * 3. Añade la restricción de unicidad estricta para garantizar que ningún token colisione.
 */
return new class extends Migration
{
    /**
     * Ejecuta las operaciones de migración e inicialización de tokens.
     */
    public function up(): void
    {
        // Agrega la columna preliminarmente como nullable
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->string('token_seguimiento', 64)
                ->nullable()
                ->after('folio');
        });

        // Genera identificadores ULID para registros existentes en lotes de 100
        DB::table('orden_servicios')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($ordenes): void {
                foreach ($ordenes as $orden) {
                    DB::table('orden_servicios')
                        ->where('id', $orden->id)
                        ->update([
                            'token_seguimiento' => (string) Str::ulid(),
                        ]);
                }
            });

        // Establece la restricción de clave única sobre el token de seguimiento
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->unique(
                'token_seguimiento',
                'orden_servicios_token_seguimiento_unique'
            );
        });
    }

    /**
     * Revierte las operaciones de migración eliminando el índice y la columna.
     */
    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table): void {
            $table->dropUnique(
                'orden_servicios_token_seguimiento_unique'
            );

            $table->dropColumn('token_seguimiento');
        });
    }
};
