<?php

use App\Actions\Inventario\RegistrarSalidaInventario;
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
// salida de inventario.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Crea administrador, empresa, sucursal, almacén, categoría,
// producto y existencia inicial para registrar salidas.
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
function prepararEntornoParaSalidaInventario(
    string $cantidad = '20.0000',
    string $cantidadReservada = '3.0000',
    string $costoPromedio = '900.0000'
): array {
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
// SALIDA CORRECTA
// Descuenta unidades del saldo disponible sin modificar la
// cantidad reservada ni el costo promedio.
// ---------------------------------------------------------

test('authorized user can register inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $resultado = app(
        RegistrarSalidaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '5.5000',
            'motivo' => 'Salida para instalación en equipo.',
        ]
    );

    expect($resultado)
        ->toBeInstanceOf(ExistenciaAlmacen::class)
        ->and($resultado->id)
        ->toBe($existencia->id)
        ->and($resultado->empresa_id)
        ->toBe($empresa->id)
        ->and($resultado->almacen_id)
        ->toBe($almacen->id)
        ->and($resultado->producto_id)
        ->toBe($producto->id)
        ->and($resultado->cantidad)
        ->toBe('14.5000')
        ->and($resultado->cantidad_reservada)
        ->toBe('3.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('11.5000')
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
// SALDO DISPONIBLE COMPLETO
// Permite retirar exactamente todas las unidades disponibles
// sin consumir las unidades reservadas.
// ---------------------------------------------------------

test('inventory exit can consume all available stock', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario(
        cantidad: '10.0000',
        cantidadReservada: '2.0000',
        costoPromedio: '850.0000'
    );

    $resultado = app(
        RegistrarSalidaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '8.0000',
            'motivo' => 'Salida de todo el saldo disponible.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('2.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('2.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('0.0000')
        ->and($resultado->costo_promedio)
        ->toBe('850.0000')
        ->and($resultado->id)
        ->toBe($existencia->id);
});

// ---------------------------------------------------------
// MOVIMIENTO DE SALIDA
// Toda salida debe generar un movimiento inmutable con el
// saldo anterior, saldo nuevo, costo, folio y grupo.
// ---------------------------------------------------------

test('inventory exit stores movement information', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    app(
        RegistrarSalidaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '4.2500',
            'motivo' => 'Salida para reparación autorizada.',
        ]
    );

    $movimiento = MovimientoInventario::query()
        ->where(
            'tipo',
            MovimientoInventario::TIPO_SALIDA
        )
        ->firstOrFail();

    expect($movimiento->empresa_id)
        ->toBe($empresa->id)
        ->and($movimiento->almacen_id)
        ->toBe($almacen->id)
        ->and($movimiento->producto_id)
        ->toBe($producto->id)
        ->and($movimiento->cantidad)
        ->toBe('4.2500')
        ->and($movimiento->existencia_anterior)
        ->toBe('20.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('15.7500')
        ->and($movimiento->costo_unitario)
        ->toBe('900.0000')
        ->and($movimiento->motivo)
        ->toBe('Salida para reparación autorizada.')
        ->and($movimiento->usuario_id)
        ->toBe($administrador->id)
        ->and($movimiento->ocurrido_at)
        ->not->toBeNull();

    expect($movimiento->folio)
        ->toStartWith('MOV-')
        ->and(Str::isUuid($movimiento->grupo_id))
        ->toBeTrue();

    expect(MovimientoInventario::query()->count())
        ->toBe(1)
        ->and($existencia->fresh()?->cantidad)
        ->toBe('15.7500');
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor puede registrar salidas cuando recibe el
// permiso inventario.registrar_salida individualmente.
// ---------------------------------------------------------

test('user with direct permission can register inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'inventario.registrar_salida'
    );

    $resultado = app(
        RegistrarSalidaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $supervisor,
        datos: [
            'cantidad' => '3.0000',
            'motivo' => 'Salida autorizada al supervisor.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('17.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('3.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('14.0000')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($supervisor->id);

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->usuario_id)
        ->toBe($supervisor->id)
        ->and($movimiento->tipo)
        ->toBe(MovimientoInventario::TIPO_SALIDA)
        ->and($movimiento->costo_unitario)
        ->toBe('900.0000')
        ->and($existencia->fresh()?->cantidad)
        ->toBe('17.0000');
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide registrar salidas cuando el actor no tiene el
// permiso inventario.registrar_salida.
// ---------------------------------------------------------

test('user without permission cannot register inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $empleado,
            datos: [
                'cantidad' => '5.0000',
                'motivo' => 'Salida no autorizada.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para registrar salidas de inventario.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// EXISTENCIA INEXISTENTE
// Impide registrar una salida cuando el producto todavía
// no tiene saldo registrado dentro del almacén.
// ---------------------------------------------------------

test('inventory exit requires warehouse stock record', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $existencia->delete();

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida sin existencia previa.',
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
// SALDO DISPONIBLE INSUFICIENTE
// La salida no puede consumir las unidades reservadas ni
// provocar una existencia disponible negativa.
// ---------------------------------------------------------

test('inventory exit cannot exceed available stock', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario(
        cantidad: '10.0000',
        cantidadReservada: '3.0000',
        costoPromedio: '900.0000'
    );

    // Existencia disponible:
    // 10.0000 - 3.0000 = 7.0000
    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '7.0001',
                'motivo' => 'Intento de consumir unidades reservadas.',
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
        ->toBe('900.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// EXISTENCIA COMPLETAMENTE RESERVADA
// No permite registrar salidas cuando todo el saldo físico
// se encuentra reservado.
// ---------------------------------------------------------

test('fully reserved stock cannot be withdrawn', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario(
        cantidad: '10.0000',
        cantidadReservada: '10.0000',
        costoPromedio: '900.0000'
    );

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '0.0001',
                'motivo' => 'Intento de retirar saldo reservado.',
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
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN DE OTRA EMPRESA
// Impide registrar una salida utilizando un almacén que
// pertenece a una empresa diferente.
// ---------------------------------------------------------

test('inventory exit warehouse must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

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
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenExterno,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida mediante almacén externo.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El almacén no pertenece a la empresa indicada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('17.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// PRODUCTO DE OTRA EMPRESA
// Impide registrar una salida con un producto perteneciente
// a una empresa diferente.
// ---------------------------------------------------------

test('inventory exit product must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

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
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $productoExterno,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida con producto externo.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El producto no pertenece a la empresa indicada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('17.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// Una empresa desactivada no puede registrar nuevos
// movimientos de salida de inventario.
// ---------------------------------------------------------

test('inactive company cannot register inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $empresa->update([
        'activo' => false,
    ]);

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa->fresh(),
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida durante suspensión de empresa.',
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
        ->toBe('900.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN INACTIVO
// No permite retirar productos de un almacén que se
// encuentra fuera de operación.
// ---------------------------------------------------------

test('inactive warehouse cannot register inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $almacen->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Almacén temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida desde almacén inactivo.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('17.0000')
        ->and($almacen->fresh()?->activo)
        ->toBeFalse()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// SUCURSAL INACTIVA
// Un almacén físico no puede entregar inventario mientras
// su sucursal esté temporalmente desactivada.
// ---------------------------------------------------------

test('warehouse inside inactive branch cannot register inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $sucursal->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Sucursal temporalmente cerrada.',
    ]);

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida durante cierre de sucursal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidadReservada ?? null)
        ->toBeNull()
        ->and($sucursal->fresh()?->activo)
        ->toBeFalse()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN SIN MANEJO DE EXISTENCIAS
// Respeta la configuración que impide administrar saldos
// cuantificables dentro del almacén.
// ---------------------------------------------------------

test('warehouse that disallows stock cannot register inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $almacen->update([
        'permite_existencias' => false,
    ]);

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida desde almacén sin existencias.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and(
            $almacen
                ->fresh()
                ?->permite_existencias
        )
        ->toBeFalse()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// PRODUCTO INACTIVO
// Un producto desactivado no puede registrar salidas
// mediante la operación normal de inventario.
// ---------------------------------------------------------

test('inactive product cannot register inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $producto->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Producto temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto->fresh(),
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida de producto inactivo.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($producto->fresh()?->activo)
        ->toBeFalse()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// CANTIDAD INVÁLIDA
// La salida necesita una cantidad numérica mayor que cero
// y con un máximo de cuatro decimales.
// ---------------------------------------------------------

test('inventory exit requires a valid positive quantity', function (
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
    ] = prepararEntornoParaSalidaInventario();

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => $cantidad,
                'motivo' => 'Validación de cantidad de salida.',
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
        ->toBe('900.0000')
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
        'cinco',
    ],
    'cantidad con más de cuatro decimales' => [
        '1.12345',
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Toda salida necesita una justificación operativa válida
// de hasta 1000 caracteres.
// ---------------------------------------------------------

test('inventory exit requires a valid reason', function (
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
    ] = prepararEntornoParaSalidaInventario();

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
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
// Permite relacionar la salida con una orden, consumo u
// otra operación externa mediante tipo e identificador.
// ---------------------------------------------------------

test('inventory exit stores optional reference', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $resultado = app(
        RegistrarSalidaInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '2.0000',
            'referencia_tipo' => ' App\Models\OrdenServicio ',
            'referencia_id' => '125',
            'motivo' => 'Salida asociada con una orden de servicio.',
        ]
    );

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->referencia_tipo)
        ->toBe('App\Models\OrdenServicio')
        ->and($movimiento->referencia_id)
        ->toBe(125)
        ->and($movimiento->tipo)
        ->toBe(MovimientoInventario::TIPO_SALIDA)
        ->and($movimiento->cantidad)
        ->toBe('2.0000')
        ->and($movimiento->existencia_anterior)
        ->toBe('20.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('18.0000');

    expect($resultado->cantidad)
        ->toBe('18.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('3.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('15.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(1)
        ->and($existencia->fresh()?->cantidad)
        ->toBe('18.0000');
});

// ---------------------------------------------------------
// ALMACÉN FÍSICO SIN SUCURSAL
// Todo almacén físico debe estar asociado con una sucursal
// válida antes de entregar inventario.
// ---------------------------------------------------------

test('physical warehouse without branch cannot register inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

    $almacen->update([
        'sucursal_id' => null,
    ]);

    expect(
        fn () => app(
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida desde almacén sin sucursal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($almacen->fresh()?->sucursal_id)
        ->toBeNull()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// SUCURSAL DE OTRA EMPRESA
// Evita registrar salidas cuando el almacén apunta a una
// sucursal perteneciente a otra empresa.
// ---------------------------------------------------------

test('physical warehouse branch must belong to company for inventory exit', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaSalidaInventario();

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
            RegistrarSalidaInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Salida con sucursal externa.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'La sucursal del almacén no pertenece a la empresa indicada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('17.0000')
        ->and($almacen->fresh()?->empresa_id)
        ->toBe($empresa->id)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});
