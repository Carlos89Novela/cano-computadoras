<?php

use App\Actions\Inventario\AjustarInventario;
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
// Carga roles y permisos antes de cada prueba de ajuste de
// inventario.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Crea administrador, empresa, sucursal, almacén, producto
// y existencia inicial para comprobar los ajustes.
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
function prepararEntornoParaAjustarInventario(
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
// AJUSTE POSITIVO
// Aumenta la existencia hasta la cantidad física indicada y
// registra únicamente la diferencia positiva.
// ---------------------------------------------------------

test('authorized user can register positive inventory adjustment', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $resultado = app(
        AjustarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad_objetivo' => '25.5000',
            'motivo' => 'Conteo físico encontró unidades adicionales.',
        ]
    );

    expect($resultado)
        ->toBeInstanceOf(ExistenciaAlmacen::class)
        ->and($resultado->id)
        ->toBe($existencia->id)
        ->and($resultado->cantidad)
        ->toBe('25.5000')
        ->and($resultado->cantidad_reservada)
        ->toBe('3.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('22.5000')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($administrador->id);

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->tipo)
        ->toBe(
            MovimientoInventario::TIPO_AJUSTE_POSITIVO
        )
        ->and($movimiento->cantidad)
        ->toBe('5.5000')
        ->and($movimiento->existencia_anterior)
        ->toBe('20.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('25.5000')
        ->and($movimiento->costo_unitario)
        ->toBe('900.0000')
        ->and($movimiento->motivo)
        ->toBe(
            'Conteo físico encontró unidades adicionales.'
        )
        ->and($movimiento->usuario_id)
        ->toBe($administrador->id);
});

// ---------------------------------------------------------
// AJUSTE NEGATIVO
// Reduce la existencia hasta la cantidad física indicada sin
// afectar la cantidad reservada ni el costo promedio.
// ---------------------------------------------------------

test('authorized user can register negative inventory adjustment', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $resultado = app(
        AjustarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad_objetivo' => '15.2500',
            'motivo' => 'Diferencia detectada durante el conteo físico.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('15.2500')
        ->and($resultado->cantidad_reservada)
        ->toBe('3.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('12.2500')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($administrador->id);

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->tipo)
        ->toBe(
            MovimientoInventario::TIPO_AJUSTE_NEGATIVO
        )
        ->and($movimiento->cantidad)
        ->toBe('4.7500')
        ->and($movimiento->existencia_anterior)
        ->toBe('20.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('15.2500')
        ->and($movimiento->costo_unitario)
        ->toBe('900.0000')
        ->and($movimiento->motivo)
        ->toBe(
            'Diferencia detectada durante el conteo físico.'
        );
});

// ---------------------------------------------------------
// MOVIMIENTO DE AJUSTE
// Comprueba que cada ajuste genere folio, grupo, fecha y
// referencias opcionales dentro del kardex.
// ---------------------------------------------------------

test('inventory adjustment stores movement information', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    app(
        AjustarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad_objetivo' => '22.0000',
            'referencia_tipo' => ' App\Models\ConteoInventario ',
            'referencia_id' => '150',
            'motivo' => 'Ajuste derivado del conteo mensual.',
        ]
    );

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->empresa_id)
        ->toBe($empresa->id)
        ->and($movimiento->almacen_id)
        ->toBe($almacen->id)
        ->and($movimiento->producto_id)
        ->toBe($producto->id)
        ->and($movimiento->tipo)
        ->toBe(
            MovimientoInventario::TIPO_AJUSTE_POSITIVO
        )
        ->and($movimiento->cantidad)
        ->toBe('2.0000')
        ->and($movimiento->existencia_anterior)
        ->toBe('20.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('22.0000')
        ->and($movimiento->referencia_tipo)
        ->toBe('App\Models\ConteoInventario')
        ->and($movimiento->referencia_id)
        ->toBe(150)
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
// PERMISO DIRECTO
// Un supervisor puede ajustar existencias cuando recibe el
// permiso inventario.ajustar de forma individual.
// ---------------------------------------------------------

test('user with direct permission can adjust inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'inventario.ajustar'
    );

    $resultado = app(
        AjustarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $supervisor,
        datos: [
            'cantidad_objetivo' => '18.0000',
            'motivo' => 'Ajuste autorizado al supervisor.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('18.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('3.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('15.0000')
        ->and($resultado->actualizado_por_id)
        ->toBe($supervisor->id);

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->usuario_id)
        ->toBe($supervisor->id)
        ->and($movimiento->tipo)
        ->toBe(
            MovimientoInventario::TIPO_AJUSTE_NEGATIVO
        )
        ->and($movimiento->cantidad)
        ->toBe('2.0000');
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide ajustar existencias cuando el actor no tiene el
// permiso inventario.ajustar.
// ---------------------------------------------------------

test('user without permission cannot adjust inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $empleado,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste no autorizado.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para ajustar existencias.'
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
// No puede ajustarse un producto que todavía no tiene un
// registro de existencia dentro del almacén.
// ---------------------------------------------------------

test('inventory adjustment requires warehouse stock record', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $existencia->delete();

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '10.0000',
                'motivo' => 'Ajuste sin existencia previa.',
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
// AJUSTE SIN CAMBIOS
// Evita guardar y auditar un ajuste cuando la cantidad
// objetivo coincide con la existencia actual.
// ---------------------------------------------------------

test('inventory adjustment requires an actual change', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '20.0000',
                'motivo' => 'Intento de ajuste sin diferencia.',
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
        ->and($existencia->actualizado_por_id)
        ->toBe($administrador->id)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// PROTECCIÓN DE RESERVAS
// El ajuste no puede colocar la cantidad física por debajo
// de las unidades reservadas.
// ---------------------------------------------------------

test('inventory adjustment cannot reduce stock below reserved quantity', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario(
        cantidad: '10.0000',
        cantidadReservada: '4.0000',
        costoPromedio: '900.0000'
    );

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '3.9999',
                'motivo' => 'Intento de reducir unidades reservadas.',
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
// AJUSTE EXACTO A LA CANTIDAD RESERVADA
// Permite establecer la existencia física exactamente en la
// cantidad reservada, dejando disponibilidad en cero.
// ---------------------------------------------------------

test('inventory adjustment can equal reserved quantity', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario(
        cantidad: '10.0000',
        cantidadReservada: '4.0000',
        costoPromedio: '900.0000'
    );

    $resultado = app(
        AjustarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad_objetivo' => '4.0000',
            'motivo' => 'Conteo físico igual a unidades reservadas.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('4.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('4.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('0.0000')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000');

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->tipo)
        ->toBe(
            MovimientoInventario::TIPO_AJUSTE_NEGATIVO
        )
        ->and($movimiento->cantidad)
        ->toBe('6.0000')
        ->and($movimiento->existencia_anterior)
        ->toBe('10.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('4.0000');
});

// ---------------------------------------------------------
// ALMACÉN DE OTRA EMPRESA
// Impide ajustar inventario mediante un almacén que no
// pertenece a la empresa indicada.
// ---------------------------------------------------------

test('inventory adjustment warehouse must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

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
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenExterno,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste mediante almacén externo.',
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
// Impide ajustar las existencias de un producto que
// pertenece a una empresa diferente.
// ---------------------------------------------------------

test('inventory adjustment product must belong to company', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

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
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $productoExterno,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste con producto externo.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El producto no pertenece a la empresa indicada.'
    );

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->producto_id)
        ->toBe($producto->id)
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// Una empresa desactivada no puede registrar ajustes de
// inventario.
// ---------------------------------------------------------

test('inactive company cannot adjust inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $empresa->update([
        'activo' => false,
    ]);

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa->fresh(),
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste durante suspensión de empresa.',
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
// No permite ajustar existencias dentro de un almacén que
// se encuentra fuera de operación.
// ---------------------------------------------------------

test('inactive warehouse cannot adjust inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $almacen->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Almacén temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste dentro de almacén inactivo.',
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
// Un almacén físico no puede ajustar existencias mientras
// su sucursal se encuentre desactivada.
// ---------------------------------------------------------

test('warehouse inside inactive branch cannot adjust inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $sucursal->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Sucursal temporalmente cerrada.',
    ]);

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste durante cierre de sucursal.',
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
        ->toBeFalse()
        ->and(MovimientoInventario::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN SIN MANEJO DE EXISTENCIAS
// Respeta la configuración que impide administrar saldos
// cuantificables dentro del almacén.
// ---------------------------------------------------------

test('warehouse that disallows stock cannot adjust inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $almacen->update([
        'permite_existencias' => false,
    ]);

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste en almacén sin existencias.',
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
// Un producto desactivado no puede ajustarse mediante la
// operación ordinaria de inventario.
// ---------------------------------------------------------

test('inactive product cannot adjust inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $producto->update([
        'activo' => false,
        'desactivado_por_id' => $administrador->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Producto temporalmente suspendido.',
    ]);

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto->fresh(),
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste de producto inactivo.',
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
// ALMACÉN FÍSICO SIN SUCURSAL
// Todo almacén físico debe permanecer relacionado con una
// sucursal antes de ajustar sus existencias.
// ---------------------------------------------------------

test('physical warehouse without branch cannot adjust inventory', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    $almacen->update([
        'sucursal_id' => null,
    ]);

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste en almacén sin sucursal.',
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
// Impide ajustar existencias cuando el almacén apunta a una
// sucursal perteneciente a otra empresa.
// ---------------------------------------------------------

test('physical warehouse branch must belong to company for inventory adjustment', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

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
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen->fresh(),
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
                'motivo' => 'Ajuste con sucursal externa.',
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

// ---------------------------------------------------------
// CANTIDAD OBJETIVO INVÁLIDA
// La cantidad objetivo debe ser numérica, no negativa y
// utilizar un máximo de cuatro decimales.
// ---------------------------------------------------------

test('inventory adjustment requires a valid target quantity', function (
    mixed $cantidadObjetivo
) {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario();

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => $cantidadObjetivo,
                'motivo' => 'Validación de cantidad objetivo.',
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
    'cantidad negativa' => [
        '-0.0001',
    ],
    'cantidad no numérica' => [
        'veinte',
    ],
    'cantidad con más de cuatro decimales' => [
        '18.12345',
    ],
]);

// ---------------------------------------------------------
// AJUSTE A CERO
// Permite establecer el saldo físico en cero cuando no hay
// unidades reservadas.
// ---------------------------------------------------------

test('inventory adjustment can set stock to zero without reservations', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
    ] = prepararEntornoParaAjustarInventario(
        cantidad: '5.0000',
        cantidadReservada: '0.0000',
        costoPromedio: '900.0000'
    );

    $resultado = app(
        AjustarInventario::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        actor: $administrador,
        datos: [
            'cantidad_objetivo' => '0',
            'motivo' => 'Conteo físico confirmó existencia en cero.',
        ]
    );

    expect($resultado->cantidad)
        ->toBe('0.0000')
        ->and($resultado->cantidad_reservada)
        ->toBe('0.0000')
        ->and($resultado->cantidadDisponible())
        ->toBe('0.0000')
        ->and($resultado->costo_promedio)
        ->toBe('900.0000');

    $movimiento = MovimientoInventario::query()
        ->firstOrFail();

    expect($movimiento->tipo)
        ->toBe(
            MovimientoInventario::TIPO_AJUSTE_NEGATIVO
        )
        ->and($movimiento->cantidad)
        ->toBe('5.0000')
        ->and($movimiento->existencia_anterior)
        ->toBe('5.0000')
        ->and($movimiento->existencia_nueva)
        ->toBe('0.0000')
        ->and($movimiento->costo_unitario)
        ->toBe('900.0000')
        ->and($existencia->fresh()?->cantidad)
        ->toBe('0.0000');
});

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Todo ajuste necesita una justificación operativa válida
// de hasta 1000 caracteres.
// ---------------------------------------------------------

test('inventory adjustment requires a valid reason', function (
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
    ] = prepararEntornoParaAjustarInventario();

    expect(
        fn () => app(
            AjustarInventario::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            actor: $administrador,
            datos: [
                'cantidad_objetivo' => '18.0000',
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
