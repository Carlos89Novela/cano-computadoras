<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->string('autorizacion')
                ->default('pendiente')
                ->after('estado');

            $table->timestamp('fecha_autorizacion')
                ->nullable()
                ->after('autorizacion');
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->dropColumn([
                'autorizacion',
                'fecha_autorizacion',
            ]);
        });
    }
};