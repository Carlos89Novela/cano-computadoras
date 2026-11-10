<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {$table->uuid('uuid_factura_global')->nullable()->after('uuid_fiscal');
            $table->boolean('es_factura_global')->default(false)->after('uuid_factura_global');$table->string('periodicidad_global', 5)->nullable()->after('es_factura_global');

            $table->index(['empresa_id', 'sucursal_id', 'estado_facturacion', 'uuid_factura_global'], 'idx_ventas_factura_global');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_factura_global');$table->dropColumn(['uuid_factura_global', 'es_factura_global', 'periodicidad_global']);
        });
    }
};