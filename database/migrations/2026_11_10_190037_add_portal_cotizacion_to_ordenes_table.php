<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->uuid('token_publico')->nullable()->unique()->after('id');
            $table->dateTime('cotizacion_expira_at')->nullable()->after('estado');
            $table->string('firma_digital_path')->nullable()->after('cotizacion_expira_at');
            $table->dateTime('firmado_at')->nullable()->after('firma_digital_path');
            $table->text('motivo_rechazo')->nullable()->after('firmado_at');

            $table->index(['token_publico', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->dropIndex(['token_publico', 'estado']);
            $table->dropColumn([
                'token_publico',
                'cotizacion_expira_at',
                'firma_digital_path',
                'firmado_at',
                'motivo_rechazo',
            ]);
        });
    }
};