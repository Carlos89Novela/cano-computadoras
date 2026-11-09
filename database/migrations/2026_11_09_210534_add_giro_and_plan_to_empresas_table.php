<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('giro_comercial', 40)->default('computo_taller')->after('nombre');
            $table->string('plan_suscripcion', 30)->default('avanzado')->after('giro_comercial');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['giro_comercial', 'plan_suscripcion']);
        });
    }
};
