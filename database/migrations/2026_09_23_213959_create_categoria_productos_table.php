<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'categorias_producto',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->restrictOnDelete();

                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();

                $table->boolean('activo')
                    ->default(true)
                    ->index();

                $table->foreignId('creado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('actualizado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('desactivado_por_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('desactivado_at')
                    ->nullable();

                $table->text('motivo_desactivacion')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'empresa_id',
                        'nombre',
                    ],
                    'categorias_producto_empresa_nombre_unique'
                );

                $table->index(
                    [
                        'empresa_id',
                        'activo',
                        'nombre',
                    ],
                    'categorias_producto_empresa_activo_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'categorias_producto'
        );
    }
};
