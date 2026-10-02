<?php

use App\Actions\Inventario\ReservarInventarioParaOrden;
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
use Illuminate\Foundation\Testing\RefreshDatabase;

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
