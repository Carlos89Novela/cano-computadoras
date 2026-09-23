<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();

            $table->string('nombre', 150);
            $table->string('razon_social', 200)->nullable();
            $table->string('rfc', 20)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo')->nullable();
            $table->text('direccion_fiscal')->nullable();

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

            $table->timestamps();

            $table->unique(
                'nombre',
                'empresas_nombre_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
