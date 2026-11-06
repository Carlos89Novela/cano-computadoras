<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Turnos / Cortes de Caja
        Schema::create('caja_turnos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('Cajero responsable')->constrained('users')->restrictOnDelete();

            $table->timestamp('apertura_at');
            $table->timestamp('cierre_at')->nullable();

            $table->decimal('monto_inicial', 12, 2)->default(0);
            $table->decimal('total_ventas_efectivo', 12, 2)->default(0);
            $table->decimal('total_ventas_tarjeta', 12, 2)->default(0);
            $table->decimal('total_ventas_transferencia', 12, 2)->default(0);
            $table->decimal('total_otros_ingresos', 12, 2)->default(0);
            $table->decimal('total_retiros', 12, 2)->default(0);

            $table->decimal('monto_esperado_efectivo', 12, 2)->default(0);
            $table->decimal('monto_real_efectivo', 12, 2)->nullable();
            $table->decimal('diferencia_efectivo', 12, 2)->nullable();

            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->text('observaciones_apertura')->nullable();
            $table->text('observaciones_cierre')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'estado']);
        });

        // 2. Ventas de Mostrador (POS y Auditoría)
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Identificador único para operaciones offline y sincronización');
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('caja_turno_id')->nullable()->constrained('caja_turnos')->nullOnDelete();
            $table->foreignId('user_id')->comment('Vendedor/Cajero')->constrained('users')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('folio', 35)->unique();
            $table->string('cliente_nombre')->default('Público General');
            $table->string('cliente_rfc', 13)->default('XAXX010101000');
            $table->string('cliente_email')->nullable();
            $table->string('cliente_telefono', 20)->nullable();
            $table->string('cliente_regimen_fiscal', 10)->nullable();
            $table->string('cliente_codigo_postal', 10)->nullable();

            // Importes comerciales y fiscales
            $table->decimal('subtotal_sin_impuestos', 12, 4)->default(0);
            $table->decimal('descuento_total', 12, 4)->default(0);
            $table->decimal('base_imponible', 12, 4)->default(0);
            $table->decimal('iva_total', 12, 4)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('costo_total_inventario', 12, 4)->default(0)->comment('Suma del costo ponderado de salida para margen y utilidad');

            // Parámetros fiscales SAT (para uso local o timbrado directo)
            $table->string('forma_pago_sat', 5)->default('01')->comment('01 Efectivo, 03 Transferencia, 04 Tarjeta, etc.');
            $table->string('metodo_pago_sat', 5)->default('PUE')->comment('PUE o PPD');
            $table->string('uso_cfdi_sat', 5)->default('S01')->comment('G01, G03, S01 Sin efectos fiscales');

            // Estados operativos, sincronización y facturación
            $table->enum('estado', ['completada', 'cancelada', 'reembolsada'])->default('completada');
            $table->enum('origen_registro', ['online', 'offline'])->default('online');
            $table->timestamp('sincronizado_at')->nullable();

            $table->enum('estado_facturacion', ['no_facturada', 'pendiente', 'facturada', 'facturada_externa', 'cancelada_sat'])->default('no_facturada');
            $table->string('uuid_fiscal', 36)->nullable()->index()->comment('UUID oficial del SAT');
            $table->string('serie_folio_factura', 30)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('pdf_factura_path')->nullable();
            $table->timestamp('facturado_at')->nullable();

            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'sucursal_id', 'created_at']);
            $table->index(['empresa_id', 'estado_facturacion']);
            $table->index(['origen_registro', 'sincronizado_at']);
        });

        // 3. Detalles de Líneas de Venta
        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();

            $table->string('codigo_barras')->nullable();
            $table->string('sku', 50)->nullable();
            $table->string('descripcion_producto');
            $table->string('clave_sat_producto', 15)->nullable()->default('01010101');
            $table->string('clave_sat_unidad', 10)->nullable()->default('H87');

            $table->decimal('cantidad', 12, 4)->default(1);
            $table->decimal('costo_unitario_promedio', 12, 4)->default(0)->comment('Para auditoría de costo y margen');
            $table->decimal('precio_unitario', 12, 4)->default(0);
            $table->decimal('descuento_unitario', 12, 4)->default(0);

            $table->decimal('tasa_iva', 6, 4)->default(0.1600);
            $table->decimal('monto_iva_unitario', 12, 4)->default(0);

            $table->decimal('subtotal', 12, 4)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });

        // 4. Pagos Recibidos (Multipago y arqueo)
        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('caja_turno_id')->nullable()->constrained('caja_turnos')->nullOnDelete();

            $table->string('forma_pago_sat', 5)->default('01');
            $table->decimal('monto', 12, 2);
            $table->decimal('monto_recibido', 12, 2)->nullable();
            $table->decimal('cambio_devuelto', 12, 2)->nullable();
            $table->string('referencia_bancaria', 50)->nullable();
            $table->timestamps();
        });

        // 5. Configuración Fiscal y Bolsa de Timbres por Empresa
        Schema::create('empresa_configuraciones_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();

            $table->boolean('facturacion_activa')->default(false);
            $table->enum('modalidad_facturacion', ['servicio_interno', 'externo_manual'])->default('externo_manual');

            // Datos fiscales oficiales
            $table->string('rfc', 13)->nullable();
            $table->string('razon_social')->nullable();
            $table->string('regimen_fiscal', 10)->nullable();
            $table->string('codigo_postal', 10)->nullable();

            // Llaves CSD (cifradas en disco)
            $table->text('cer_path')->nullable();
            $table->text('key_path')->nullable();
            $table->text('password_csd_encrypted')->nullable();

            // Monedero de timbres
            $table->unsignedInteger('timbres_contratados')->default(0);
            $table->unsignedInteger('timbres_utilizados')->default(0);
            $table->unsignedInteger('timbres_restantes')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_configuraciones_fiscales');
        Schema::dropIfExists('venta_pagos');
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('caja_turnos');
    }
};
