<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ventas') && ! Schema::hasColumn('ventas', 'origen_registro')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->enum('origen_registro', ['online', 'offline'])->default('online')->after('estado');
                $table->timestamp('sincronizado_at')->nullable()->after('origen_registro');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ventas') && Schema::hasColumn('ventas', 'origen_registro')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->dropColumn(['origen_registro', 'sincronizado_at']);
            });
        }
    }
};