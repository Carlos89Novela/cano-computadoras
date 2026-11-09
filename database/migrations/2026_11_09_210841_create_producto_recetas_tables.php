<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar 'es_receta' solo si no existe en 'productos'
        if (! Schema::hasColumn('productos', 'es_receta')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->boolean('es_receta')->default(false)->after('precio_base')
                    ->comment('Indica si el producto se compone de insumos fraccionados');
            });
        }

        // 2. Ficha Técnica / Receta del Producto Elaborado
        if (! Schema::hasTable('producto_recetas')) {
            Schema::create('producto_recetas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $table->decimal('rendimiento_porciones', 8, 2)->default(1.00);
                $table->unsignedInteger('tiempo_preparacion_minutos')->default(10);
                $table->string('estacion_cocina', 50)->default('cocina')->comment('cocina, barra, parrilla, postres');
                $table->text('instrucciones')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['empresa_id', 'producto_id']);
            });
        }

        // 3. Insumos e Ingredientes Requeridos por Receta
        if (! Schema::hasTable('producto_receta_detalles')) {
            Schema::create('producto_receta_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('producto_receta_id')->constrained('producto_recetas')->cascadeOnDelete();
                $table->foreignId('insumo_producto_id')->comment('Producto clasificado como insumo/materia prima')
                    ->constrained('productos')->restrictOnDelete();
                $table->decimal('cantidad_requerida', 12, 4)->comment('Cantidad que se descuenta por cada porción');
                $table->decimal('porcentaje_merma', 5, 2)->default(0.00)->comment('Margen de desperdicio estimado');
                $table->timestamps();

                $table->unique(['producto_receta_id', 'insumo_producto_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_receta_detalles');
        Schema::dropIfExists('producto_recetas');

        if (Schema::hasColumn('productos', 'es_receta')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('es_receta');
            });
        }
    }
};
