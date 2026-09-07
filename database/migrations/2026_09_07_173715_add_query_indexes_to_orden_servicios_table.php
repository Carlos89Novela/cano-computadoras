<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->index(
                ['fecha_ingreso', 'id'],
                'orden_servicios_fecha_ingreso_id_index'
            );

            $table->index(
                ['estado', 'fecha_ingreso', 'id'],
                'orden_servicios_estado_fecha_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->dropIndex(
                'orden_servicios_estado_fecha_id_index'
            );

            $table->dropIndex(
                'orden_servicios_fecha_ingreso_id_index'
            );
        });
    }
};
