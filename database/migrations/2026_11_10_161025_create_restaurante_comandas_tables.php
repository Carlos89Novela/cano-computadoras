<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comandas', function (Blueprint $table) {
            $table->id();$table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();$table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('mesero_user_id')->nullable()->constrained('users')->nullOnDelete();$table->string('folio', 30);
            $table->string('mesa_o_referencia', 60)->default('Mostrador / Para Llevar');$table->enum('estado', ['pendiente', 'en_preparacion', 'completada', 'cancelada'])->default('pendiente');
            $table->text('notas_generales')->nullable();$table->timestamp('enviada_a_cocina_at')->useCurrent();
            $table->timestamp('finalizada_at')->nullable();$table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'estado']);
        });

        Schema::create('comanda_items', function (Blueprint $table) {$table->id();
            $table->foreignId('comanda_id')->constrained('comandas')->cascadeOnDelete();$table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad', 8, 2)->default(1.00);$table->string('estacion_cocina', 50)->default('cocina')->comment('cocina, barra, parrilla, postres');
            $table->enum('estado', ['pendiente', 'en_preparacion', 'listo'])->default('pendiente');$table->string('notas_especiales', 255)->nullable()->comment('ej. sin cebolla, término medio');
            $table->timestamps();

            $table->index(['comanda_id', 'estacion_cocina', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_items');
        Schema::dropIfExists('comandas');
    }
};
