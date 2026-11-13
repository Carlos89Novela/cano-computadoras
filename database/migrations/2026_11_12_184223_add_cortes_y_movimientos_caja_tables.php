<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('caja_turnos')) {
            Schema::table('caja_turnos', function (Blueprint $table) {
                if (! Schema::hasColumn('caja_turnos', 'total_ventas_efectivo')) {
                    $table->decimal('total_ventas_efectivo', 12, 2)->default(0.00)->after('monto_inicial');
                }
                if (! Schema::hasColumn('caja_turnos', 'total_ventas_tarjeta')) {
                    $table->decimal('total_ventas_tarjeta', 12, 2)->default(0.00)->after('total_ventas_efectivo');
                }
                if (! Schema::hasColumn('caja_turnos', 'total_ventas_transferencia')) {
                    $table->decimal('total_ventas_transferencia', 12, 2)->default(0.00)->after('total_ventas_tarjeta');
                }
                if (! Schema::hasColumn('caja_turnos', 'total_retiros')) {
                    $table->decimal('total_retiros', 12, 2)->default(0.00)->after('total_ventas_transferencia');
                }
                if (! Schema::hasColumn('caja_turnos', 'total_otros_ingresos')) {
                    $table->decimal('total_otros_ingresos', 12, 2)->default(0.00)->after('total_retiros');
                }
            });
        }

        if (! Schema::hasTable('caja_movimientos')) {
            Schema::create('caja_movimientos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('caja_turno_id')->constrained('caja_turnos')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('autorizado_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('tipo', ['ingreso', 'retiro'])->default('retiro');
                $table->string('concepto', 120);
                $table->decimal('monto', 12, 2);
                $table->string('motivo', 500)->nullable();
                $table->string('comprobante_numero', 60)->nullable();
                $table->timestamp('realizado_at')->useCurrent();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_movimientos');
    }
};