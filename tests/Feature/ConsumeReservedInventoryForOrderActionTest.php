<?php

use App\Actions\Inventario\ConsumirInventarioReservadoParaOrden;
use App\Enums\EstadoOrden;
use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------
// PREPARACIÓN GENERAL
// Carga los roles y permisos antes de cada prueba relacionada
// con consumos reservados vinculados con órdenes.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Crea empresa, almacén, producto, existencia consolidada,
// cliente, equipo, orden y reserva formal activa.
// ---------------------------------------------------------

/**
 * @return array{
 *     0: User,
 *     1: User,
 *     2: Empresa,
 *     3: Sucursal,
 *     4: Almacen,
 *     5: CategoriaProducto,
 *     6: Producto,
 *     7: ExistenciaAlmacen,
 *     8: Equipo,
 *     9: OrdenServicio,
 *     10: ReservaInventario
 * }
 */
function prepararEntornoParaConsumirReservaPorOrden(): array
{
    // ---------------------------------------------------------
    // USUARIOS
    // Crea al responsable de inventario y al cliente dueño
    // del equipo y de la orden.
    // ---------------------------------------------------------

    $responsable = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $responsable->assignRole(
        'administrador'
    );

    $cliente = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    // ---------------------------------------------------------
    // EMPRESA
    // Crea la empresa propietaria del inventario.
    // ---------------------------------------------------------

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // ---------------------------------------------------------
    // SUCURSAL
    // Crea la sucursal donde se encuentra el almacén.
    // ---------------------------------------------------------

    $sucursal = Sucursal::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'MATRIZ',
        'nombre' => 'Sucursal principal',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // ---------------------------------------------------------
    // ALMACÉN
    // Crea el almacén que conserva el producto reservado.
    // ---------------------------------------------------------

    $almacen = Almacen::query()->create([
        'empresa_id' => $empresa->id,
        'sucursal_id' => $sucursal->id,
        'codigo' => 'ALM-MATRIZ',
        'nombre' => 'Almacén principal',
        'tipo' => Almacen::TIPO_PRINCIPAL,
        'es_virtual' => false,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // ---------------------------------------------------------
    // CATEGORÍA
    // Agrupa el producto utilizado en esta prueba.
    // ---------------------------------------------------------

    $categoria = CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento',
        'descripcion' => 'Discos y unidades de almacenamiento.',
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // ---------------------------------------------------------
    // PRODUCTO
    // Crea el producto reservado para la reparación.
    // ---------------------------------------------------------

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => null,
        'sku' => 'SSD-CONSUMIR-ORDEN-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD reservada para consumo',
        'descripcion' => 'Producto utilizado para probar consumos por orden.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-CONSUMIR-ORDEN',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // ---------------------------------------------------------
    // EXISTENCIA CONSOLIDADA
    // Registra veinte unidades físicas y ocho reservadas.
    // ---------------------------------------------------------

    $existencia = ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => '20.0000',
        'cantidad_reservada' => '8.0000',
        'costo_promedio' => '900.0000',
        'actualizado_por_id' => $responsable->id,
    ]);

    // ---------------------------------------------------------
    // EQUIPO
    // Crea el equipo perteneciente al cliente.
    // ---------------------------------------------------------

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Marca de prueba',
        'modelo' => 'Equipo para prueba de consumo de inventario',
        'numero_serie' => 'SERIE-CONSUMIR-ORDEN-001',
        'descripcion' => 'Equipo utilizado en la prueba de consumo.',
    ]);

    // ---------------------------------------------------------
    // ORDEN DE SERVICIO
    // Crea la orden propietaria de la reserva formal.
    // ---------------------------------------------------------

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-CONSUMIR-ORDEN-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    // ---------------------------------------------------------
    // RESERVA FORMAL
    // Vincula ocho unidades reservadas con la orden.
    // ---------------------------------------------------------

    $reserva = ReservaInventario::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'orden_servicio_id' => $orden->id,
        'cantidad_reservada' => '8.0000',
        'cantidad_consumida' => '0.0000',
        'cantidad_liberada' => '0.0000',
        'estado' => ReservaInventario::ESTADO_ACTIVA,
        'motivo' => 'Reserva inicial para reparación.',
        'reservado_por_id' => $responsable->id,
        'actualizado_por_id' => $responsable->id,
        'reservado_at' => now(),
        'finalizado_at' => null,
    ]);

    return [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ];
}

// ---------------------------------------------------------
// CONSUMO PARCIAL POR ORDEN
// Descuenta la existencia física y la cantidad reservada y
// acumula las unidades consumidas en la orden.
// ---------------------------------------------------------

test('authorized user can partially consume reservation for service order', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    $reservaActualizada = app(
        ConsumirInventarioReservadoParaOrden::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        actor: $responsable,
        datos: [
            'cantidad' => '3.0000',
            'motivo' => 'Consumo parcial para reparación autorizada.',
        ]
    );

    // -----------------------------------------------------
    // DETALLE FORMAL
    // La reserva original permanece en ocho unidades, pero
    // tres se registran como consumidas.
    // -----------------------------------------------------

    expect($reservaActualizada->id)
        ->toBe($reserva->id)
        ->and($reservaActualizada->cantidad_reservada)
        ->toBe('8.0000')
        ->and($reservaActualizada->cantidad_consumida)
        ->toBe('3.0000')
        ->and($reservaActualizada->cantidad_liberada)
        ->toBe('0.0000')
        ->and($reservaActualizada->cantidadPendiente())
        ->toBe('5.0000')
        ->and($reservaActualizada->estado)
        ->toBe(ReservaInventario::ESTADO_PARCIAL)
        ->and($reservaActualizada->motivo)
        ->toBe(
            'Consumo parcial para reparación autorizada.'
        )
        ->and($reservaActualizada->actualizado_por_id)
        ->toBe($responsable->id)
        ->and($reservaActualizada->finalizado_at)
        ->toBeNull()
        ->and($reservaActualizada->estaActiva())
        ->toBeTrue()
        ->and($reservaActualizada->estaFinalizada())
        ->toBeFalse();

    // -----------------------------------------------------
    // EXISTENCIA CONSOLIDADA
    // Disminuye tanto la cantidad física como la reservada.
    // El costo promedio se conserva intacto.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('17.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('5.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and($existencia->actualizado_por_id)
        ->toBe($responsable->id);

    // -----------------------------------------------------
    // MOVIMIENTO KARDEX
    // Debe existir un movimiento de salida vinculado con la
    // orden de servicio.
    // -----------------------------------------------------

    $movimiento = MovimientoInventario::query()
        ->where('referencia_tipo', OrdenServicio::class)
        ->where('referencia_id', $orden->id)
        ->first();

    expect($movimiento)
        ->not->toBeNull()
        ->and($movimiento->tipo)
        ->toBe(MovimientoInventario::TIPO_CONSUMO)
        ->and($movimiento->cantidad)
        ->toBe('3.0000')
        ->and($movimiento->existencia_anterior)
        ->toBe('20.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('17.0000');

    // -----------------------------------------------------
    // RELACIONES Y UNICIDAD
    // La reserva formal actualizada conserva sus relaciones
    // y mantiene un solo registro.
    // -----------------------------------------------------

    expect($reservaActualizada->ordenServicio->is($orden))
        ->toBeTrue()
        ->and($reservaActualizada->almacen->is($almacen))
        ->toBeTrue()
        ->and($reservaActualizada->producto->is($producto))
        ->toBeTrue()
        ->and($orden->user->is($cliente))
        ->toBeTrue()
        ->and($orden->equipo->is($equipo))
        ->toBeTrue();

    expect(
        ReservaInventario::query()
            ->where('orden_servicio_id', $orden->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// CONSUMO COMPLETO POR ORDEN
// Consume todas las unidades pendientes, finaliza la reserva
// formal y registra el movimiento correspondiente.
// ---------------------------------------------------------

test('authorized user can completely consume reserved inventory for service order', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    $reservaActualizada = app(
        ConsumirInventarioReservadoParaOrden::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        actor: $responsable,
        datos: [
            'cantidad' => '8.0000',
            'motivo' => 'Consumo completo durante la reparación.',
        ]
    );

    // -----------------------------------------------------
    // RESERVA FORMAL FINALIZADA
    // Las ocho unidades históricamente reservadas quedan
    // completamente registradas como consumidas.
    // -----------------------------------------------------

    expect($reservaActualizada->id)
        ->toBe($reserva->id)
        ->and($reservaActualizada->cantidad_reservada)
        ->toBe('8.0000')
        ->and($reservaActualizada->cantidad_consumida)
        ->toBe('8.0000')
        ->and($reservaActualizada->cantidad_liberada)
        ->toBe('0.0000')
        ->and($reservaActualizada->cantidadPendiente())
        ->toBe('0.0000')
        ->and($reservaActualizada->estado)
        ->toBe(ReservaInventario::ESTADO_CONSUMIDA)
        ->and($reservaActualizada->motivo)
        ->toBe(
            'Consumo completo durante la reparación.'
        )
        ->and($reservaActualizada->actualizado_por_id)
        ->toBe($responsable->id)
        ->and($reservaActualizada->finalizado_at)
        ->not->toBeNull()
        ->and($reservaActualizada->estaActiva())
        ->toBeFalse()
        ->and($reservaActualizada->estaFinalizada())
        ->toBeTrue();

    // -----------------------------------------------------
    // EXISTENCIA CONSOLIDADA
    // La existencia física y la reserva consolidada bajan
    // ocho unidades. La disponibilidad permanece igual.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('12.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and($existencia->actualizado_por_id)
        ->toBe($responsable->id);

    // -----------------------------------------------------
    // MOVIMIENTO DE CONSUMO
    // Comprueba la salida física completa y su referencia
    // directa a la orden de servicio.
    // -----------------------------------------------------

    $movimiento = MovimientoInventario::query()
        ->where(
            'tipo',
            MovimientoInventario::TIPO_CONSUMO
        )
        ->firstOrFail();

    expect($movimiento->empresa_id)
        ->toBe($empresa->id)
        ->and($movimiento->almacen_id)
        ->toBe($almacen->id)
        ->and($movimiento->producto_id)
        ->toBe($producto->id)
        ->and($movimiento->cantidad)
        ->toBe('8.0000')
        ->and($movimiento->existencia_anterior)
        ->toBe('20.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('12.0000')
        ->and($movimiento->costo_unitario)
        ->toBe('900.0000')
        ->and($movimiento->referencia_tipo)
        ->toBe(OrdenServicio::class)
        ->and($movimiento->referencia_id)
        ->toBe($orden->id)
        ->and($movimiento->motivo)
        ->toBe(
            'Consumo completo durante la reparación.'
        )
        ->and($movimiento->usuario_id)
        ->toBe($responsable->id)
        ->and($movimiento->ocurrido_at)
        ->not->toBeNull();

    expect($movimiento->folio)
        ->toStartWith('MOV-')
        ->and(Str::isUuid($movimiento->grupo_id))
        ->toBeTrue();

    // -----------------------------------------------------
    // REGISTROS ÚNICOS
    // El consumo completo actualiza la reserva existente y
    // genera exactamente un movimiento de inventario.
    // -----------------------------------------------------

    expect(
        ReservaInventario::query()
            ->where('orden_servicio_id', $orden->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(1);

    expect($reservaActualizada->ordenServicio->is($orden))
        ->toBeTrue()
        ->and($orden->user->is($cliente))
        ->toBeTrue()
        ->and($orden->equipo->is($equipo))
        ->toBeTrue()
        ->and($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id);
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide consumir una reserva por orden cuando el actor no
// cuenta con el permiso inventario.consumir.
// ---------------------------------------------------------

test('user without permission cannot consume reservation for service order', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole(
        'empleado'
    );

    expect(
        fn () => app(
            ConsumirInventarioReservadoParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $empleado,
            datos: [
                'cantidad' => '3.0000',
                'motivo' => 'Intento de consumo no autorizado.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para consumir inventario reservado.'
    );

    // -----------------------------------------------------
    // EXISTENCIA SIN CAMBIOS
    // La operación rechazada no debe modificar el saldo
    // consolidado del almacén.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');

    // -----------------------------------------------------
    // RESERVA FORMAL SIN CAMBIOS
    // El detalle asociado con la orden tampoco debe recibir
    // un consumo parcial.
    // -----------------------------------------------------

    $reserva->refresh();

    expect($reserva->cantidad_reservada)
        ->toBe('8.0000')
        ->and($reserva->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reserva->cantidad_liberada)
        ->toBe('0.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('8.0000')
        ->and($reserva->estado)
        ->toBe(ReservaInventario::ESTADO_ACTIVA)
        ->and($reserva->finalizado_at)
        ->toBeNull();

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// CONSUMO SUPERIOR A LA CANTIDAD PENDIENTE
// Impide consumir más unidades que las pendientes dentro de
// la reserva específica de la orden.
// ---------------------------------------------------------

test('service order reservation consumption cannot exceed pending quantity', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    // -----------------------------------------------------
    // PROCESAMIENTO PREVIO
    // Simula una reserva con dos unidades consumidas y una
    // liberada. Quedan cinco unidades pendientes.
    // -----------------------------------------------------

    $reserva->update([
        'cantidad_consumida' => '2.0000',
        'cantidad_liberada' => '1.0000',
        'estado' => ReservaInventario::ESTADO_PARCIAL,
        'motivo' => 'Reserva parcialmente procesada.',
    ]);

    expect($reserva->cantidadPendiente())
        ->toBe('5.0000');

    // -----------------------------------------------------
    // INTENTO INVÁLIDO
    // Un consumo de 5.0001 supera las cinco unidades que
    // todavía permanecen pendientes en la reserva.
    // -----------------------------------------------------

    expect(
        fn () => app(
            ConsumirInventarioReservadoParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => '5.0001',
                'motivo' => 'Intento de consumir más de lo pendiente.',
            ]
        )
    )->toThrow(
        ValidationException::class,
        'La cantidad solicitada supera las unidades pendientes de la reserva.'
    );

    // -----------------------------------------------------
    // EXISTENCIA SIN CAMBIOS
    // La validación se ejecuta antes de llamar a la acción
    // consolidada, por lo que no altera el saldo.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');

    // -----------------------------------------------------
    // RESERVA FORMAL SIN CAMBIOS
    // Los acumulados previos deben conservarse intactos.
    // -----------------------------------------------------

    $reserva->refresh();

    expect($reserva->cantidad_reservada)
        ->toBe('8.0000')
        ->and($reserva->cantidad_consumida)
        ->toBe('2.0000')
        ->and($reserva->cantidad_liberada)
        ->toBe('1.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('5.0000')
        ->and($reserva->estado)
        ->toBe(ReservaInventario::ESTADO_PARCIAL)
        ->and($reserva->finalizado_at)
        ->toBeNull();

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// RESERVA FORMAL INEXISTENTE
// Impide consumir unidades cuando la orden no tiene una
// reserva formal para el producto y almacén indicados.
// ---------------------------------------------------------

test('service order must have inventory reservation before consumption', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    // -----------------------------------------------------
    // ELIMINAR DETALLE FORMAL
    // Conserva la existencia consolidada, pero elimina la
    // reserva específica asociada con la orden.
    // -----------------------------------------------------

    $reserva->delete();

    expect(
        fn () => app(
            ConsumirInventarioReservadoParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => '2.0000',
                'motivo' => 'Intento de consumir una reserva inexistente.',
            ]
        )
    )->toThrow(
        ValidationException::class,
        'La orden no tiene una reserva de inventario para el producto y almacén indicados.'
    );

    // -----------------------------------------------------
    // EXISTENCIA CONSOLIDADA SIN CAMBIOS
    // La validación del detalle formal ocurre antes de llamar
    // a la acción base de consumo.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');

    expect(
        ReservaInventario::query()->count()
    )->toBe(0);

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// RESERVA FORMAL FINALIZADA
// Impide consumir nuevamente una reserva que ya no conserva
// unidades pendientes.
// ---------------------------------------------------------

test('finished service order reservation cannot be consumed again', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    // -----------------------------------------------------
    // RESERVA COMPLETAMENTE CONSUMIDA
    // Registra las ocho unidades como consumidas y marca el
    // detalle formal como finalizado.
    // -----------------------------------------------------

    $reserva->update([
        'cantidad_consumida' => '8.0000',
        'estado' => ReservaInventario::ESTADO_CONSUMIDA,
        'motivo' => 'Reserva consumida completamente.',
        'finalizado_at' => now(),
    ]);

    $existencia->update([
        'cantidad' => '12.0000',
        'cantidad_reservada' => '0.0000',
    ]);

    expect($reserva->cantidadPendiente())
        ->toBe('0.0000')
        ->and($reserva->estaFinalizada())
        ->toBeTrue()
        ->and($reserva->estaActiva())
        ->toBeFalse();

    expect(
        fn () => app(
            ConsumirInventarioReservadoParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Intento de consumir nuevamente la reserva.',
            ]
        )
    )->toThrow(
        ValidationException::class,
        'La reserva de inventario ya está finalizada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('12.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');

    $reserva->refresh();

    expect($reserva->cantidad_reservada)
        ->toBe('8.0000')
        ->and($reserva->cantidad_consumida)
        ->toBe('8.0000')
        ->and($reserva->cantidad_liberada)
        ->toBe('0.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('0.0000')
        ->and($reserva->estado)
        ->toBe(ReservaInventario::ESTADO_CONSUMIDA)
        ->and($reserva->finalizado_at)
        ->not->toBeNull();

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// EQUIPO DE OTRO CLIENTE
// Impide consumir una reserva cuando el equipo relacionado no
// pertenece al cliente titular de la orden.
// ---------------------------------------------------------

test('service order equipment must belong to order customer', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    $otroCliente = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $equipoExterno = Equipo::query()->create([
        'user_id' => $otroCliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Marca externa',
        'modelo' => 'Equipo externo',
        'numero_serie' => 'SERIE-EXTERNA-001',
        'descripcion' => 'Equipo perteneciente a otro cliente.',
    ]);

    $orden->update([
        'equipo_id' => $equipoExterno->id,
    ]);

    expect(
        fn () => app(
            ConsumirInventarioReservadoParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden->fresh(),
            actor: $responsable,
            datos: [
                'cantidad' => '3.0000',
                'motivo' => 'Intento de consumo con equipo ajeno.',
            ]
        )
    )->toThrow(
        ValidationException::class,
        'El equipo de la orden no pertenece al cliente indicado.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000');

    $reserva->refresh();

    expect($reserva->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('8.0000');

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ORDEN FINALIZADA
// Impide registrar consumos para órdenes que ya fueron
// entregadas o canceladas.
// ---------------------------------------------------------

test('finished service order cannot consume reserved inventory', function (
    string $estado
) {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    $orden->update([
        'estado' => $estado,
    ]);

    expect(
        fn () => app(
            ConsumirInventarioReservadoParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden->fresh(),
            actor: $responsable,
            datos: [
                'cantidad' => '3.0000',
                'motivo' => 'Intento de consumo en orden finalizada.',
            ]
        )
    )->toThrow(
        ValidationException::class,
        'No se puede consumir inventario para una orden finalizada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000');

    $reserva->refresh();

    expect($reserva->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('8.0000');

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(0);
})->with([
    'orden entregada' => [
        EstadoOrden::ENTREGADO->value,
    ],
    'orden cancelada' => [
        EstadoOrden::CANCELADO->value,
    ],
]);

// ---------------------------------------------------------
// CANTIDAD INVÁLIDA
// El consumo por orden requiere una cantidad numérica mayor
// que cero y con un máximo de cuatro decimales.
// ---------------------------------------------------------

test('service order reservation consumption requires valid positive quantity', function (
    mixed $cantidad
) {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    expect(
        fn () => app(
            ConsumirInventarioReservadoParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => $cantidad,
                'motivo' => 'Validación de cantidad para consumo.',
            ]
        )
    )->toThrow(
        ValidationException::class,
        'La cantidad a consumir debe ser mayor que cero y tener hasta cuatro decimales.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000');

    $reserva->refresh();

    expect($reserva->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('8.0000');

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(0);
})->with([
    'cantidad vacía' => [
        '',
    ],
    'cantidad cero' => [
        '0.0000',
    ],
    'cantidad negativa' => [
        '-1.0000',
    ],
    'cantidad no numérica' => [
        'invalida',
    ],
    'cantidad con más de cuatro decimales' => [
        '1.12345',
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// El consumo requiere un motivo explicativo que no supere
// el límite permitido de mil caracteres.
// ---------------------------------------------------------

test('service order reservation consumption requires valid reason', function (
    mixed $motivo
) {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $equipo,
        $orden,
        $reserva,
    ] = prepararEntornoParaConsumirReservaPorOrden();

    expect(
        fn () => app(
            ConsumirInventarioReservadoParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => '2.0000',
                'motivo' => $motivo,
            ]
        )
    )->toThrow(
        ValidationException::class,
        'Debes indicar un motivo válido de hasta 1000 caracteres.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000');

    $reserva->refresh();

    expect($reserva->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('8.0000');

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(0);
})->with([
    'motivo vacío' => [
        '',
    ],
    'motivo mayor de mil caracteres' => [
        str_repeat('a', 1001),
    ],
]);
