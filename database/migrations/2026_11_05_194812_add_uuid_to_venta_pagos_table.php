<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_pagos') && ! Schema::hasColumn('venta_pagos', 'uuid')) {
            Schema::table('venta_pagos', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id')->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venta_pagos') && Schema::hasColumn('venta_pagos', 'uuid')) {
            Schema::table('venta_pagos', function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
    }
};