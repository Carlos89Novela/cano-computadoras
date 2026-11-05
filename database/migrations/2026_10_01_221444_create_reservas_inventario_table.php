<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ---------------------------------------------------------
    // CREAR RESERVAS DE INVENTARIO
    // Relaciona las unidades comprometidas de un producto con
    // una orden de servicio específica.
    // ---------------------------------------------------------

    public function up(): void
    {
        Schema::create(
            'reservas_inventario',
            function (Blueprint $table): void {
                $table->id();

                // ---------------------------------------------
                // EMPRESA
                // Mantiene el aislamiento multiempresa de la
                // reserva y facilita consultas administrativas.
                // ---------------------------------------------

                $table->foreignId('empresa_id')
                    ->constrained('empresas')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // ALMACÉN
                // Identifica el almacén que conserva físicamente
                // las unidades comprometidas.
                // ---------------------------------------------

                $table->foreignId('almacen_id')
                    ->constrained('almacenes')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // PRODUCTO
                // Identifica el producto reservado para la
                // orden de servicio.
                // ---------------------------------------------

                $table->foreignId('producto_id')
                    ->constrained('productos')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // ORDEN DE SERVICIO
                // Relaciona la reserva con la reparación que
                // requiere las unidades comprometidas.
                // ---------------------------------------------

                $table->foreignId(
                    'orden_servicio_id'
                )
                    ->constrained('orden_servicios')
                    ->restrictOnDelete();

                // ---------------------------------------------
                // CANTIDAD ORIGINALMENTE RESERVADA
                // Conserva la cantidad total comprometida para
                // la orden, incluso después de finalizarla.
                // ---------------------------------------------

                $table->decimal(
                    'cantidad_reservada',
                    18,
                    4
                );

                // ---------------------------------------------
                // CANTIDAD CONSUMIDA
                // Acumula las unidades que ya fueron retiradas
                // físicamente para utilizarse en la reparación.
                // ---------------------------------------------

                $table->decimal(
                    'cantidad_consumida',
                    18,
                    4
                )->default(0);

                // ---------------------------------------------
                // CANTIDAD LIBERADA
                // Acumula las unidades que dejaron de estar
                // comprometidas y regresaron a disponibilidad.
                // ---------------------------------------------

                $table->decimal(
                    'cantidad_liberada',
                    18,
                    4
                )->default(0);

                // ---------------------------------------------
                // ESTADO
                // Resume la condición operativa de la reserva:
                // activa, parcial, consumida o liberada.
                // ---------------------------------------------

                $table->string(
                    'estado',
                    30
                )->default('activa');

                // ---------------------------------------------
                // MOTIVO
                // Conserva la justificación original de la
                // reserva asociada con la orden.
                // ---------------------------------------------

                $table->text('motivo');

                // ---------------------------------------------
                // USUARIO QUE RESERVÓ
                // Identifica al usuario responsable de crear el
                // compromiso inicial de inventario.
                // ---------------------------------------------

                $table->foreignId(
                    'reservado_por_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                // ---------------------------------------------
                // ÚLTIMO USUARIO ACTUALIZADOR
                // Identifica quién realizó el consumo o la
                // liberación más reciente.
                // ---------------------------------------------

                $table->foreignId(
                    'actualizado_por_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                // ---------------------------------------------
                // FECHAS OPERATIVAS
                // Separa el momento de la reserva y su cierre
                // de las marcas técnicas del registro.
                // ---------------------------------------------

                $table->timestamp(
                    'reservado_at'
                )->useCurrent();

                $table->timestamp(
                    'finalizado_at'
                )->nullable();

                $table->timestamps();

                // ---------------------------------------------
                // RESERVA ÚNICA POR ORDEN Y UBICACIÓN
                // Una orden solo mantiene un registro acumulado
                // por producto y almacén.
                // ---------------------------------------------

                $table->unique(
                    [
                        'orden_servicio_id',
                        'almacen_id',
                        'producto_id',
                    ],
                    'reservas_orden_almacen_producto_unique'
                );

                // ---------------------------------------------
                // CONSULTA POR ORDEN
                // Permite recuperar rápidamente todos los
                // productos reservados para una reparación.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'orden_servicio_id',
                    ],
                    'reservas_empresa_orden_index'
                );

                // ---------------------------------------------
                // CONSULTA POR ALMACÉN Y ESTADO
                // Facilita identificar las reservas activas que
                // comprometen existencias de un almacén.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'almacen_id',
                        'estado',
                    ],
                    'reservas_empresa_almacen_estado_index'
                );

                // ---------------------------------------------
                // CONSULTA POR PRODUCTO
                // Permite determinar en qué órdenes se encuentra
                // comprometido un producto determinado.
                // ---------------------------------------------

                $table->index(
                    [
                        'empresa_id',
                        'producto_id',
                        'estado',
                    ],
                    'reservas_empresa_producto_estado_index'
                );
            }
        );
    }

    // ---------------------------------------------------------
    // REVERTIR RESERVAS
    // Elimina la tabla durante una reversión controlada de
    // esta migración.
    // ---------------------------------------------------------

    public function down(): void
    {
        Schema::dropIfExists(
            'reservas_inventario'
        );
    }
};
