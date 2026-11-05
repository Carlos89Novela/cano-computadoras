<?php

use App\Actions\Inventario\LiberarReservaInventario;
use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\Producto;
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
// con la liberación de reservas de inventario.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Crea administrador, empresa, sucursal, almacén, producto
// y existencia con unidades previamente reservadas.
// ---------------------------------------------------------

/**
 * @return array{
 *     0: User,
 *     1: Empresa,
 *     2: Sucursal,
 *     3: Almacen,
 *     4: CategoriaProducto,
 *     5: Producto,
 *     6: ExistenciaAlmacen
 * }
 */
function prepararEntornoParaLiberarReservaInventario(
    string $cantidad = '20.0000',
    string $cantidadReservada = '8.0000',
    string $costoPromedio = '900.0000'
): array {
    $administrador = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $administrador->assignRole(
        'administrador'
    );

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $sucursal = Sucursal::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'MATRIZ',
        'nombre' => 'Sucursal principal',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $almacen = Almacen::query()->create([
        'empresa_id' => $empresa->id,
        'sucursal_id' => $sucursal->id,
        'codigo' => 'ALM-MATRIZ',
        'nombre' => 'Almacén principal',
        'tipo' => Almacen::TIPO_PRINCIPAL,
        'es_virtual' => false,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $categoria = CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento',
        'descripcion' => 'Discos y unidades de almacenamiento.',
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => null,
        'sku' => 'SSD-LIBERAR-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD para liberar',
        'descripcion' => 'Producto utilizado para liberar reservas.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-LIBERAR',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $existencia = ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => $cantidad,
        'cantidad_reservada' => $cantidadReservada,
        'costo_promedio' => $costoPromedio,
        'actualizado_por_id' => $administrador->id,
    ]);

    return [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ];
}

// ---------------------------------------------------------
// LIBERACIÓN PARCIAL
// Reduce la cantidad reservada sin modificar la existencia
// física ni el costo promedio del producto.
// ---------------------------------------------------------

test('authorized user can release inventory reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $resultado = app(
        LiberarReservaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '3.5000',
            'referencia_tipo' => 'App\Models\OrdenServicio',
            'referencia_id' => 125,
            'motivo' => 'Liberación parcial por cambio de reparación.',
        ]
    );

    expect($resultado)
        ->toBeInstanceOf(ExistenciaAlmacen::class)
        ->and($resultado->id)
        ->toBe($existencia->id)
        ->and($resultado->cantidad)
        ->toBe('20.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('4.5000')
        ->and($resultado->cantidadDisponible())
        ->toBe('15.5000')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($administrador->id);

    expect($existencia->fresh()?->cantidad)
        ->toBe('20.0000')
        ->and(
            $existencia
                ->fresh()
                ?->cantidad_reservada
        )
        ->toBe('4.5000')
        ->and(
            $existencia
                ->fresh()
                ?->costo_promedio
        )
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// LIBERACIÓN COMPLETA
// Permite liberar exactamente todas las unidades reservadas
// y restablece la disponibilidad completa.
// ---------------------------------------------------------

test('inventory reservation can be released completely', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario(
        cantidad: '10.0000',
        cantidadReservada: '6.0000',
        costoPromedio: '850.0000'
    );

    $resultado = app(
        LiberarReservaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '6.0000',
            'motivo' => 'Liberación completa de la reserva.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('10.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('0.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('10.0000')
        ->and($resultado->costo_promedio)
        ->toBe('850.0000')
        ->and($resultado->id)
        ->toBe($existencia->id);
});

// ---------------------------------------------------------
// LIBERACIÓN EXCESIVA
// Impide liberar una cantidad mayor que las unidades
// reservadas actualmente.
// ---------------------------------------------------------

test('inventory reservation release cannot exceed reserved stock', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario(
        cantidad: '10.0000',
        cantidadReservada: '4.0000',
        costoPromedio: '900.0000'
    );

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '4.0001',
                'motivo' => 'Intento de liberar más de lo reservado.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('10.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('4.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('6.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor puede liberar reservas cuando recibe el
// permiso inventario.consumir individualmente.
// ---------------------------------------------------------

test('user with direct permission can release inventory reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole(
        'supervisor'
    );

    $supervisor->givePermissionTo(
        'inventario.consumir'
    );

    $resultado = app(
        LiberarReservaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $supervisor,
        datos: [
            'cantidad' => '2.0000',
            'motivo' => 'Liberación autorizada al supervisor.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('20.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('6.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('14.0000')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($supervisor->id);
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide liberar reservas cuando el actor no tiene el
// permiso inventario.consumir.
// ---------------------------------------------------------

test('user without permission cannot release inventory reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole(
        'empleado'
    );

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $empleado,
            datos: [
                'cantidad' => '2.0000',
                'motivo' => 'Liberación no autorizada.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para liberar reservas de inventario.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// EXISTENCIA INEXISTENTE
// No puede liberarse una reserva si el producto no tiene
// existencia registrada dentro del almacén.
// ---------------------------------------------------------

test('reservation release requires warehouse stock record', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $existencia->delete();

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación sin existencia previa.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        ExistenciaAlmacen::query()->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// SIN UNIDADES RESERVADAS
// Impide liberar unidades cuando la existencia actualmente
// no tiene ninguna cantidad reservada.
// ---------------------------------------------------------

test('stock without reservation cannot release units', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario(
        cantidad: '10.0000',
        cantidadReservada: '0.0000',
        costoPromedio: '900.0000'
    );

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '0.0001',
                'motivo' => 'Intento de liberar sin reserva.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('10.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('10.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// ALMACÉN DE OTRA EMPRESA
// Evita liberar una reserva mediante un almacén que
// pertenece a una empresa diferente.
// ---------------------------------------------------------

test('reservation release warehouse must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $sucursalExterna = Sucursal::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'codigo' => 'EXTERNA',
        'nombre' => 'Sucursal externa',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $almacenExterno = Almacen::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'sucursal_id' => $sucursalExterna->id,
        'codigo' => 'ALM-EXTERNO',
        'nombre' => 'Almacén externo',
        'tipo' => Almacen::TIPO_PRINCIPAL,
        'es_virtual' => false,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenExterno,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación mediante almacén externo.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El almacén no pertenece a la empresa indicada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000');
});

// ---------------------------------------------------------
// PRODUCTO DE OTRA EMPRESA
// Impide liberar reservas de un producto perteneciente a
// una empresa diferente.
// ---------------------------------------------------------

test('reservation release product must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $categoriaExterna = CategoriaProducto::query()
        ->create([
            'empresa_id' => $otraEmpresa->id,
            'nombre' => 'Categoría externa',
            'activo' => true,
            'creado_por_id' => $administrador->id,
        ]);

    $productoExterno = Producto::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'categoria_producto_id' => $categoriaExterna->id,
        'proveedor_principal_id' => null,
        'sku' => 'EXT-LIBERAR-001',
        'codigo_barras' => '7509876543210',
        'nombre' => 'Producto externo',
        'descripcion' => 'Producto perteneciente a otra empresa.',
        'marca' => 'Marca externa',
        'modelo' => 'EXT-LIBERAR',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 500,
        'precio_base' => 750,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $productoExterno,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación con producto externo.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El producto no pertenece a la empresa indicada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->producto_id)
        ->toBe($producto->id);
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// Una empresa desactivada no puede modificar sus reservas
// mediante la operación ordinaria.
// ---------------------------------------------------------

test('inactive company cannot release inventory reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $empresa->update([
        'activo' => false,
    ]);

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa->fresh(),
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación durante suspensión de empresa.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// ALMACÉN INACTIVO
// No permite liberar reservas dentro de un almacén que se
// encuentra fuera de operación.
// ---------------------------------------------------------

test('inactive warehouse cannot release inventory reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $almacen->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Almacén temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación dentro de almacén inactivo.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($almacen->fresh()?->activo)
        ->toBeFalse();
});

// ---------------------------------------------------------
// SUCURSAL INACTIVA
// Un almacén físico no puede liberar reservas mientras su
// sucursal esté desactivada.
// ---------------------------------------------------------

test('warehouse inside inactive branch cannot release inventory reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $sucursal->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Sucursal temporalmente cerrada.',
    ]);

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación durante cierre de sucursal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($sucursal->fresh()?->activo)
        ->toBeFalse();
});

// ---------------------------------------------------------
// ALMACÉN SIN MANEJO DE EXISTENCIAS
// Respeta la configuración que impide administrar saldos
// dentro del almacén.
// ---------------------------------------------------------

test('warehouse that disallows stock cannot release reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $almacen->update([
        'permite_existencias' => false,
    ]);

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación en almacén sin existencias.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and(
            $almacen
                ->fresh()
                ?->permite_existencias
        )
        ->toBeFalse();
});

// ---------------------------------------------------------
// PRODUCTO INACTIVO
// Un producto desactivado no puede modificar reservas por
// medio de la operación ordinaria.
// ---------------------------------------------------------

test('inactive product cannot release inventory reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $producto->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Producto temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto->fresh(),
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación de producto inactivo.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($producto->fresh()?->activo)
        ->toBeFalse();
});

// ---------------------------------------------------------
// CANTIDAD INVÁLIDA
// La liberación debe utilizar una cantidad numérica mayor
// que cero y con un máximo de cuatro decimales.
// ---------------------------------------------------------

test('reservation release requires a valid positive quantity', function (
    mixed $cantidad
) {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => $cantidad,
                'motivo' => 'Validación de cantidad liberada.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
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
        'dos',
    ],
    'cantidad con más de cuatro decimales' => [
        '1.12345',
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Toda liberación necesita una justificación operativa
// válida de hasta 1000 caracteres.
// ---------------------------------------------------------

test('reservation release requires a valid reason', function (
    string $motivo
) {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
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
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
})->with([
    'motivo vacío' => [
        '   ',
    ],
    'motivo mayor de mil caracteres' => [
        str_repeat('A', 1001),
    ],
]);

// ---------------------------------------------------------
// ALMACÉN FÍSICO SIN SUCURSAL
// Todo almacén físico debe estar asociado con una sucursal
// válida antes de modificar sus reservas.
// ---------------------------------------------------------

test('physical warehouse without branch cannot release reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $almacen->update([
        'sucursal_id' => null,
    ]);

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación en almacén sin sucursal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($almacen->fresh()?->sucursal_id)
        ->toBeNull();
});

// ---------------------------------------------------------
// SUCURSAL DE OTRA EMPRESA
// Impide liberar reservas cuando el almacén apunta a una
// sucursal perteneciente a otra empresa.
// ---------------------------------------------------------

test('physical warehouse branch must belong to company for reservation release', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $sucursalExterna = Sucursal::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'codigo' => 'EXTERNA',
        'nombre' => 'Sucursal externa',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $almacen->update([
        'sucursal_id' => $sucursalExterna->id,
    ]);

    expect(
        fn () => app(
            LiberarReservaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Liberación con sucursal externa.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'La sucursal del almacén no pertenece a la empresa indicada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($almacen->fresh()?->empresa_id)
        ->toBe($empresa->id);
});

// ---------------------------------------------------------
// LIBERACIONES SUCESIVAS
// Varias liberaciones autorizadas deben descontarse del
// mismo registro de reserva sin afectar el saldo físico.
// ---------------------------------------------------------

test('successive reservation releases accumulate correctly', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario(
        cantidad: '20.0000',
        cantidadReservada: '10.0000',
        costoPromedio: '900.0000'
    );

    app(
        LiberarReservaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '2.5000',
            'motivo' => ' Primera liberación operativa. ',
        ]
    );

    $resultado = app(
        LiberarReservaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '3.2500',
            'motivo' => ' Segunda liberación operativa. ',
        ]
    );

    expect($resultado->id)
        ->toBe($existencia->id)
        ->and($resultado->cantidad)
        ->toBe('20.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('4.2500')
        ->and($resultado->cantidadDisponible())
        ->toBe('15.7500')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($administrador->id);

    expect(
        ExistenciaAlmacen::query()
            ->where('empresa_id', $empresa->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// CONSERVACIÓN DE EXISTENCIA Y COSTO
// La liberación solamente modifica cantidad_reservada y el
// usuario responsable de la última actualización.
// ---------------------------------------------------------

test('reservation release preserves physical stock and average cost', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaLiberarReservaInventario(
        cantidad: '13.7500',
        cantidadReservada: '6.2500',
        costoPromedio: '875.5500'
    );

    $resultado = app(
        LiberarReservaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '2.5000',
            'referencia_tipo' => ' App\Models\OrdenServicio ',
            'referencia_id' => '125',
            'motivo' => ' Liberación de reserva de una orden. ',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('13.7500')
        ->and($resultado->cantidad_reservada)
        ->toBe('3.7500')
        ->and($resultado->cantidadDisponible())
        ->toBe('10.0000')
        ->and($resultado->costo_promedio)
        ->toBe('875.5500')
        ->and($resultado->empresa_id)
        ->toBe($empresa->id)
        ->and($resultado->almacen_id)
        ->toBe($almacen->id)
        ->and($resultado->producto_id)
        ->toBe($producto->id)
        ->and($resultado->actualizado_por_id)
        ->toBe($administrador->id);

    expect($existencia->fresh()?->cantidad)
        ->toBe('13.7500')
        ->and(
            $existencia
                ->fresh()
                ?->cantidad_reservada
        )
        ->toBe('3.7500')
        ->and(
            $existencia
                ->fresh()
                ?->costo_promedio
        )
        ->toBe('875.5500');
});
