<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->restrictOnDelete();

            $table->string('codigo', 30);
            $table->string('nombre', 150);

            $table->string('telefono', 30)->nullable();
            $table->string('correo')->nullable();
            $table->text('direccion')->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('estado', 100)->nullable();
            $table->string('codigo_postal', 10)->nullable();

            $table->boolean('es_principal')
                ->default(false);

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
                'sucursales_empresa_codigo_unique'
            );

            $table->index(
                ['empresa_id', 'activo'],
                'sucursales_empresa_activo_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
