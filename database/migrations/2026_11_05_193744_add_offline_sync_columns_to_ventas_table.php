<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('origen_registro', 20)->default('online')->after('estado');
            $table->timestamp('sincronizado_at')->nullable()->after('origen_registro');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['origen_registro', 'sincronizado_at']);
        });
    }
};
