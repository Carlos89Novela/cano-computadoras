<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones_inventario', function (Blueprint $table) {
            $table->foreignId('orden_compra_id')
                ->nullable()
                ->after('proveedor_id')
                ->constrained('ordenes_compra')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recepciones_inventario', function (Blueprint $table) {
            $table->dropForeign(['orden_compra_id']);
            $table->dropColumn('orden_compra_id');
        });
    }
};
