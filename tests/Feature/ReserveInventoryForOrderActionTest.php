<?php

use App\Actions\Inventario\ReservarInventarioParaOrden;
use App\Enums\EstadoOrden;
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
// Carga los roles y permisos antes de cada prueba de reserva
// formal asociada con una orden de servicio.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Crea la empresa, almacén, producto, existencia, cliente,
// equipo y orden necesarios para registrar una reserva.
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
 *     9: OrdenServicio
 * }
 */
function prepararEntornoParaReservaPorOrden(): array
{
    // -----------------------------------------------------
    // USUARIOS
    // Crea al responsable de inventario y al cliente dueño
    // del equipo y de la orden.
    // -----------------------------------------------------

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

    // -----------------------------------------------------
    // EMPRESA
    // Crea la empresa propietaria del inventario.
    // -----------------------------------------------------

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // -----------------------------------------------------
    // SUCURSAL
    // Crea la sucursal donde se encuentra el almacén.
    // -----------------------------------------------------

    $sucursal = Sucursal::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'MATRIZ',
        'nombre' => 'Sucursal principal',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // -----------------------------------------------------
    // ALMACÉN
    // Crea el almacén desde el que se reservará el producto.
    // -----------------------------------------------------

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

    // -----------------------------------------------------
    // CATEGORÍA
    // Agrupa el producto utilizado en la prueba.
    // -----------------------------------------------------

    $categoria = CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento',
        'descripcion' => 'Discos y unidades de almacenamiento.',
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // -----------------------------------------------------
    // PRODUCTO
    // Crea el producto que será comprometido para la orden.
    // -----------------------------------------------------

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => null,
        'sku' => 'SSD-ORDEN-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD para orden',
        'descripcion' => 'Producto utilizado para probar reservas por orden.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-ORDEN',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // -----------------------------------------------------
    // EXISTENCIA
    // Crea el saldo consolidado que será comprometido.
    // -----------------------------------------------------

    $existencia = ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => '20.0000',
        'cantidad_reservada' => '2.0000',
        'costo_promedio' => '900.0000',
        'actualizado_por_id' => $responsable->id,
    ]);

    // -----------------------------------------------------
    // EQUIPO
    // Crea el equipo perteneciente al cliente.
    // -----------------------------------------------------

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Marca de prueba',
        'modelo' => 'Equipo para reserva',
        'numero_serie' => 'SERIE-ORDEN-001',
        'descripcion' => 'Equipo utilizado en la prueba.',
    ]);

    // -----------------------------------------------------
    // ORDEN
    // Crea la orden que requiere el producto reservado.
    // -----------------------------------------------------

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-RESERVA-ORDEN-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
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
    ];
}

// ---------------------------------------------------------
// RESERVA INICIAL POR ORDEN
// Actualiza el saldo consolidado y crea el detalle formal de
// la reserva dentro de una sola operación.
// ---------------------------------------------------------

test('authorized user can reserve inventory for service order', function () {
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
    ] = prepararEntornoParaReservaPorOrden();

    $reserva = app(
        ReservarInventarioParaOrden::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        actor: $responsable,
        datos: [
            'cantidad' => '5.5000',
            'motivo' => 'Reserva para reparación autorizada.',
        ]
    );

    expect($reserva)
        ->toBeInstanceOf(ReservaInventario::class)
        ->and($reserva->empresa_id)
        ->toBe($empresa->id)
        ->and($reserva->almacen_id)
        ->toBe($almacen->id)
        ->and($reserva->producto_id)
        ->toBe($producto->id)
        ->and($reserva->orden_servicio_id)
        ->toBe($orden->id)
        ->and($reserva->cantidad_reservada)
        ->toBe('5.5000')
        ->and($reserva->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reserva->cantidad_liberada)
        ->toBe('0.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('5.5000')
        ->and($reserva->estado)
        ->toBe(ReservaInventario::ESTADO_ACTIVA)
        ->and($reserva->reservado_por_id)
        ->toBe($responsable->id)
        ->and($reserva->actualizado_por_id)
        ->toBe($responsable->id)
        ->and($reserva->finalizado_at)
        ->toBeNull();

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('7.5000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.5000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and($existencia->actualizado_por_id)
        ->toBe($responsable->id);

    expect($reserva->ordenServicio->is($orden))
        ->toBeTrue()
        ->and($reserva->producto->is($producto))
        ->toBeTrue()
        ->and($reserva->almacen->is($almacen))
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
// RESERVA ACUMULADA
// Una segunda reserva para la misma orden, producto y almacén
// actualiza el registro existente sin duplicarlo.
// ---------------------------------------------------------

test('successive service order reservations accumulate', function () {
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
    ] = prepararEntornoParaReservaPorOrden();

    app(
        ReservarInventarioParaOrden::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        actor: $responsable,
        datos: [
            'cantidad' => '3.0000',
            'motivo' => 'Primera reserva.',
        ]
    );

    $reserva = app(
        ReservarInventarioParaOrden::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        actor: $responsable,
        datos: [
            'cantidad' => '2.5000',
            'motivo' => 'Ampliación de la reserva.',
        ]
    );

    expect($reserva->cantidad_reservada)
        ->toBe('5.5000')
        ->and($reserva->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reserva->cantidad_liberada)
        ->toBe('0.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('5.5000')
        ->and($reserva->estado)
        ->toBe(ReservaInventario::ESTADO_ACTIVA)
        ->and($reserva->motivo)
        ->toBe('Ampliación de la reserva.');

    expect(
        ReservaInventario::query()
            ->where('orden_servicio_id', $orden->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('7.5000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.5000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide crear una reserva por orden cuando el actor no
// cuenta con el permiso inventario.consumir.
// ---------------------------------------------------------

test('user without permission cannot reserve inventory for service order', function () {
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
    ] = prepararEntornoParaReservaPorOrden();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole(
        'empleado'
    );

    expect(
        fn () => app(
            ReservarInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $empleado,
            datos: [
                'cantidad' => '3.0000',
                'motivo' => 'Intento de reserva no autorizada.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para reservar inventario.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('2.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('18.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(ReservaInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// EQUIPO DE OTRO CLIENTE
// Impide crear una reserva cuando el equipo relacionado no
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
    ] = prepararEntornoParaReservaPorOrden();

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
            ReservarInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden->fresh(),
            actor: $responsable,
            datos: [
                'cantidad' => '3.0000',
                'motivo' => 'Intento de reserva con equipo externo.',
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
        ->toBe('2.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('18.0000')
        ->and(ReservaInventario::query()->count())
        ->toBe(0)
        ->and($orden->fresh()?->user_id)
        ->toBe($cliente->id)
        ->and($equipo->user_id)
        ->toBe($cliente->id);
});

// ---------------------------------------------------------
// DISPONIBILIDAD INSUFICIENTE
// Impide reservar para una orden una cantidad superior al
// saldo disponible y revierte toda la operación.
// ---------------------------------------------------------

test('service order reservation cannot exceed available stock', function () {
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
    ] = prepararEntornoParaReservaPorOrden();

    // Existencia disponible:
    // 20.0000 - 2.0000 = 18.0000
    expect(
        fn () => app(
            ReservarInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => '18.0001',
                'motivo' => 'Intento de reservar más del disponible.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('2.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('18.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(ReservaInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// CANTIDAD INVÁLIDA
// Reutiliza las validaciones de la reserva consolidada y
// evita crear detalles incompletos para la orden.
// ---------------------------------------------------------

test('service order reservation requires valid positive quantity', function (
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
    ] = prepararEntornoParaReservaPorOrden();

    expect(
        fn () => app(
            ReservarInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => $cantidad,
                'motivo' => 'Validación de cantidad para la orden.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('2.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('18.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(ReservaInventario::query()->count())
        ->toBe(0);
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
        'cinco',
    ],
    'cantidad con más de cuatro decimales' => [
        '1.12345',
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Toda reserva por orden necesita una justificación válida
// y no debe modificar ningún saldo si la validación falla.
// ---------------------------------------------------------

test('service order reservation requires valid reason', function (
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
    ] = prepararEntornoParaReservaPorOrden();

    expect(
        fn () => app(
            ReservarInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            actor: $responsable,
            datos: [
                'cantidad' => '3.0000',
                'motivo' => $motivo,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('2.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('18.0000')
        ->and(ReservaInventario::query()->count())
        ->toBe(0);
})->with([
    'motivo vacío' => [
        '   ',
    ],
    'motivo mayor de mil caracteres' => [
        str_repeat('A', 1001),
    ],
]);

// ---------------------------------------------------------
// ORDEN FINALIZADA
// Una orden entregada o cancelada no puede recibir nuevas
// reservas de inventario.
// ---------------------------------------------------------

test('finished service order cannot receive inventory reservation', function (
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
    ] = prepararEntornoParaReservaPorOrden();

    $orden->update([
        'estado' => $estado,
    ]);

    expect(
        fn () => app(
            ReservarInventarioParaOrden::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden->fresh(),
            actor: $responsable,
            datos: [
                'cantidad' => '3.0000',
                'motivo' => 'Intento de reservar para una orden finalizada.',
            ]
        )
    )->toThrow(
        ValidationException::class,
        'No se puede reservar inventario para una orden finalizada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('2.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('18.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(ReservaInventario::query()->count())
        ->toBe(0);
})->with([
    'orden entregada' => [
        EstadoOrden::ENTREGADO->value,
    ],
    'orden cancelada' => [
        EstadoOrden::CANCELADO->value,
    ],
]);

// ---------------------------------------------------------
// REAPERTURA DE RESERVA LIBERADA
// Una reserva completamente liberada puede recibir nuevas
// unidades sin perder su historial de liberación.
// ---------------------------------------------------------

test('fully released reservation can be reopened with new quantity', function () {
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
    ] = prepararEntornoParaReservaPorOrden();

    // -----------------------------------------------------
    // RESERVA FINALIZADA PREVIA
    // Simula una reserva de cinco unidades que fue liberada
    // completamente antes de recibir la nueva ampliación.
    // -----------------------------------------------------

    $reservaExistente = ReservaInventario::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'orden_servicio_id' => $orden->id,
        'cantidad_reservada' => '5.0000',
        'cantidad_consumida' => '0.0000',
        'cantidad_liberada' => '5.0000',
        'estado' => ReservaInventario::ESTADO_LIBERADA,
        'motivo' => 'Reserva anterior completamente liberada.',
        'reservado_por_id' => $responsable->id,
        'actualizado_por_id' => $responsable->id,
        'reservado_at' => now()->subDay(),
        'finalizado_at' => now()->subHour(),
    ]);

    expect($reservaExistente->cantidadPendiente())
        ->toBe('0.0000')
        ->and($reservaExistente->estaFinalizada())
        ->toBeTrue()
        ->and($reservaExistente->estaActiva())
        ->toBeFalse();

    // -----------------------------------------------------
    // NUEVA RESERVA
    // Agrega tres unidades sobre el mismo registro formal.
    // La reserva total histórica pasa de cinco a ocho.
    // -----------------------------------------------------

    $reservaActualizada = app(
        ReservarInventarioParaOrden::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        actor: $responsable,
        datos: [
            'cantidad' => '3.0000',
            'motivo' => 'Nuevas unidades requeridas para la reparación.',
        ]
    );

    // -----------------------------------------------------
    // DETALLE FORMAL
    // Conserva las cinco unidades liberadas y deja tres
    // unidades nuevamente pendientes.
    // -----------------------------------------------------

    expect($reservaActualizada->id)
        ->toBe($reservaExistente->id)
        ->and($reservaActualizada->cantidad_reservada)
        ->toBe('8.0000')
        ->and($reservaActualizada->cantidad_consumida)
        ->toBe('0.0000')
        ->and($reservaActualizada->cantidad_liberada)
        ->toBe('5.0000')
        ->and($reservaActualizada->cantidadPendiente())
        ->toBe('3.0000')
        ->and($reservaActualizada->estado)
        ->toBe(ReservaInventario::ESTADO_PARCIAL)
        ->and($reservaActualizada->motivo)
        ->toBe(
            'Nuevas unidades requeridas para la reparación.'
        )
        ->and($reservaActualizada->reservado_por_id)
        ->toBe($responsable->id)
        ->and($reservaActualizada->actualizado_por_id)
        ->toBe($responsable->id)
        ->and($reservaActualizada->finalizado_at)
        ->toBeNull()
        ->and($reservaActualizada->estaFinalizada())
        ->toBeFalse()
        ->and($reservaActualizada->estaActiva())
        ->toBeTrue();

    // -----------------------------------------------------
    // SALDO CONSOLIDADO
    // La existencia física y el costo permanecen iguales.
    // Solo aumenta la cantidad reservada consolidada.
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
    // SIN DUPLICADOS
    // Debe seguir existiendo un solo registro para la misma
    // orden, almacén y producto.
    // -----------------------------------------------------

    expect(
        ReservaInventario::query()
            ->where('orden_servicio_id', $orden->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);
});
