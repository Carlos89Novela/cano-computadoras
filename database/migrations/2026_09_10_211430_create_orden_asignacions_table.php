<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_asignaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orden_servicio_id')
                ->constrained('orden_servicios')
                ->cascadeOnDelete();

            $table->foreignId('empleado_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('asignado_por_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('asignado_at');
            $table->timestamp('finalizado_at')->nullable();

            $table->boolean('activo')
                ->default(true);

            $table->text('observaciones')
                ->nullable();

            $table->timestamps();

            $table->index([
                'orden_servicio_id',
                'activo',
            ]);

            $table->index([
                'empleado_id',
                'activo',
            ]);

            $table->index('asignado_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_asignaciones');
    }
};
