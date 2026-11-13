<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'es_extra')) {
                $table->boolean('es_extra')->default(false)->after('es_receta')
                    ->comment('Indica si este insumo se ofrece como extra/modificador en comanda');
            }
            if (! Schema::hasColumn('productos', 'precio_extra')) {
                $table->decimal('precio_extra', 10, 2)->default(0.00)->after('es_extra')
                    ->comment('Precio de venta al público cuando se agrega como extra');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'es_extra')) {
                $table->dropColumn(['es_extra', 'precio_extra']);
            }
        });
    }
};
