<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias_producto', function (Blueprint $table) {
            if (! Schema::hasColumn('categorias_producto', 'estacion_cocina')) {
                $table->string('estacion_cocina', 30)->default('cocina')->after('nombre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categorias_producto', function (Blueprint $table) {
            $table->dropColumn('estacion_cocina');
        });
    }
};
