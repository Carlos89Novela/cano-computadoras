<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Áreas o Ambientes (Salón, Terraza, Planta Alta, Barra)
        Schema::create('restaurante_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('nombre', 60);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'activo']);
        });

        // 2. Mesas Físicas con Coordenadas Drag & Drop
        Schema::create('restaurante_mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_area_id')->constrained('restaurante_areas')->cascadeOnDelete();
            $table->string('numero_o_nombre', 30);
            $table->unsignedSmallInteger('capacidad')->default(4);
            $table->enum('forma', ['cuadrada', 'redonda', 'rectangular'])->default('cuadrada');
            $table->decimal('posicion_x', 5, 2)->default(10.00)->comment('Porcentaje de 0 a 100% en el eje X');
            $table->decimal('posicion_y', 5, 2)->default(10.00)->comment('Porcentaje de 0 a 100% en el eje Y');
            $table->enum('estado', ['disponible', 'ocupada', 'cuenta_pedida', 'inactiva'])->default('disponible');
            $table->foreignId('comanda_actual_id')->nullable()->constrained('comandas')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['restaurante_area_id', 'numero_o_nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante_mesas');
        Schema::dropIfExists('restaurante_areas');
    }
};
