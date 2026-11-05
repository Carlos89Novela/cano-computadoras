<?php

use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ---------------------------------------------------------
// TABLAS DE INVENTARIO
// Comprueba que existan la tabla de saldos actuales por
// almacén y la bitácora inmutable de movimientos.
// ---------------------------------------------------------

test('inventory stock tables exist', function () {
    expect(Schema::hasTable('existencias_almacen'))
        ->toBeTrue()
        ->and(
            Schema::hasTable(
                'movimientos_inventario'
            )
        )
        ->toBeTrue();
});

// ---------------------------------------------------------
// ESTRUCTURA DE EXISTENCIAS
// Cada registro representa el saldo de un producto dentro
// de un almacén específico de la empresa.
// ---------------------------------------------------------

test('warehouse stock table has required columns', function () {
    expect(
        Schema::hasColumns(
            'existencias_almacen',
            [
                'id',
                'empresa_id',
                'almacen_id',
                'producto_id',
                'cantidad',
                'cantidad_reservada',
                'costo_promedio',
                'actualizado_por_id',
                'created_at',
                'updated_at',
            ]
        )
    )->toBeTrue();
});

// ---------------------------------------------------------
// ESTRUCTURA DE MOVIMIENTOS
// Cada modificación de existencias debe quedar registrada
// como un movimiento histórico e inmutable.
// ---------------------------------------------------------

test('inventory movement table has required columns', function () {
    expect(
        Schema::hasColumns(
            'movimientos_inventario',
            [
                'id',
                'empresa_id',
                'almacen_id',
                'producto_id',
                'grupo_id',
                'folio',
                'tipo',
                'cantidad',
                'existencia_anterior',
                'existencia_nueva',
                'costo_unitario',
                'referencia_tipo',
                'referencia_id',
                'motivo',
                'usuario_id',
                'ocurrido_at',
                'created_at',
            ]
        )
    )->toBeTrue();
});

// ---------------------------------------------------------
// PRODUCTOS SIN STOCK DIRECTO
// Confirma nuevamente que el saldo no se almacene en la
// tabla productos, porque depende de cada almacén.
// ---------------------------------------------------------

test('products table does not store warehouse stock', function () {
    expect(
        Schema::hasColumn(
            'productos',
            'stock_actual'
        )
    )->toBeFalse()
        ->and(
            Schema::hasColumn(
                'productos',
                'cantidad'
            )
        )
        ->toBeFalse()
        ->and(
            Schema::hasColumn(
                'productos',
                'existencia'
            )
        )
        ->toBeFalse();
});

// ---------------------------------------------------------
// ENTORNO BASE DE INVENTARIO
// Crea empresa, sucursal, almacén, categoría, producto y
// usuario para validar existencias y movimientos.
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
function prepararEntornoParaInventario(): array
{
    $usuario = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    $sucursal = Sucursal::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'MATRIZ',
        'nombre' => 'Sucursal principal',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $usuario->id,
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
        'creado_por_id' => $usuario->id,
    ]);

    $categoria = CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento',
        'descripcion' => 'Discos, memorias y unidades de almacenamiento.',
        'activo' => true,
        'creado_por_id' => $usuario->id,
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
        'creado_por_id' => $usuario->id,
    ]);

    return [
        $usuario,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ];
}

// ---------------------------------------------------------
// EXISTENCIA POR ALMACÉN
// Comprueba la persistencia del saldo, la precisión decimal
// y las relaciones con empresa, almacén, producto y usuario.
// ---------------------------------------------------------

test('warehouse stock stores balances and relationships', function () {
    [
        $usuario,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaInventario();

    $existencia = ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => 25.5000,
        'cantidad_reservada' => 4.2500,
        'costo_promedio' => 975.1250,
        'actualizado_por_id' => $usuario->id,
    ]);

    expect($existencia->cantidad)
        ->toBe('25.5000')
        ->and($existencia->cantidad_reservada)
        ->toBe('4.2500')
        ->and($existencia->costo_promedio)
        ->toBe('975.1250')
        ->and($existencia->cantidadDisponible())
        ->toBe('21.2500')
        ->and($existencia->empresa->is($empresa))
        ->toBeTrue()
        ->and($existencia->almacen->is($almacen))
        ->toBeTrue()
        ->and($existencia->producto->is($producto))
        ->toBeTrue()
        ->and($existencia->actualizadoPor->is($usuario))
        ->toBeTrue();
});

// ---------------------------------------------------------
// EXISTENCIA ÚNICA
// Un producto solamente puede tener un registro de saldo
// dentro del mismo almacén y empresa.
// ---------------------------------------------------------

test('product stock is unique inside warehouse', function () {
    [
        $usuario,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaInventario();

    ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => 10,
        'cantidad_reservada' => 0,
        'costo_promedio' => 900,
        'actualizado_por_id' => $usuario->id,
    ]);

    expect(
        fn () => ExistenciaAlmacen::query()->create([
            'empresa_id' => $empresa->id,
            'almacen_id' => $almacen->id,
            'producto_id' => $producto->id,
            'cantidad' => 5,
            'cantidad_reservada' => 0,
            'costo_promedio' => 950,
            'actualizado_por_id' => $usuario->id,
        ])
    )->toThrow(
        QueryException::class
    );

    expect(
        ExistenciaAlmacen::query()
            ->where('empresa_id', $empresa->id)
            ->where('almacen_id', $almacen->id)
            ->where('producto_id', $producto->id)
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// MOVIMIENTO DE INVENTARIO
// Comprueba atributos, conversiones y relaciones de un
// movimiento registrado en el kardex.
// ---------------------------------------------------------

test('inventory movement stores values and relationships', function () {
    [
        $usuario,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaInventario();

    $grupoId = (string) Str::uuid();

    $movimiento = MovimientoInventario::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'grupo_id' => $grupoId,
        'folio' => 'MOV-000001',
        'tipo' => MovimientoInventario::TIPO_ENTRADA,
        'cantidad' => 10.5000,
        'existencia_anterior' => 5.2500,
        'existencia_nueva' => 15.7500,
        'costo_unitario' => 925.5000,
        'referencia_tipo' => null,
        'referencia_id' => null,
        'motivo' => 'Entrada inicial de inventario.',
        'usuario_id' => $usuario->id,
        'ocurrido_at' => now(),
    ]);

    expect($movimiento->grupo_id)
        ->toBe($grupoId)
        ->and($movimiento->folio)
        ->toBe('MOV-000001')
        ->and($movimiento->tipo)
        ->toBe(MovimientoInventario::TIPO_ENTRADA)
        ->and($movimiento->cantidad)
        ->toBe('10.5000')
        ->and($movimiento->existencia_anterior)
        ->toBe('5.2500')
        ->and($movimiento->existencia_nueva)
        ->toBe('15.7500')
        ->and($movimiento->costo_unitario)
        ->toBe('925.5000')
        ->and($movimiento->ocurrido_at)
        ->not->toBeNull()
        ->and($movimiento->empresa->is($empresa))
        ->toBeTrue()
        ->and($movimiento->almacen->is($almacen))
        ->toBeTrue()
        ->and($movimiento->producto->is($producto))
        ->toBeTrue()
        ->and($movimiento->usuario->is($usuario))
        ->toBeTrue();
});

// ---------------------------------------------------------
// MOVIMIENTO INMUTABLE
// Un movimiento de inventario no puede modificarse después
// de quedar registrado en el kardex.
// ---------------------------------------------------------

test('inventory movement cannot be updated', function () {
    [
        $usuario,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaInventario();

    $movimiento = MovimientoInventario::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'grupo_id' => (string) Str::uuid(),
        'folio' => 'MOV-000001',
        'tipo' => MovimientoInventario::TIPO_ENTRADA,
        'cantidad' => 10,
        'existencia_anterior' => 0,
        'existencia_nueva' => 10,
        'costo_unitario' => 900,
        'motivo' => 'Entrada inicial.',
        'usuario_id' => $usuario->id,
        'ocurrido_at' => now(),
    ]);

    expect(
        fn () => $movimiento->update([
            'cantidad' => 20,
        ])
    )->toThrow(
        LogicException::class,
        'Los movimientos de inventario no pueden modificarse.'
    );

    expect($movimiento->fresh()?->cantidad)
        ->toBe('10.0000');
});

// ---------------------------------------------------------
// ELIMINACIÓN PROHIBIDA
// La bitácora no permite eliminar movimientos porque su
// historial debe conservarse permanentemente.
// ---------------------------------------------------------

test('inventory movement cannot be deleted', function () {
    [
        $usuario,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaInventario();

    $movimiento = MovimientoInventario::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'grupo_id' => (string) Str::uuid(),
        'folio' => 'MOV-000001',
        'tipo' => MovimientoInventario::TIPO_ENTRADA,
        'cantidad' => 10,
        'existencia_anterior' => 0,
        'existencia_nueva' => 10,
        'costo_unitario' => 900,
        'motivo' => 'Entrada inicial.',
        'usuario_id' => $usuario->id,
        'ocurrido_at' => now(),
    ]);

    expect(
        fn () => $movimiento->delete()
    )->toThrow(
        LogicException::class,
        'Los movimientos de inventario no pueden eliminarse.'
    );

    $this->assertDatabaseHas(
        'movimientos_inventario',
        [
            'id' => $movimiento->id,
            'folio' => 'MOV-000001',
        ]
    );
});

// ---------------------------------------------------------
// RELACIONES INVERSAS DE INVENTARIO
// Comprueba que producto, almacén, empresa y usuario puedan
// consultar sus existencias y movimientos relacionados.
// ---------------------------------------------------------

test('inventory models expose inverse relationships', function () {
    [
        $usuario,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
    ] = prepararEntornoParaInventario();

    $existencia = ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => 10,
        'cantidad_reservada' => 2,
        'costo_promedio' => 900,
        'actualizado_por_id' => $usuario->id,
    ]);

    $movimiento = MovimientoInventario::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'grupo_id' => (string) Str::uuid(),
        'folio' => 'MOV-000001',
        'tipo' => MovimientoInventario::TIPO_ENTRADA,
        'cantidad' => 10,
        'existencia_anterior' => 0,
        'existencia_nueva' => 10,
        'costo_unitario' => 900,
        'referencia_tipo' => null,
        'referencia_id' => null,
        'motivo' => 'Entrada inicial de inventario.',
        'usuario_id' => $usuario->id,
        'ocurrido_at' => now(),
    ]);

    expect($producto->existenciasAlmacen)
        ->toHaveCount(1)
        ->and(
            $producto
                ->existenciasAlmacen
                ->first()
                ?->is($existencia)
        )
        ->toBeTrue()
        ->and($producto->movimientosInventario)
        ->toHaveCount(1)
        ->and(
            $producto
                ->movimientosInventario
                ->first()
                ?->is($movimiento)
        )
        ->toBeTrue();

    expect($almacen->existencias)
        ->toHaveCount(1)
        ->and(
            $almacen
                ->existencias
                ->first()
                ?->is($existencia)
        )
        ->toBeTrue()
        ->and($almacen->movimientosInventario)
        ->toHaveCount(1);

    expect($empresa->existenciasAlmacen)
        ->toHaveCount(1)
        ->and($empresa->movimientosInventario)
        ->toHaveCount(1);

    expect($usuario->existenciasActualizadas)
        ->toHaveCount(1)
        ->and(
            $usuario
                ->existenciasActualizadas
                ->first()
                ?->is($existencia)
        )
        ->toBeTrue()
        ->and($usuario->movimientosInventario)
        ->toHaveCount(1)
        ->and(
            $usuario
                ->movimientosInventario
                ->first()
                ?->is($movimiento)
        )
        ->toBeTrue();

    expect($sucursal->almacenes)
        ->toHaveCount(1)
        ->and($categoria->productos)
        ->toHaveCount(1);
});
