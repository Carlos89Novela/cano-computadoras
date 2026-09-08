<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historial_reparaciones', function (Blueprint $table) {
            $table->text('mensaje_cliente')
                ->nullable()
                ->after('comentarios');
        });
    }

    public function down(): void
    {
        Schema::table('historial_reparaciones', function (Blueprint $table) {
            $table->dropColumn('mensaje_cliente');
        });
    }
};
