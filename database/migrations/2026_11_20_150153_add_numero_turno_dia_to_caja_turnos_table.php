<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el consecutivo diario de turno por sucursal (reinicia en 1 cada día).
     */
    public function up(): void
    {
        if (Schema::hasTable('caja_turnos') && ! Schema::hasColumn('caja_turnos', 'numero_turno_dia')) {
            Schema::table('caja_turnos', function (Blueprint $table) {
                $table->unsignedInteger('numero_turno_dia')->default(1)->after('sucursal_id');
            });
        }
    }

    /**
     * Revierte la columna numero_turno_dia.
     */
    public function down(): void
    {
        if (Schema::hasTable('caja_turnos') && Schema::hasColumn('caja_turnos', 'numero_turno_dia')) {
            Schema::table('caja_turnos', function (Blueprint $table) {
                $table->dropColumn('numero_turno_dia');
            });
        }
    }
};
