<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ---------------------------------------------------------
    // CREAR RECEPCIONES DE INVENTARIO
    // Registra el encabezado de cada recepción de productos.
    // Una recepción puede contener una o varias líneas.
    // ---------------------------------------------------------

    public function up(): void
    {
        Schema::create(
            'recepciones_inventario',
            function (Blueprint $table): void {
                $table->id();

                // ---------------------------------------------
                // EMPRESA
                // Delimita la operación dentro del contexto
                // multiempresa.
                // ---------------------------------------------

                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // ALMACÉN DE DESTINO
                // Identifica el almacén que recibirá todas las
                // líneas incluidas en el documento.
                // ---------------------------------------------

                $table->foreignId('almacen_id')
                    ->constrained('almacenes')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // PROVEEDOR
                // Es obligatorio para compras y opcional para
                // recepciones, devoluciones o ajustes.
                // ---------------------------------------------

                $table->foreignId('proveedor_id')
                    ->nullable()
                    ->constrained('proveedores')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // GRUPO DE MOVIMIENTOS
                // Todos los movimientos creados por esta
                // recepción compartirán el mismo UUID.
                // ---------------------------------------------

                $table->uuid('grupo_id');

                // ---------------------------------------------
                // FOLIO INTERNO
                // Identificador legible y único dentro de la
                // empresa.
                // ---------------------------------------------

                $table->string(
                    'folio',
                    60
                );

                // ---------------------------------------------
                // TIPO DE RECEPCIÓN
                // Distingue el origen administrativo de las
                // unidades recibidas.
                // ---------------------------------------------

                $table->string(
                    'tipo',
                    40
                )->index();

                // ---------------------------------------------
                // REFERENCIA EXTERNA
                // Puede contener factura, remisión, orden de
                // compra, devolución u otro comprobante.
                // ---------------------------------------------

                $table->string(
                    'referencia_externa',
                    150
                )->nullable();

                // ---------------------------------------------
                // MOTIVO
                // Describe el origen o justificación general
                // de la recepción.
                // ---------------------------------------------

                $table->text('motivo');

                // ---------------------------------------------
                // TOTAL DE LÍNEAS
                // Conserva la cantidad de productos distintos
                // incluidos en el documento.
                // ---------------------------------------------

                $table->unsignedInteger(
                    'total_lineas'
                )->default(0);

                // ---------------------------------------------
                // IMPORTE TOTAL
                // Suma los subtotales de todas las líneas.
                // Utiliza cuatro decimales para conservar la
                // precisión de cantidades y costos.
                // ---------------------------------------------

                $table->decimal(
                    'importe_total',
                    18,
                    4
                )->default(0);

                // ---------------------------------------------
                // USUARIO RECEPTOR
                // Registra quién confirmó la operación.
                // ---------------------------------------------

                $table->foreignId('recibido_por_id')
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                // ---------------------------------------------
                // FECHA OPERATIVA
                // Indica cuándo se recibió físicamente el
                // inventario.
                // ---------------------------------------------

                $table->timestamp(
                    'recibido_at'
                )->index();

                // ---------------------------------------------
                // MARCAS TÉCNICAS
                // Conservan la creación y actualización del
                // encabezado administrativo.
                // ---------------------------------------------

                $table->timestamps();

                // ---------------------------------------------
                // FOLIO ÚNICO POR EMPRESA
                // Permite folios iguales en empresas diferentes,
                // pero no dos veces dentro de la misma empresa.
                // ---------------------------------------------

                $table->unique(
                    [
                        'empresa_id',
                        'folio',
                    ],
                    'recepciones_empresa_folio_unique'
                );

                // ---------------------------------------------
                // GRUPO ÚNICO POR EMPRESA
                // Evita que dos recepciones de una empresa
                // compartan accidentalmente el mismo grupo.
                // ---------------------------------------------

                $table->unique(
                    [
                        'empresa_id',
                        'grupo_id',
                    ],
                    'recepciones_empresa_grupo_unique'
                );

                // ---------------------------------------------
                // REFERENCIA EXTERNA
                // Optimiza búsquedas por facturas, remisiones
                // u otros comprobantes.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'referencia_externa',
                    ],
                    'recepciones_empresa_referencia_index'
                );

                // ---------------------------------------------
                // HISTORIAL POR ALMACÉN
                // Facilita consultar cronológicamente las
                // recepciones de cada almacén.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'almacen_id',
                        'recibido_at',
                    ],
                    'recepciones_almacen_fecha_index'
                );

                // ---------------------------------------------
                // HISTORIAL POR PROVEEDOR
                // Facilita consultar las compras y recepciones
                // vinculadas con un proveedor.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'proveedor_id',
                        'recibido_at',
                    ],
                    'recepciones_proveedor_fecha_index'
                );
            }
        );
    }

    // ---------------------------------------------------------
    // REVERTIR RECEPCIONES DE INVENTARIO
    // Elimina el encabezado durante una reversión controlada.
    // La migración de detalles deberá revertirse primero.
    // ---------------------------------------------------------

    public function down(): void
    {
        Schema::dropIfExists(
            'recepciones_inventario'
        );
    }
};
