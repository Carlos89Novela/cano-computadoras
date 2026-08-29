<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orden_servicios', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->foreignId('equipo_id')->constrained()->onDelete('cascade');

            $table->text('problema_reportado');

            $table->text('diagnostico')->nullable();

            $table->decimal('costo_estimado', 10, 2)->nullable();
            $table->decimal('costo_final',10,2)->nullable();

            $table->string('estado')->default('Recibido');
            $table->date('fecha_ingreso');
            $table->date('fecha_entrega')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_servicios');
    }
};
