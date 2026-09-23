<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'sucursal_usuario',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('sucursal_id')
                    ->constrained('sucursales')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->boolean('es_principal')
                    ->default(false);

                $table->boolean('es_gerente')
                    ->default(false);

                $table->boolean('activo')
                    ->default(true);

                $table->foreignId('asignado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('asignado_at')
                    ->useCurrent();

                $table->timestamp('finalizado_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    ['sucursal_id', 'user_id'],
                    'sucursal_usuario_unique'
                );

                $table->index(
                    ['user_id', 'activo'],
                    'sucursal_usuario_user_activo_index'
                );

                $table->index(
                    ['sucursal_id', 'es_gerente', 'activo'],
                    'sucursal_usuario_gerente_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_usuario');
    }
};
