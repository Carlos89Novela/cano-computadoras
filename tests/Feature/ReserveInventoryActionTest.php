<?php

use App\Actions\Inventario\ReservarInventario;
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
// Carga roles y permisos antes de cada prueba relacionada
// con reservas de inventario.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Crea administrador, empresa, sucursal, almacén, producto
// y existencia inicial para probar las reservas.
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
function prepararEntornoParaReservarInventario(
    string $cantidad = '20.0000',
    string $cantidadReservada = '3.0000',
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
        'sku' => 'SSD-RESERVA-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD para reserva',
        'descripcion' => 'Producto utilizado para probar reservas.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-RESERVA',
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
// RESERVA CORRECTA
// Aumenta la cantidad reservada sin modificar la existencia
// física ni el costo promedio.
// ---------------------------------------------------------

test('authorized user can reserve inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

    $resultado = app(
        ReservarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '5.5000',
            'referencia_tipo' => 'App\Models\OrdenServicio',
            'referencia_id' => 125,
            'motivo' => 'Reserva para una reparación autorizada.',
        ]
    );

    expect($resultado)
        ->toBeInstanceOf(ExistenciaAlmacen::class)
        ->and($resultado->id)
        ->toBe($existencia->id)
        ->and($resultado->cantidad)
        ->toBe('20.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('8.5000')
        ->and($resultado->cantidadDisponible())
        ->toBe('11.5000')
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
        ->toBe('8.5000');
});

// ---------------------------------------------------------
// RESERVAR TODO LO DISPONIBLE
// Permite reservar exactamente el saldo disponible sin
// provocar cantidades negativas.
// ---------------------------------------------------------

test('inventory reservation can use all available stock', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario(
        cantidad: '10.0000',
        cantidadReservada: '2.0000',
        costoPromedio: '850.0000'
    );

    $resultado = app(
        ReservarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '8.0000',
            'motivo' => 'Reserva de todo el saldo disponible.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('10.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('10.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('0.0000')
        ->and($resultado->costo_promedio)
        ->toBe('850.0000')
        ->and($resultado->id)
        ->toBe($existencia->id);
});

// ---------------------------------------------------------
// DISPONIBILIDAD INSUFICIENTE
// Impide reservar una cantidad mayor que el saldo disponible
// del producto dentro del almacén.
// ---------------------------------------------------------

test('inventory reservation cannot exceed available stock', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario(
        cantidad: '10.0000',
        cantidadReservada: '3.0000',
        costoPromedio: '900.0000'
    );

    // Disponible:
    // 10.0000 - 3.0000 = 7.0000
    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '7.0001',
                'motivo' => 'Intento de reservar más del disponible.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('10.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('7.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor puede reservar inventario cuando recibe
// inventario.consumir de forma individual.
// ---------------------------------------------------------

test('user with direct permission can reserve inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

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
        ReservarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $supervisor,
        datos: [
            'cantidad' => '2.0000',
            'motivo' => 'Reserva autorizada al supervisor.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('20.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('5.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('15.0000')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($supervisor->id);
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide reservar inventario cuando el actor no tiene el
// permiso inventario.consumir.
// ---------------------------------------------------------

test('user without permission cannot reserve inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole(
        'empleado'
    );

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $empleado,
            datos: [
                'cantidad' => '2.0000',
                'motivo' => 'Reserva no autorizada.',
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
        ->toBe('3.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('17.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// EXISTENCIA INEXISTENTE
// No puede reservarse un producto que todavía no tiene un
// registro de existencia dentro del almacén.
// ---------------------------------------------------------

test('inventory reservation requires warehouse stock record', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

    $existencia->delete();

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva sin existencia previa.',
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
// EXISTENCIA COMPLETAMENTE RESERVADA
// No permite crear otra reserva cuando todo el saldo físico
// ya se encuentra comprometido.
// ---------------------------------------------------------

test('fully reserved stock cannot receive another reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario(
        cantidad: '10.0000',
        cantidadReservada: '10.0000',
        costoPromedio: '900.0000'
    );

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '0.0001',
                'motivo' => 'Intento de reservar sin disponibilidad.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('10.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('10.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('0.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// ALMACÉN DE OTRA EMPRESA
// Evita reservar inventario mediante un almacén que
// pertenece a una empresa diferente.
// ---------------------------------------------------------

test('inventory reservation warehouse must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

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
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenExterno,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva mediante almacén externo.',
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
        ->toBe('3.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('17.0000');
});

// ---------------------------------------------------------
// PRODUCTO DE OTRA EMPRESA
// Impide reservar un producto perteneciente a una empresa
// distinta de la indicada.
// ---------------------------------------------------------

test('inventory reservation product must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

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
        'sku' => 'EXT-RESERVA-001',
        'codigo_barras' => '7509876543210',
        'nombre' => 'Producto externo',
        'descripcion' => 'Producto perteneciente a otra empresa.',
        'marca' => 'Marca externa',
        'modelo' => 'EXT-RESERVA',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 500,
        'precio_base' => 750,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $productoExterno,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva con producto externo.',
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
        ->toBe('3.0000')
        ->and($existencia->producto_id)
        ->toBe($producto->id);
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// Una empresa desactivada no puede crear nuevas reservas de
// inventario.
// ---------------------------------------------------------

test('inactive company cannot reserve inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

    $empresa->update([
        'activo' => false,
    ]);

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa->fresh(),
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva durante suspensión de empresa.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// ALMACÉN INACTIVO
// No permite reservar existencias de un almacén que está
// fuera de operación.
// ---------------------------------------------------------

test('inactive warehouse cannot reserve inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

    $almacen->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Almacén temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva dentro de almacén inactivo.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and($almacen->fresh()?->activo)
        ->toBeFalse();
});

// ---------------------------------------------------------
// SUCURSAL INACTIVA
// Un almacén físico no puede reservar inventario mientras
// su sucursal esté desactivada.
// ---------------------------------------------------------

test('warehouse inside inactive branch cannot reserve inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

    $sucursal->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Sucursal temporalmente cerrada.',
    ]);

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva durante cierre de sucursal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and($sucursal->fresh()?->activo)
        ->toBeFalse();
});

// ---------------------------------------------------------
// ALMACÉN SIN MANEJO DE EXISTENCIAS
// Respeta la configuración que impide manejar saldos dentro
// del almacén.
// ---------------------------------------------------------

test('warehouse that disallows stock cannot reserve inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

    $almacen->update([
        'permite_existencias' => false,
    ]);

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva en almacén sin existencias.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and(
            $almacen
                ->fresh()
                ?->permite_existencias
        )
        ->toBeFalse();
});

// ---------------------------------------------------------
// PRODUCTO INACTIVO
// Un producto desactivado no puede recibir nuevas reservas
// operativas.
// ---------------------------------------------------------

test('inactive product cannot reserve inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

    $producto->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Producto temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto->fresh(),
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva de producto inactivo.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and($producto->fresh()?->activo)
        ->toBeFalse();
});

// ---------------------------------------------------------
// CANTIDAD INVÁLIDA
// La reserva debe utilizar una cantidad numérica mayor que
// cero y con un máximo de cuatro decimales.
// ---------------------------------------------------------

test('inventory reservation requires a valid positive quantity', function (
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
    ] = prepararEntornoParaReservarInventario();

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => $cantidad,
                'motivo' => 'Validación de cantidad reservada.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('17.0000')
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
        'cinco',
    ],
    'cantidad con más de cuatro decimales' => [
        '1.12345',
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Toda reserva necesita una justificación operativa válida
// de hasta 1000 caracteres.
// ---------------------------------------------------------

test('inventory reservation requires a valid reason', function (
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
    ] = prepararEntornoParaReservarInventario();

    expect(
        fn () => app(
            ReservarInventario::class
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
        ->toBe('3.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('17.0000')
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
// Todo almacén físico debe permanecer asociado con una
// sucursal válida antes de reservar inventario.
// ---------------------------------------------------------

test('physical warehouse without branch cannot reserve inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

    $almacen->update([
        'sucursal_id' => null,
    ]);

    expect(
        fn () => app(
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva en almacén sin sucursal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and($almacen->fresh()?->sucursal_id)
        ->toBeNull();
});

// ---------------------------------------------------------
// SUCURSAL DE OTRA EMPRESA
// Impide reservar inventario cuando el almacén apunta a una
// sucursal perteneciente a otra empresa.
// ---------------------------------------------------------

test('physical warehouse branch must belong to company for reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario();

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
            ReservarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Reserva con sucursal externa.',
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
        ->toBe('3.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('17.0000')
        ->and($almacen->fresh()?->empresa_id)
        ->toBe($empresa->id);
});

// ---------------------------------------------------------
// RESERVAS SUCESIVAS
// Varias reservas autorizadas deben acumularse sobre el
// mismo registro sin modificar la existencia física.
// ---------------------------------------------------------

test('successive inventory reservations accumulate correctly', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario(
        cantidad: '20.0000',
        cantidadReservada: '2.0000',
        costoPromedio: '900.0000'
    );

    app(
        ReservarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '3.5000',
            'motivo' => ' Primera reserva operativa. ',
        ]
    );

    $resultado = app(
        ReservarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '4.2500',
            'motivo' => ' Segunda reserva operativa. ',
        ]
    );

    expect($resultado->id)
        ->toBe($existencia->id)
        ->and($resultado->cantidad)
        ->toBe('20.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('9.7500')
        ->and($resultado->cantidadDisponible())
        ->toBe('10.2500')
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
// La reserva solamente modifica cantidad_reservada y el
// usuario actualizador.
// ---------------------------------------------------------

test('inventory reservation preserves physical stock and average cost', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaReservarInventario(
        cantidad: '13.7500',
        cantidadReservada: '1.2500',
        costoPromedio: '875.5500'
    );

    $resultado = app(
        ReservarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '2.5000',
            'referencia_tipo' => ' App\Models\OrdenServicio ',
            'referencia_id' => '125',
            'motivo' => ' Reserva para orden de servicio. ',
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
