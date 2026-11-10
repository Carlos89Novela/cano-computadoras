<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('token_autofactura', 40)->nullable()->unique()->after('folio');$table->timestamp('autofactura_limite_at')->nullable()->after('token_autofactura');
        });

        Schema::table('comandas', function (Blueprint $table) {$table->decimal('total_acumulado', 12, 2)->default(0.00)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('comandas', function (Blueprint $table) {$table->dropColumn('total_acumulado');
        });

        Schema::table('ventas', function (Blueprint $table) {$table->dropColumn(['token_autofactura', 'autofactura_limite_at']);
        });
    }
};