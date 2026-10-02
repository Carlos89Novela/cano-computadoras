<?php

use App\Actions\Inventario\RegistrarEntradaInventario;
use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Producto;
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
// Carga roles y permisos antes de ejecutar cada prueba de
// entrada de inventario.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Crea usuario administrador, empresa, sucursal, almacén,
// categoría y producto habilitados para inventario.
// ---------------------------------------------------------

/**
 * @return array{
 *     0: User,
 *     1: Empresa,
 *     2: Sucursal,
 *     3: Almacen,
 *     4: CategoriaProducto,
 *     5: Producto
 * }
 */
function prepararEntornoParaEntradaInventario(): array
{
    $administrador = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $administrador->assignRole('administrador');

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
        'sku' => 'SSD-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD',
        'descripcion' => 'Unidad de estado sólido.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-TEST',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    return [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ];
}

// ---------------------------------------------------------
// PRIMERA ENTRADA
// Cuando todavía no existe saldo para el producto y el
// almacén, crea el registro inicial de existencia.
// ---------------------------------------------------------

test('inventory entry creates initial warehouse stock', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    $resultado = app(
        RegistrarEntradaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '10.5000',
            'costo_unitario' => '900.2500',
            'motivo' => 'Entrada inicial de inventario.',
        ]
    );

    expect($resultado)
        ->toBeInstanceOf(ExistenciaAlmacen::class)
        ->and($resultado->empresa_id)
        ->toBe($empresa->id)
        ->and($resultado->almacen_id)
        ->toBe($almacen->id)
        ->and($resultado->producto_id)
        ->toBe($producto->id)
        ->and($resultado->cantidad)
        ->toBe('10.5000')
        ->and($resultado->cantidad_reservada)
        ->toBe('0.0000')
        ->and($resultado->costo_promedio)
        ->toBe('900.2500')
        ->and($resultado->actualizado_por_id)
        ->toBe($administrador->id)
        ->and($resultado->cantidadDisponible())
        ->toBe('10.5000');

    expect(
        ExistenciaAlmacen::query()
            ->where('empresa_id', $empresa->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// MOVIMIENTO DE ENTRADA
// Toda entrada debe generar un movimiento inmutable con
// saldos anterior y nuevo, folio y grupo de operación.
// ---------------------------------------------------------

test('inventory entry stores movement information', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    app(
        RegistrarEntradaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '12.2500',
            'costo_unitario' => '950.5000',
            'motivo' => 'Compra inicial para la sucursal.',
        ]
    );

    $movimiento = MovimientoInventario::query()
        ->where(
            'tipo',
            MovimientoInventario::TIPO_ENTRADA
        )
        ->firstOrFail();

    expect($movimiento->empresa_id)
        ->toBe($empresa->id)
        ->and($movimiento->almacen_id)
        ->toBe($almacen->id)
        ->and($movimiento->producto_id)
        ->toBe($producto->id)
        ->and($movimiento->cantidad)
        ->toBe('12.2500')
        ->and($movimiento->existencia_anterior)
        ->toBe('0.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('12.2500')
        ->and($movimiento->costo_unitario)
        ->toBe('950.5000')
        ->and($movimiento->motivo)
        ->toBe('Compra inicial para la sucursal.')
        ->and($movimiento->usuario_id)
        ->toBe($administrador->id)
        ->and($movimiento->ocurrido_at)
        ->not->toBeNull();

    expect($movimiento->folio)
        ->toStartWith('MOV-')
        ->and(Str::isUuid($movimiento->grupo_id))
        ->toBeTrue();

    expect(MovimientoInventario::query()->count())
        ->toBe(1);
});

// ---------------------------------------------------------
// EXISTENCIA PREVIA
// Suma la entrada a un saldo existente y calcula el costo
// promedio ponderado usando cantidades y costos actuales.
// ---------------------------------------------------------

test('inventory entry increases stock and recalculates average cost', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => '10.0000',
        'cantidad_reservada' => '2.0000',
        'costo_promedio' => '100.0000',
        'actualizado_por_id' => $administrador->id,
    ]);

    $resultado = app(
        RegistrarEntradaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '5.0000',
            'costo_unitario' => '160.0000',
            'motivo' => 'Reposición de unidades.',
        ]
    );

    // Costo promedio ponderado:
    // ((10 × 100) + (5 × 160)) ÷ 15 = 120
    expect($resultado->cantidad)
        ->toBe('15.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('2.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('13.0000')
        ->and($resultado->costo_promedio)
        ->toBe('120.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($administrador->id);

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->existencia_anterior)
        ->toBe('10.0000')
        ->and($movimiento->cantidad)
        ->toBe('5.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('15.0000')
        ->and($movimiento->costo_unitario)
        ->toBe('160.0000');

    expect(
        ExistenciaAlmacen::query()
            ->where('empresa_id', $empresa->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor puede registrar entradas cuando recibe el
// permiso inventario.registrar_entrada individualmente.
// ---------------------------------------------------------

test('user with direct permission can register inventory entry', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'inventario.registrar_entrada'
    );

    $resultado = app(
        RegistrarEntradaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $supervisor,
        datos: [
            'cantidad' => '3.0000',
            'costo_unitario' => '875.0000',
            'motivo' => 'Entrada autorizada al supervisor.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('3.0000')
        ->and($resultado->costo_promedio)
        ->toBe('875.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($supervisor->id);

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->usuario_id)
        ->toBe($supervisor->id)
        ->and($movimiento->tipo)
        ->toBe(MovimientoInventario::TIPO_ENTRADA);
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide registrar entradas cuando el actor no tiene el
// permiso inventario.registrar_entrada.
// ---------------------------------------------------------

test('user without permission cannot register inventory entry', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $empleado,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => 'Entrada no autorizada.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para registrar entradas de inventario.'
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN DE OTRA EMPRESA
// Evita registrar una entrada utilizando un almacén que
// pertenece a una empresa diferente.
// ---------------------------------------------------------

test('inventory entry warehouse must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

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
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenExterno,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => 'Entrada con almacén externo.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El almacén no pertenece a la empresa indicada.'
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0)
        ->and($almacen->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// PRODUCTO DE OTRA EMPRESA
// Impide afectar existencias de un producto perteneciente
// a una empresa diferente.
// ---------------------------------------------------------

test('inventory entry product must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

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
        'sku' => 'EXT-001',
        'codigo_barras' => '7509876543210',
        'nombre' => 'Producto externo',
        'descripcion' => 'Producto perteneciente a otra empresa.',
        'marca' => 'Marca externa',
        'modelo' => 'EXT-TEST',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 500,
        'precio_base' => 750,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $productoExterno,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '500.0000',
                'motivo' => 'Entrada con producto externo.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El producto no pertenece a la empresa indicada.'
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0)
        ->and($producto->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// Una empresa desactivada no puede registrar nuevos
// movimientos de inventario.
// ---------------------------------------------------------

test('inactive company cannot register inventory entry', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    $empresa->update([
        'activo' => false,
    ]);

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa->fresh(),
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => 'Entrada durante suspensión.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN INACTIVO
// Un almacén desactivado no puede recibir productos ni
// generar movimientos de entrada.
// ---------------------------------------------------------

test('inactive warehouse cannot receive inventory entry', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    $almacen->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Almacén temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => 'Entrada en almacén inactivo.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0)
        ->and($almacen->fresh()?->activo)
        ->toBeFalse();
});

// ---------------------------------------------------------
// SUCURSAL INACTIVA
// Un almacén físico no puede recibir inventario mientras
// su sucursal se encuentre desactivada.
// ---------------------------------------------------------

test('warehouse inside inactive branch cannot receive inventory entry', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    $sucursal->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Sucursal temporalmente cerrada.',
    ]);

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => 'Entrada durante cierre de sucursal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0)
        ->and($sucursal->fresh()?->activo)
        ->toBeFalse();
});

// ---------------------------------------------------------
// ALMACÉN SIN EXISTENCIAS
// Respeta la configuración que impide manejar saldos
// cuantificables dentro del almacén.
// ---------------------------------------------------------

test('warehouse that disallows stock cannot receive inventory entry', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    $almacen->update([
        'permite_existencias' => false,
    ]);

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => 'Entrada en almacén sin existencias.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0)
        ->and(
            $almacen
                ->fresh()
                ?->permite_existencias
        )
        ->toBeFalse();
});

// ---------------------------------------------------------
// PRODUCTO INACTIVO
// Un producto desactivado no puede recibir unidades nuevas
// dentro del inventario.
// ---------------------------------------------------------

test('inactive product cannot receive inventory entry', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    $producto->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Producto temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto->fresh(),
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => 'Entrada para producto inactivo.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0)
        ->and($producto->fresh()?->activo)
        ->toBeFalse();
});

// ---------------------------------------------------------
// CANTIDAD INVÁLIDA
// Toda entrada debe utilizar una cantidad numérica mayor
// que cero y con un máximo de cuatro decimales.
// ---------------------------------------------------------

test('inventory entry requires a valid positive quantity', function (
    mixed $cantidad
) {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => $cantidad,
                'costo_unitario' => '900.0000',
                'motivo' => 'Validación de cantidad.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
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
        'diez',
    ],
    'cantidad con más de cuatro decimales' => [
        '1.12345',
    ],
]);

// ---------------------------------------------------------
// COSTO UNITARIO INVÁLIDO
// El costo debe ser numérico, igual o mayor que cero y
// utilizar un máximo de cuatro decimales.
// ---------------------------------------------------------

test('inventory entry requires a valid nonnegative unit cost', function (
    mixed $costoUnitario
) {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => $costoUnitario,
                'motivo' => 'Validación del costo unitario.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
})->with([
    'costo vacío' => [
        '   ',
    ],
    'costo negativo' => [
        '-0.0001',
    ],
    'costo no numérico' => [
        'novecientos',
    ],
    'costo con más de cuatro decimales' => [
        '900.12345',
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Toda entrada necesita una justificación operativa válida
// de hasta 1000 caracteres.
// ---------------------------------------------------------

test('inventory entry requires a valid reason', function (
    string $motivo
) {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => $motivo,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
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
// REFERENCIA OPCIONAL
// Permite relacionar la entrada con una operación externa
// mediante tipo e identificador de referencia.
// ---------------------------------------------------------

test('inventory entry stores optional reference', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    app(
        RegistrarEntradaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '8.0000',
            'costo_unitario' => '850.0000',
            'referencia_tipo' => ' App\Models\Compra ',
            'referencia_id' => '125',
            'motivo' => 'Entrada relacionada con una compra.',
        ]
    );

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->referencia_tipo)
        ->toBe('App\Models\Compra')
        ->and($movimiento->referencia_id)
        ->toBe(125)
        ->and($movimiento->cantidad)
        ->toBe('8.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('8.0000');

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(1)
        ->and(MovimientoInventario::query()->count())
        ->toBe(1);
});

// ---------------------------------------------------------
// ALMACÉN FÍSICO SIN SUCURSAL
// Todo almacén físico debe permanecer conectado con una
// sucursal antes de recibir inventario.
// ---------------------------------------------------------

test('physical warehouse without branch cannot receive inventory entry', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    $almacen->update([
        'sucursal_id' => null,
    ]);

    expect(
        fn () => app(
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => 'Entrada en almacén sin sucursal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0)
        ->and($almacen->fresh()?->sucursal_id)
        ->toBeNull();
});

// ---------------------------------------------------------
// SUCURSAL DE OTRA EMPRESA
// Impide registrar inventario si el almacén apunta a una
// sucursal perteneciente a otra empresa.
// ---------------------------------------------------------

test('physical warehouse branch must belong to company for inventory entry', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

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
            RegistrarEntradaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '10.0000',
                'costo_unitario' => '900.0000',
                'motivo' => 'Entrada con sucursal externa.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'La sucursal del almacén no pertenece a la empresa indicada.'
    );

    expect(ExistenciaAlmacen::query()->count())
        ->toBe(0)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0)
        ->and($almacen->fresh()?->empresa_id)
        ->toBe($empresa->id);
});

// ---------------------------------------------------------
// COSTO PROMEDIO ANTERIOR EN CERO
// Comprueba el cálculo ponderado cuando ya hay unidades,
// pero el costo promedio anterior es cero.
// ---------------------------------------------------------

test('inventory entry recalculates average cost from zero previous cost', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaEntradaInventario();

    ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => '5.0000',
        'cantidad_reservada' => '1.0000',
        'costo_promedio' => '0.0000',
        'actualizado_por_id' => $administrador->id,
    ]);

    $resultado = app(
        RegistrarEntradaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '5.0000',
            'costo_unitario' => '200.0000',
            'motivo' => 'Entrada con costo anterior desconocido.',
        ]
    );

    // ((5 × 0) + (5 × 200)) ÷ 10 = 100
    expect($resultado->cantidad)
        ->toBe('10.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('1.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('9.0000')
        ->and($resultado->costo_promedio)
        ->toBe('100.0000');

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->existencia_anterior)
        ->toBe('5.0000')
        ->and($movimiento->cantidad)
        ->toBe('5.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('10.0000')
        ->and($movimiento->costo_unitario)
        ->toBe('200.0000');
});
