<?php

use App\Actions\Inventario\LiberarReservaInventarioParaOrden;
use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\ExistenciaAlmacen;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------
// PREPARACIÓN GENERAL
// Carga los roles y permisos antes de cada prueba relacionada
// con liberaciones de reservas vinculadas con órdenes.
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
function prepararEntornoParaLiberarReservaPorOrden(): array
{
    // ---------------------------------------------------------
    // USUARIOS
    // Crea al responsable de inventario y al cliente dueño
    // del equipo y de la orden de servicio.
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
    // Crea la sucursal que contiene el almacén.
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
    // Crea el almacén vinculado con la reserva formal.
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
    // Agrupa el producto utilizado en la prueba.
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
    // Crea el producto reservado para la orden.
    // ---------------------------------------------------------

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => null,
        'sku' => 'SSD-LIBERAR-ORDEN-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD reservada para orden',
        'descripcion' => 'Producto utilizado para probar liberaciones por orden.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-LIBERAR-ORDEN',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // ---------------------------------------------------------
    // EXISTENCIA CONSOLIDADA
    // Registra veinte unidades físicas, de las cuales ocho
    // están reservadas en total.
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
        'modelo' => 'Equipo para liberación',
        'numero_serie' => 'SERIE-LIBERAR-ORDEN-001',
        'descripcion' => 'Equipo utilizado en la prueba de liberación.',
    ]);

    // ---------------------------------------------------------
    // ORDEN DE SERVICIO
    // Crea la orden propietaria de la reserva formal.
    // ---------------------------------------------------------

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-LIBERAR-ORDEN-001',
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
// LIBERACIÓN PARCIAL POR ORDEN
// Reduce la reserva consolidada y acumula la cantidad
// liberada en el detalle formal de la orden.
// ---------------------------------------------------------

test('authorized user can partially release reservation for service order', function () {
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
    ] = prepararEntornoParaLiberarReservaPorOrden();

    $reservaActualizada = app(
        LiberarReservaInventarioParaOrden::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        actor: $responsable,
        datos: [
            'cantidad' => '3.0000',
            'motivo' => 'Liberación parcial por cambio de reparación.',
        ]
    );

    // -----------------------------------------------------
    // DETALLE FORMAL
    // La reserva original permanece en ocho unidades, pero
    // tres se registran como liberadas.
    // -----------------------------------------------------

    expect($reservaActualizada->id)
        ->toBe($reserva->id)
        ->and($reservaActualizada->cantidad_reservada)
        ->toBe('8.0000')
        ->and($reservaActualizada->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reservaActualizada->cantidad_liberada)
        ->toBe('3.0000')
        ->and($reservaActualizada->cantidadPendiente())
        ->toBe('5.0000')
        ->and($reservaActualizada->estado)
        ->toBe(ReservaInventario::ESTADO_PARCIAL)
        ->and($reservaActualizada->motivo)
        ->toBe(
            'Liberación parcial por cambio de reparación.'
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
    // La cantidad física y el costo promedio se conservan.
    // La cantidad reservada disminuye en tres unidades.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('5.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('15.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and($existencia->actualizado_por_id)
        ->toBe($responsable->id);

    // -----------------------------------------------------
    // RELACIONES Y UNICIDAD
    // La liberación actualiza el registro existente sin crear
    // una segunda reserva para la misma combinación.
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
        ->toBeTrue()
        ->and($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id);

    expect(
        ReservaInventario::query()
            ->where('orden_servicio_id', $orden->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// LIBERACIÓN COMPLETA POR ORDEN
// Libera todas las unidades pendientes, finaliza la reserva
// formal y restablece la disponibilidad consolidada.
// ---------------------------------------------------------

test('authorized user can completely release reservation for service order', function () {
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
    ] = prepararEntornoParaLiberarReservaPorOrden();

    $reservaActualizada = app(
        LiberarReservaInventarioParaOrden::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        actor: $responsable,
        datos: [
            'cantidad' => '8.0000',
            'motivo' => 'Liberación completa por cancelación de piezas.',
        ]
    );

    // -----------------------------------------------------
    // DETALLE FORMAL FINALIZADO
    // La cantidad histórica permanece en ocho unidades y
    // todas quedan registradas como liberadas.
    // -----------------------------------------------------

    expect($reservaActualizada->id)
        ->toBe($reserva->id)
        ->and($reservaActualizada->cantidad_reservada)
        ->toBe('8.0000')
        ->and($reservaActualizada->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reservaActualizada->cantidad_liberada)
        ->toBe('8.0000')
        ->and($reservaActualizada->cantidadPendiente())
        ->toBe('0.0000')
        ->and($reservaActualizada->estado)
        ->toBe(ReservaInventario::ESTADO_LIBERADA)
        ->and($reservaActualizada->motivo)
        ->toBe(
            'Liberación completa por cancelación de piezas.'
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
    // La existencia física y el costo promedio permanecen
    // iguales; la reserva consolidada vuelve a cero.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('20.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and($existencia->actualizado_por_id)
        ->toBe($responsable->id);

    // -----------------------------------------------------
    // REGISTRO ÚNICO
    // La liberación completa actualiza la reserva existente
    // sin generar registros adicionales.
    // -----------------------------------------------------

    expect(
        ReservaInventario::query()
            ->where('orden_servicio_id', $orden->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide liberar una reserva por orden cuando el actor no
// cuenta con el permiso inventario.consumir.
// ---------------------------------------------------------

test('user without permission cannot release reservation for service order', function () {
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
    ] = prepararEntornoParaLiberarReservaPorOrden();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole(
        'empleado'
    );

    expect(
        fn () => app(
            LiberarReservaInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $empleado,
            datos: [
                'cantidad' => '3.0000',
                'motivo' => 'Intento de liberación no autorizada.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para liberar reservas de inventario.'
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
    // una liberación parcial.
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
});

// ---------------------------------------------------------
// LIBERACIÓN SUPERIOR A LA PENDIENTE
// Impide liberar más unidades que las pendientes dentro de
// la reserva específica de la orden.
// ---------------------------------------------------------

test('service order reservation release cannot exceed pending quantity', function () {
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
    ] = prepararEntornoParaLiberarReservaPorOrden();

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
    // Una liberación de 5.0001 supera las cinco unidades
    // que todavía permanecen pendientes.
    // -----------------------------------------------------

    expect(
        fn () => app(
            LiberarReservaInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => '5.0001',
                'motivo' => 'Intento de liberar más de lo pendiente.',
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
});

// ---------------------------------------------------------
// RESERVA FORMAL INEXISTENTE
// Impide liberar unidades cuando la orden no tiene una
// reserva formal para el producto y almacén indicados.
// ---------------------------------------------------------

test('service order must have inventory reservation before release', function () {
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
    ] = prepararEntornoParaLiberarReservaPorOrden();

    // -----------------------------------------------------
    // ELIMINAR DETALLE FORMAL
    // Conserva la existencia consolidada, pero elimina la
    // reserva específica asociada con la orden.
    // -----------------------------------------------------

    $reserva->delete();

    expect(
        fn () => app(
            LiberarReservaInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => '2.0000',
                'motivo' => 'Intento de liberar una reserva inexistente.',
            ]
        )
    )->toThrow(
        ValidationException::class,
        'La orden no tiene una reserva de inventario para el producto y almacén indicados.'
    );

    // -----------------------------------------------------
    // EXISTENCIA CONSOLIDADA SIN CAMBIOS
    // La validación del detalle formal ocurre antes de llamar
    // a la acción base de liberación.
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
});

// ---------------------------------------------------------
// RESERVA FORMAL FINALIZADA
// Impide liberar nuevamente una reserva que ya no conserva
// unidades pendientes.
// ---------------------------------------------------------

test('finished service order reservation cannot be released again', function () {
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
    ] = prepararEntornoParaLiberarReservaPorOrden();

    // -----------------------------------------------------
    // RESERVA COMPLETAMENTE LIBERADA
    // Registra las ocho unidades como liberadas y marca el
    // detalle formal como finalizado.
    // -----------------------------------------------------

    $reserva->update([
        'cantidad_liberada' => '8.0000',
        'estado' => ReservaInventario::ESTADO_LIBERADA,
        'motivo' => 'Reserva liberada completamente.',
        'finalizado_at' => now(),
    ]);

    // -----------------------------------------------------
    // SALDO CONSOLIDADO COHERENTE
    // Como la reserva formal se encuentra liberada, el saldo
    // consolidado tampoco conserva unidades reservadas.
    // -----------------------------------------------------

    $existencia->update([
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
            LiberarReservaInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Intento de liberar nuevamente la reserva.',
            ]
        )
    )->toThrow(
        ValidationException::class,
        'La reserva de inventario ya está finalizada.'
    );

    // -----------------------------------------------------
    // EXISTENCIA SIN CAMBIOS
    // El intento rechazado no debe alterar la cantidad física
    // ni la reserva consolidada.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('20.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');

    // -----------------------------------------------------
    // DETALLE FORMAL SIN CAMBIOS
    // La reserva debe conservar su estado y fecha de cierre.
    // -----------------------------------------------------

    $reserva->refresh();

    expect($reserva->cantidad_reservada)
        ->toBe('8.0000')
        ->and($reserva->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reserva->cantidad_liberada)
        ->toBe('8.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('0.0000')
        ->and($reserva->estado)
        ->toBe(ReservaInventario::ESTADO_LIBERADA)
        ->and($reserva->finalizado_at)
        ->not->toBeNull();
});

// ---------------------------------------------------------
// CANTIDAD INVÁLIDA
// La liberación por orden requiere una cantidad numérica
// mayor que cero y con un máximo de cuatro decimales.
// ---------------------------------------------------------

test('service order reservation release requires valid positive quantity', function (
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
    ] = prepararEntornoParaLiberarReservaPorOrden();

    expect(
        fn () => app(
            LiberarReservaInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => $cantidad,
                'motivo' => 'Validación de cantidad para liberación.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    // -----------------------------------------------------
    // EXISTENCIA CONSOLIDADA SIN CAMBIOS
    // Una cantidad inválida no debe modificar el saldo del
    // producto dentro del almacén.
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
    // El detalle de la orden debe conservar sus cantidades
    // y su estado original.
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
})->with([
    'cantidad vacía' => [
        '   ',
    ],
    'cantidad cero' => [
        '0',
    ],
    'cantidad negativa' => [
        '-1.0000',
    ],
    'cantidad no numérica' => [
        'tres',
    ],
    'cantidad con más de cuatro decimales' => [
        '1.12345',
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Toda liberación por orden necesita una justificación
// válida de hasta 1000 caracteres.
// ---------------------------------------------------------

test('service order reservation release requires valid reason', function (
    string $motivo
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
    ] = prepararEntornoParaLiberarReservaPorOrden();

    expect(
        fn () => app(
            LiberarReservaInventarioParaOrden::class
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
        ValidationException::class
    );

    // -----------------------------------------------------
    // EXISTENCIA CONSOLIDADA SIN CAMBIOS
    // La validación del motivo ocurre dentro de la operación
    // atómica y cualquier cambio debe revertirse.
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
    // El motivo inválido no debe acumular unidades liberadas.
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
})->with([
    'motivo vacío' => [
        '   ',
    ],
    'motivo mayor de mil caracteres' => [
        str_repeat('A', 1001),
    ],
]);
