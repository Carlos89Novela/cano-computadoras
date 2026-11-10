<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurante_reservaciones', function (Blueprint $table) {$table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();$table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('restaurante_mesa_id')->nullable()->constrained('restaurante_mesas')->nullOnDelete();$table->string('folio', 30);
            $table->string('cliente_nombre', 150);$table->string('cliente_telefono', 25)->nullable();
            $table->string('cliente_email', 100)->nullable();$table->unsignedSmallInteger('numero_personas')->default(2);
            $table->dateTime('fecha_hora_reservacion');$table->unsignedSmallInteger('duracion_estimada_minutos')->default(90);
            $table->enum('estado', ['confirmada', 'sentada', 'completada', 'cancelada', 'no_show'])->default('confirmada');$table->text('notas_especiales')->nullable()->comment('ej. aniversario, silla para bebé, intolerancia');
            $table->foreignId('atendido_por_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'fecha_hora_reservacion', 'estado'], 'idx_reservaciones_filtro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurante_reservaciones');
    }
};