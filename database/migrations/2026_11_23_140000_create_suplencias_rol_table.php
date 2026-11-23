<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para el control de suplencias temporales de rol (ej. Supervisor).
 *
 * Permite promover temporalmente a un colaborador al rol `supervisor` durante
 * vacaciones, incapacidades o ausencias imprevistas, conservando su `rol_anterior`
 * para restaurarlo de forma exacta y auditada al regreso del titular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suplencias_rol', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('supervisor_ausente_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('suplente_id')->constrained('users')->cascadeOnDelete();
            $table->string('rol_anterior', 100);
            $table->string('rol_temporal', 100)->default('supervisor');
            $table->string('motivo', 500);
            $table->date('fecha_inicio');
            $table->date('fecha_fin_estimada')->nullable();
            $table->string('estado', 30)->default('activa')->index();
            $table->foreignId('autorizado_por_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('restaurado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restaurado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suplencias_rol');
    }
};