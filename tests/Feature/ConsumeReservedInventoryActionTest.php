<?php

use App\Actions\Inventario\ConsumirInventarioReservado;
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
// Carga roles y permisos antes de cada prueba relacionada
// con el consumo de inventario reservado.
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
function prepararEntornoParaConsumirInventarioReservado(
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
        'sku' => 'SSD-CONSUMO-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD para consumo',
        'descripcion' => 'Producto utilizado para probar consumos.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-CONSUMO',
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
// CONSUMO PARCIAL
// Descuenta simultáneamente la existencia física y las
// unidades reservadas consumidas en una reparación.
// ---------------------------------------------------------

test('authorized user can consume reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    $resultado = app(
        ConsumirInventarioReservado::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '3.5000',
            'referencia_tipo' => 'App\Models\OrdenServicio',
            'referencia_id' => 125,
            'motivo' => 'Consumo de producto en reparación autorizada.',
        ]
    );

    expect($resultado)
        ->toBeInstanceOf(ExistenciaAlmacen::class)
        ->and($resultado->id)
        ->toBe($existencia->id)
        ->and($resultado->cantidad)
        ->toBe('16.5000')
        ->and($resultado->cantidad_reservada)
        ->toBe('4.5000')
        ->and($resultado->cantidadDisponible())
        ->toBe('12.0000')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($administrador->id);

    expect($existencia->fresh()?->cantidad)
        ->toBe('16.5000')
        ->and(
            $existencia
                ->fresh()
                ?->cantidad_reservada
        )
        ->toBe('4.5000');
});

// ---------------------------------------------------------
// CONSUMO COMPLETO DE LA RESERVA
// Permite consumir todas las unidades reservadas y deja
// cantidad_reservada en cero.
// ---------------------------------------------------------

test('all reserved inventory can be consumed', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado(
        cantidad: '10.0000',
        cantidadReservada: '6.0000',
        costoPromedio: '850.0000'
    );

    $resultado = app(
        ConsumirInventarioReservado::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '6.0000',
            'motivo' => 'Consumo completo de la reserva.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('4.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('0.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('4.0000')
        ->and($resultado->costo_promedio)
        ->toBe('850.0000')
        ->and($resultado->id)
        ->toBe($existencia->id);
});

// ---------------------------------------------------------
// CONSUMO MAYOR QUE LA RESERVA
// Impide consumir una cantidad superior a las unidades
// reservadas actualmente para el producto.
// ---------------------------------------------------------

test('reserved inventory consumption cannot exceed reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado(
        cantidad: '10.0000',
        cantidadReservada: '4.0000',
        costoPromedio: '900.0000'
    );

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '4.0001',
                'motivo' => 'Intento de consumir más de lo reservado.',
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
        ->toBe('900.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// MOVIMIENTO DE CONSUMO
// Registra el consumo en el kardex con saldos anterior y
// nuevo, costo promedio, referencia, folio y grupo.
// ---------------------------------------------------------

test('reserved inventory consumption stores movement information', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    app(
        ConsumirInventarioReservado::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '2.2500',
            'referencia_tipo' => ' App\Models\OrdenServicio ',
            'referencia_id' => '125',
            'motivo' => ' Consumo asociado con una reparación. ',
        ]
    );

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
        ->and($movimiento->tipo)
        ->toBe(MovimientoInventario::TIPO_CONSUMO)
        ->and($movimiento->cantidad)
        ->toBe('2.2500')
        ->and($movimiento->existencia_anterior)
        ->toBe('20.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('17.7500')
        ->and($movimiento->costo_unitario)
        ->toBe('900.0000')
        ->and($movimiento->referencia_tipo)
        ->toBe('App\Models\OrdenServicio')
        ->and($movimiento->referencia_id)
        ->toBe(125)
        ->and($movimiento->motivo)
        ->toBe(
            'Consumo asociado con una reparación.'
        )
        ->and($movimiento->usuario_id)
        ->toBe($administrador->id)
        ->and($movimiento->ocurrido_at)
        ->not->toBeNull();

    expect($movimiento->folio)
        ->toStartWith('MOV-')
        ->and(Str::isUuid($movimiento->grupo_id))
        ->toBeTrue();

    expect(
        MovimientoInventario::query()->count()
    )->toBe(1);

    expect($existencia->fresh()?->cantidad)
        ->toBe('17.7500')
        ->and(
            $existencia
                ->fresh()
                ?->cantidad_reservada
        )
        ->toBe('5.7500');
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor puede consumir inventario reservado cuando
// recibe inventario.consumir individualmente.
// ---------------------------------------------------------

test('user with direct permission can consume reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

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
        ConsumirInventarioReservado::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $supervisor,
        datos: [
            'cantidad' => '2.0000',
            'motivo' => 'Consumo autorizado al supervisor.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('18.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('6.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('12.0000')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($supervisor->id);

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->usuario_id)
        ->toBe($supervisor->id)
        ->and($movimiento->tipo)
        ->toBe(MovimientoInventario::TIPO_CONSUMO)
        ->and($movimiento->costo_unitario)
        ->toBe('900.0000');
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide consumir inventario reservado cuando el actor no
// tiene el permiso inventario.consumir.
// ---------------------------------------------------------

test('user without permission cannot consume reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole(
        'empleado'
    );

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $empleado,
            datos: [
                'cantidad' => '2.0000',
                'motivo' => 'Consumo no autorizado.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para consumir inventario reservado.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('8.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('12.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// EXISTENCIA INEXISTENTE
// No puede consumirse una reserva cuando el producto no
// tiene existencia registrada dentro del almacén.
// ---------------------------------------------------------

test('reserved inventory consumption requires warehouse stock record', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    $existencia->delete();

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo sin existencia previa.',
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
// SIN UNIDADES RESERVADAS
// Impide consumir unidades cuando el producto no tiene una
// reserva activa dentro del almacén.
// ---------------------------------------------------------

test('stock without reservation cannot be consumed as reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado(
        cantidad: '10.0000',
        cantidadReservada: '0.0000',
        costoPromedio: '900.0000'
    );

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '0.0001',
                'motivo' => 'Intento de consumo sin reserva.',
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
        ->toBe('900.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// RESERVA MAYOR QUE EXISTENCIA FÍSICA
// Protege contra una inconsistencia previa en la que las
// unidades reservadas superan la existencia física.
// ---------------------------------------------------------

test('reserved consumption cannot exceed physical stock', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado(
        cantidad: '3.0000',
        cantidadReservada: '5.0000',
        costoPromedio: '900.0000'
    );

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '4.0000',
                'motivo' => 'Consumo superior a la existencia física.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('3.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('5.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN DE OTRA EMPRESA
// Impide consumir inventario mediante un almacén que
// pertenece a una empresa diferente.
// ---------------------------------------------------------

test('reserved consumption warehouse must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

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
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenExterno,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo mediante almacén externo.',
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
        ->toBe('12.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// PRODUCTO DE OTRA EMPRESA
// Impide consumir un producto perteneciente a una empresa
// diferente de la indicada.
// ---------------------------------------------------------

test('reserved consumption product must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

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
        'sku' => 'EXT-CONSUMO-001',
        'codigo_barras' => '7509876543210',
        'nombre' => 'Producto externo',
        'descripcion' => 'Producto perteneciente a otra empresa.',
        'marca' => 'Marca externa',
        'modelo' => 'EXT-CONSUMO',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 500,
        'precio_base' => 750,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $productoExterno,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo con producto externo.',
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
        ->toBe($producto->id)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// Una empresa desactivada no puede registrar consumos de
// inventario reservado.
// ---------------------------------------------------------

test('inactive company cannot consume reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    $empresa->update([
        'activo' => false,
    ]);

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa->fresh(),
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo durante suspensión de empresa.',
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
        ->toBe('900.0000')
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN INACTIVO
// No permite consumir inventario de un almacén que se
// encuentra fuera de operación.
// ---------------------------------------------------------

test('inactive warehouse cannot consume reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    $almacen->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Almacén temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo dentro de almacén inactivo.',
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
        ->toBeFalse()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// SUCURSAL INACTIVA
// Un almacén físico no puede consumir inventario mientras
// la sucursal se encuentre desactivada.
// ---------------------------------------------------------

test('warehouse inside inactive branch cannot consume reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    $sucursal->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Sucursal temporalmente cerrada.',
    ]);

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo durante cierre de sucursal.',
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
        ->toBeFalse()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN SIN MANEJO DE EXISTENCIAS
// Respeta la configuración que impide administrar saldos
// cuantificables dentro del almacén.
// ---------------------------------------------------------

test('warehouse that disallows stock cannot consume reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    $almacen->update([
        'permite_existencias' => false,
    ]);

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo en almacén sin existencias.',
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
        ->toBeFalse()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// PRODUCTO INACTIVO
// Un producto desactivado no puede consumirse mediante la
// operación ordinaria de inventario.
// ---------------------------------------------------------

test('inactive product cannot consume reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    $producto->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Producto temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto->fresh(),
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo de producto inactivo.',
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
        ->toBeFalse()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// CANTIDAD INVÁLIDA
// El consumo debe utilizar una cantidad numérica mayor que
// cero y con un máximo de cuatro decimales.
// ---------------------------------------------------------

test('reserved inventory consumption requires a valid positive quantity', function (
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
    ] = prepararEntornoParaConsumirInventarioReservado();

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => $cantidad,
                'motivo' => 'Validación de cantidad consumida.',
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
        'dos',
    ],
    'cantidad con más de cuatro decimales' => [
        '1.12345',
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Todo consumo necesita una justificación operativa válida
// de hasta 1000 caracteres.
// ---------------------------------------------------------

test('reserved inventory consumption requires a valid reason', function (
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
    ] = prepararEntornoParaConsumirInventarioReservado();

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
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
        ->toBe('900.0000')
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
// ALMACÉN FÍSICO SIN SUCURSAL
// Todo almacén físico debe estar asociado con una sucursal
// válida antes de consumir inventario.
// ---------------------------------------------------------

test('physical warehouse without branch cannot consume reserved inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

    $almacen->update([
        'sucursal_id' => null,
    ]);

    expect(
        fn () => app(
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo desde almacén sin sucursal.',
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
        ->toBeNull()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// SUCURSAL DE OTRA EMPRESA
// Impide consumir inventario cuando el almacén apunta a una
// sucursal perteneciente a otra empresa.
// ---------------------------------------------------------

test('physical warehouse branch must belong to company for reserved consumption', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado();

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
            ConsumirInventarioReservado::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad' => '1.0000',
                'motivo' => 'Consumo con sucursal externa.',
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
        ->toBe($empresa->id)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// CONSUMOS SUCESIVOS
// Varios consumos autorizados deben reducir correctamente
// la existencia física y la reserva del mismo registro.
// ---------------------------------------------------------

test('successive reserved inventory consumptions accumulate correctly', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado(
        cantidad: '20.0000',
        cantidadReservada: '10.0000',
        costoPromedio: '900.0000'
    );

    app(
        ConsumirInventarioReservado::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '2.5000',
            'motivo' => ' Primer consumo reservado. ',
        ]
    );

    $resultado = app(
        ConsumirInventarioReservado::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '3.2500',
            'motivo' => ' Segundo consumo reservado. ',
        ]
    );

    expect($resultado->id)
        ->toBe($existencia->id)
        ->and($resultado->cantidad)
        ->toBe('14.2500')
        ->and($resultado->cantidad_reservada)
        ->toBe('4.2500')
        ->and($resultado->cantidadDisponible())
        ->toBe('10.0000')
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

    expect(
        MovimientoInventario::query()
            ->where(
                'tipo',
                MovimientoInventario::TIPO_CONSUMO
            )
            ->count()
    )->toBe(2);
});

// ---------------------------------------------------------
// DISPONIBILIDAD CONSERVADA
// Al reducir cantidad física y reserva en el mismo valor,
// la cantidad disponible debe permanecer sin cambios.
// ---------------------------------------------------------

test('reserved consumption preserves available stock and average cost', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaConsumirInventarioReservado(
        cantidad: '13.7500',
        cantidadReservada: '6.2500',
        costoPromedio: '875.5500'
    );

    // Disponible antes del consumo:
    // 13.7500 - 6.2500 = 7.5000

    $resultado = app(
        ConsumirInventarioReservado::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad' => '2.5000',
            'referencia_tipo' => ' App\Models\OrdenServicio ',
            'referencia_id' => '125',
            'motivo' => ' Consumo reservado para una orden. ',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('11.2500')
        ->and($resultado->cantidad_reservada)
        ->toBe('3.7500')
        ->and($resultado->cantidadDisponible())
        ->toBe('7.5000')
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

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->tipo)
        ->toBe(MovimientoInventario::TIPO_CONSUMO)
        ->and($movimiento->cantidad)
        ->toBe('2.5000')
        ->and($movimiento->existencia_anterior)
        ->toBe('13.7500')
        ->and($movimiento->existencia_nueva)
        ->toBe('11.2500')
        ->and($movimiento->costo_unitario)
        ->toBe('875.5500');
});
