<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiar tablas parciales de intentos previos
        Schema::dropIfExists('cliente_monedero_movimientos');
        Schema::dropIfExists('promocion_items');
        Schema::dropIfExists('promociones');

        // 1. Tabla maestra de Promociones y Combos
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo_cupon', 40)->nullable()->index();
            $table->text('descripcion')->nullable();

            $table->enum('tipo', [
                '2x1',
                'segundo_al_porcentaje',
                'descuento_porcentaje',
                'precio_fijo_combo',
                'regalo_en_compra',
            ])->default('descuento_porcentaje');

            $table->decimal('valor_descuento', 10, 2)->default(0.00);
            $table->decimal('precio_combo', 10, 2)->nullable();
            $table->integer('cantidad_requerida')->default(1);

            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_fin')->nullable();
            $table->json('dias_semana')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();

            $table->boolean('solo_clientes_destacados')->default(false);
            $table->integer('puntos_extra_otorgados')->default(0);

            $table->integer('limite_usos')->nullable();
            $table->integer('usos_actuales')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Productos o Categorías participantes en la Promoción o Combo
        Schema::create('promocion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promocion_id')->constrained('promociones')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->cascadeOnDelete();
            $table->foreignId('categoria_producto_id')->nullable()->constrained('categorias_producto')->cascadeOnDelete();
            $table->integer('cantidad')->default(1);
            $table->enum('rol', ['participante', 'beneficio_regalo'])->default('participante');
            $table->timestamps();
        });

        // 3. Crear o extender tabla clientes para Lealtad, Puntos y Monedero Electrónico
        if (! Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nombre', 180);
                $table->string('rfc', 13)->nullable()->index();
                $table->string('correo', 150)->nullable();
                $table->string('telefono_whatsapp', 20)->nullable()->index();
                $table->enum('nivel_lealtad', ['regular', 'frecuente', 'destacado'])->default('regular');
                $table->integer('puntos_acumulados')->default(0);
                $table->decimal('saldo_monedero', 12, 2)->default(0.00);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('clientes', function (Blueprint $table) {
                if (! Schema::hasColumn('clientes', 'nivel_lealtad')) {
                    $table->enum('nivel_lealtad', ['regular', 'frecuente', 'destacado'])->default('regular');
                }
                if (! Schema::hasColumn('clientes', 'puntos_acumulados')) {
                    $table->integer('puntos_acumulados')->default(0);
                }
                if (! Schema::hasColumn('clientes', 'saldo_monedero')) {
                    $table->decimal('saldo_monedero', 12, 2)->default(0.00);
                }
                if (! Schema::hasColumn('clientes', 'telefono_whatsapp')) {
                    $table->string('telefono_whatsapp', 20)->nullable()->index();
                }
            });
        }

        // 4. Bitácora auditada de movimientos de Monedero y Puntos
        Schema::create('cliente_monedero_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('cliente_id')->index();
            $table->unsignedBigInteger('venta_id')->nullable()->index();
            $table->enum('tipo', ['abono_compra', 'canje_pago', 'bono_promocion', 'ajuste_manual']);
            $table->decimal('monto_monedero', 12, 2)->default(0.00);
            $table->integer('puntos')->default(0);
            $table->string('concepto', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_monedero_movimientos');
        Schema::dropIfExists('promocion_items');
        Schema::dropIfExists('promociones');
    }
};