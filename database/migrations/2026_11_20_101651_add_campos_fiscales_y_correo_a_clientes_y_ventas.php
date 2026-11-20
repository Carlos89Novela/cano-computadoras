<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas de correo electrónico y datos fiscales SAT si aún no existen.
     */
    public function up(): void
    {
        // Agregar campos a la tabla de clientes si hacen falta
        if (Schema::hasTable('clientes')) {
            Schema::table('clientes', function (Blueprint $table): void {
                if (! Schema::hasColumn('clientes', 'email')) {
                    $table->string('email', 150)->nullable()->after('nombre');
                }
                if (! Schema::hasColumn('clientes', 'codigo_postal')) {
                    $table->string('codigo_postal', 10)->nullable()->after('rfc');
                }
                if (! Schema::hasColumn('clientes', 'regimen_fiscal')) {
                    $table->string('regimen_fiscal', 10)->nullable()->after('codigo_postal');
                }
                if (! Schema::hasColumn('clientes', 'uso_cfdi')) {
                    $table->string('uso_cfdi', 10)->nullable()->after('regimen_fiscal');
                }
            });
        }

        // Agregar cliente_email a ventas si la tabla existe y no tiene esa columna
        if (Schema::hasTable('ventas')) {
            Schema::table('ventas', function (Blueprint $table): void {
                if (! Schema::hasColumn('ventas', 'cliente_email')) {
                    $table->string('cliente_email', 150)->nullable()->after('cliente_rfc');
                }
            });
        }
    }

    /**
     * Revierte las columnas agregadas de forma segura.
     */
    public function down(): void
    {
        if (Schema::hasTable('clientes')) {
            Schema::table('clientes', function (Blueprint $table): void {
                foreach (['email', 'codigo_postal', 'regimen_fiscal', 'uso_cfdi'] as $columna) {
                    if (Schema::hasColumn('clientes', $columna)) {
                        $table->dropColumn($columna);
                    }
                }
            });
        }

        if (Schema::hasTable('ventas') && Schema::hasColumn('ventas', 'cliente_email')) {
            Schema::table('ventas', function (Blueprint $table): void {
                $table->dropColumn('cliente_email');
            });
        }
    }
};
