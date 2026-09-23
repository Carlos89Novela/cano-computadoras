<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('almacenes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->restrictOnDelete();

            $table->foreignId('sucursal_id')
                ->nullable()
                ->constrained('sucursales')
                ->restrictOnDelete();

            $table->string('codigo', 40);
            $table->string('nombre', 150);

            $table->string('tipo', 30)
                ->default('principal')
                ->index();

            $table->boolean('es_virtual')
                ->default(false);

            $table->boolean('permite_existencias')
                ->default(true);

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

            $table->timestamp('desactivado_at')->nullable();
            $table->text('motivo_desactivacion')->nullable();

            $table->timestamps();

            $table->unique(
                ['empresa_id', 'codigo'],
                'almacenes_empresa_codigo_unique'
            );

            $table->index(
                ['empresa_id', 'sucursal_id', 'activo'],
                'almacenes_empresa_sucursal_activo_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacenes');
    }
};
