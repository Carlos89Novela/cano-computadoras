<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transferencias_inventario', function (Blueprint $table) {
            $table->string('estado', 30)->default('solicitada')->after('folio')->index();
            $table->foreignId('almacen_transito_id')->nullable()->after('almacen_destino_id')->constrained('almacenes')->nullOnDelete();

            $table->foreignId('solicitado_por_id')->nullable()->after('creado_por_id')->constrained('users')->nullOnDelete();
            $table->foreignId('despachado_por_id')->nullable()->after('solicitado_por_id')->constrained('users')->nullOnDelete();
            $table->foreignId('recibido_por_id')->nullable()->after('despachado_por_id')->constrained('users')->nullOnDelete();

            $table->timestamp('solicitado_at')->nullable()->after('transferido_at')->index();
            $table->timestamp('despachado_at')->nullable()->after('solicitado_at')->index();
            $table->timestamp('recibido_at')->nullable()->after('despachado_at')->index();

            $table->text('observaciones_despacho')->nullable()->after('motivo');
            $table->text('observaciones_recepcion')->nullable()->after('observaciones_despacho');
        });

        Schema::table('transferencia_inventario_detalles', function (Blueprint $table) {
            $table->decimal('cantidad_solicitada', 12, 4)->default(0)->after('producto_id');
            $table->decimal('cantidad_despachada', 12, 4)->default(0)->after('cantidad_solicitada');
            $table->decimal('cantidad_recibida', 12, 4)->default(0)->after('cantidad_despachada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transferencia_inventario_detalles', function (Blueprint $table) {
            $table->dropColumn([
                'cantidad_solicitada',
                'cantidad_despachada',
                'cantidad_recibida',
            ]);
        });

        Schema::table('transferencias_inventario', function (Blueprint $table) {
            $table->dropForeign(['almacen_transito_id']);
            $table->dropForeign(['solicitado_por_id']);
            $table->dropForeign(['despachado_por_id']);
            $table->dropForeign(['recibido_por_id']);

            $table->dropColumn([
                'estado',
                'almacen_transito_id',
                'solicitado_por_id',
                'despachado_por_id',
                'recibido_por_id',
                'solicitado_at',
                'despachado_at',
                'recibido_at',
                'observaciones_despacho',
                'observaciones_recepcion',
            ]);
        });
    }
};
