<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Corrige el nombre antiguo creado por Laravel.
        if (
            Schema::hasTable('historial_reparacions') &&
            !Schema::hasTable('historial_reparaciones')
        ) {
            Schema::rename(
                'historial_reparacions',
                'historial_reparaciones'
            );
        }

        // Agrega user_id solamente si todavía no existe.
        if (
            Schema::hasTable('historial_reparaciones') &&
            !Schema::hasColumn('historial_reparaciones', 'user_id')
        ) {
            Schema::table('historial_reparaciones', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('orden_servicio_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('historial_reparaciones') &&
            Schema::hasColumn('historial_reparaciones', 'user_id')
        ) {
            Schema::table('historial_reparaciones', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        if (
            Schema::hasTable('historial_reparaciones') &&
            !Schema::hasTable('historial_reparacions')
        ) {
            Schema::rename(
                'historial_reparaciones',
                'historial_reparacions'
            );
        }
    }
};