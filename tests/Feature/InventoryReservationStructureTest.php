<?php

use App\Models\Almacen;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ---------------------------------------------------------
// TABLA DE RESERVAS
// Comprueba la existencia de la tabla que relacionará las
// unidades reservadas con una orden de servicio concreta.
// ---------------------------------------------------------

test('inventory reservation table exists', function () {
    expect(
        Schema::hasTable(
            'reservas_inventario'
        )
    )->toBeTrue();
});

// ---------------------------------------------------------
// COLUMNAS DE IDENTIFICACIÓN
// Cada reserva debe identificar empresa, almacén, producto
// y orden de servicio propietaria del compromiso.
// ---------------------------------------------------------

test('inventory reservation table has ownership columns', function () {
    expect(
        Schema::hasColumns(
            'reservas_inventario',
            [
                'id',
                'empresa_id',
                'almacen_id',
                'producto_id',
                'orden_servicio_id',
            ]
        )
    )->toBeTrue();
});

// ---------------------------------------------------------
// CANTIDADES DE LA RESERVA
// Conserva la cantidad originalmente reservada, consumida
// y liberada para calcular el saldo pendiente.
// ---------------------------------------------------------

test('inventory reservation table has quantity columns', function () {
    expect(
        Schema::hasColumns(
            'reservas_inventario',
            [
                'cantidad_reservada',
                'cantidad_consumida',
                'cantidad_liberada',
            ]
        )
    )->toBeTrue();
});

// ---------------------------------------------------------
// ESTADO Y TRAZABILIDAD
// La reserva mantiene su estado, justificación, usuarios
// responsables y fechas operativas.
// ---------------------------------------------------------

test('inventory reservation table has tracking columns', function () {
    expect(
        Schema::hasColumns(
            'reservas_inventario',
            [
                'estado',
                'motivo',
                'reservado_por_id',
                'actualizado_por_id',
                'reservado_at',
                'finalizado_at',
                'created_at',
                'updated_at',
            ]
        )
    )->toBeTrue();
});

// ---------------------------------------------------------
// COLUMNAS NO DUPLICADAS
// La reserva utiliza los productos y órdenes existentes y
// no almacena información descriptiva redundante.
// ---------------------------------------------------------

test('inventory reservation table avoids duplicated catalog data', function () {
    expect(
        Schema::hasColumn(
            'reservas_inventario',
            'nombre_producto'
        )
    )->toBeFalse()
        ->and(
            Schema::hasColumn(
                'reservas_inventario',
                'folio_orden'
            )
        )
        ->toBeFalse()
        ->and(
            Schema::hasColumn(
                'reservas_inventario',
                'costo_promedio'
            )
        )
        ->toBeFalse();
});

// ---------------------------------------------------------
// ENTORNO BASE DE RESERVAS
// Crea todos los registros necesarios para relacionar una
// reserva de inventario con una orden de servicio.
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
 *     7: Equipo,
 *     8: OrdenServicio
 * }
 */
function prepararEntornoParaReservaInventario(): array
{
    // ---------------------------------------------------------
    // USUARIOS
    // Crea al responsable de inventario y al cliente
    // propietario de la orden y del equipo.
    // ---------------------------------------------------------

    $responsable = User::factory()->create([
        'email_verified_at' => now(),
    ]);

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
    // Crea la sucursal donde se encuentra el almacén.
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
    // Crea el almacén que conserva físicamente el producto.
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
    // Agrupa el producto utilizado en la reserva.
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
    // Crea el producto que será reservado para la orden.
    // ---------------------------------------------------------

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => null,
        'sku' => 'SSD-RESERVA-FORMAL-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD para reserva formal',
        'descripcion' => 'Producto utilizado para probar reservas por orden.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-RESERVA-FORMAL',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => true,
        'creado_por_id' => $responsable->id,
    ]);

    // ---------------------------------------------------------
    // EQUIPO DEL CLIENTE
    // Crea el equipo relacionado con la orden de servicio.
    // ---------------------------------------------------------

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Marca de prueba',
        'modelo' => 'Equipo reserva',
        'numero_serie' => 'SERIE-RESERVA-001',
        'descripcion' => 'Equipo utilizado para validar reservas.',
    ]);

    // ---------------------------------------------------------
    // ORDEN DE SERVICIO
    // Crea la orden propietaria de la reserva de inventario.
    // El token de seguimiento se genera automáticamente.
    // ---------------------------------------------------------

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-RESERVA-001',
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
        $equipo,
        $orden,
    ];
}

// ---------------------------------------------------------
// CREAR RESERVA DE INVENTARIO
// Centraliza la creación de reservas para las pruebas de
// consistencia y permite sobrescribir atributos concretos.
// ---------------------------------------------------------

/**
 * @param  array<string, mixed>  $atributos
 */
function crearReservaInventarioParaPrueba(
    User $responsable,
    Empresa $empresa,
    Almacen $almacen,
    Producto $producto,
    OrdenServicio $orden,
    array $atributos = []
): ReservaInventario {
    return ReservaInventario::query()->create(
        array_merge(
            [
                'empresa_id' => $empresa->id,
                'almacen_id' => $almacen->id,
                'producto_id' => $producto->id,
                'orden_servicio_id' => $orden->id,
                'cantidad_reservada' => '10.0000',
                'cantidad_consumida' => '0.0000',
                'cantidad_liberada' => '0.0000',
                'estado' => ReservaInventario::ESTADO_ACTIVA,
                'motivo' => 'Reserva creada para una prueba.',
                'reservado_por_id' => $responsable->id,
                'actualizado_por_id' => $responsable->id,
                'reservado_at' => now(),
                'finalizado_at' => null,
            ],
            $atributos
        )
    );
}

// ---------------------------------------------------------
// PERSISTENCIA Y RELACIONES
// Comprueba cantidades, fechas y relaciones principales de
// una reserva activa vinculada con una orden.
// ---------------------------------------------------------

test('inventory reservation stores values and relationships', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    $reserva = crearReservaInventarioParaPrueba($responsable, $empresa, $almacen, $producto, $orden, [
        'cantidad_reservada' => '10.5000',
        'cantidad_consumida' => '2.2500',
        'cantidad_liberada' => '1.0000',
        'estado' => ReservaInventario::ESTADO_PARCIAL,
        'motivo' => 'Productos comprometidos para la reparación.',
        'reservado_por_id' => $responsable->id,
        'actualizado_por_id' => $responsable->id,
        'reservado_at' => now(),
        'finalizado_at' => null,
    ]);

    expect($reserva->cantidad_reservada)
        ->toBe('10.5000')
        ->and($reserva->cantidad_consumida)
        ->toBe('2.2500')
        ->and($reserva->cantidad_liberada)
        ->toBe('1.0000')
        ->and($reserva->estado)
        ->toBe(ReservaInventario::ESTADO_PARCIAL)
        ->and($reserva->reservado_at)
        ->not->toBeNull()
        ->and($reserva->finalizado_at)
        ->toBeNull();

    expect($reserva->empresa->is($empresa))
        ->toBeTrue()
        ->and($reserva->almacen->is($almacen))
        ->toBeTrue()
        ->and($reserva->producto->is($producto))
        ->toBeTrue()
        ->and($reserva->ordenServicio->is($orden))
        ->toBeTrue()
        ->and($reserva->reservadoPor->is($responsable))
        ->toBeTrue()
        ->and($reserva->actualizadoPor->is($responsable))
        ->toBeTrue();

    expect($orden->user->is($cliente))
        ->toBeTrue()
        ->and($orden->equipo->is($equipo))
        ->toBeTrue()
        ->and($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id);
});

// ---------------------------------------------------------
// CANTIDAD PENDIENTE
// Calcula las unidades que todavía continúan comprometidas
// después de consumos y liberaciones parciales.
// ---------------------------------------------------------

test('inventory reservation calculates pending quantity', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    $reserva = ReservaInventario::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'orden_servicio_id' => $orden->id,
        'cantidad_reservada' => '12.0000',
        'cantidad_consumida' => '3.5000',
        'cantidad_liberada' => '2.2500',
        'estado' => ReservaInventario::ESTADO_PARCIAL,
        'motivo' => 'Reserva parcialmente procesada.',
        'reservado_por_id' => $responsable->id,
        'actualizado_por_id' => $responsable->id,
        'reservado_at' => now(),
    ]);

    // 12.0000 - 3.5000 - 2.2500 = 6.2500
    expect($reserva->cantidadPendiente())
        ->toBe('6.2500')
        ->and($reserva->estaFinalizada())
        ->toBeFalse()
        ->and($reserva->estaActiva())
        ->toBeTrue();
});

// ---------------------------------------------------------
// RESERVAS FINALIZADAS
// Detecta reservas completamente consumidas o liberadas y
// confirma que ya no permanezcan activas.
// ---------------------------------------------------------

test('inventory reservation detects completed states', function (
    string $estado,
    string $cantidadConsumida,
    string $cantidadLiberada
) {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    $reserva = ReservaInventario::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'orden_servicio_id' => $orden->id,
        'cantidad_reservada' => '10.0000',
        'cantidad_consumida' => $cantidadConsumida,
        'cantidad_liberada' => $cantidadLiberada,
        'estado' => $estado,
        'motivo' => 'Reserva completamente finalizada.',
        'reservado_por_id' => $responsable->id,
        'actualizado_por_id' => $responsable->id,
        'reservado_at' => now(),
        'finalizado_at' => now(),
    ]);

    expect($reserva->cantidadPendiente())
        ->toBe('0.0000')
        ->and($reserva->estaFinalizada())
        ->toBeTrue()
        ->and($reserva->estaActiva())
        ->toBeFalse();
})->with([
    'reserva consumida completamente' => [
        ReservaInventario::ESTADO_CONSUMIDA,
        '10.0000',
        '0.0000',
    ],
    'reserva liberada completamente' => [
        ReservaInventario::ESTADO_LIBERADA,
        '0.0000',
        '10.0000',
    ],
    'reserva finalizada de forma combinada' => [
        ReservaInventario::ESTADO_CONSUMIDA,
        '6.0000',
        '4.0000',
    ],
]);

// ---------------------------------------------------------
// RESERVA ÚNICA
// Una orden solo puede mantener un registro acumulado por
// producto y almacén.
// ---------------------------------------------------------

test('inventory reservation is unique by order warehouse and product', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    ReservaInventario::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'orden_servicio_id' => $orden->id,
        'cantidad_reservada' => '5.0000',
        'cantidad_consumida' => '0.0000',
        'cantidad_liberada' => '0.0000',
        'estado' => ReservaInventario::ESTADO_ACTIVA,
        'motivo' => 'Primera reserva.',
        'reservado_por_id' => $responsable->id,
        'actualizado_por_id' => $responsable->id,
        'reservado_at' => now(),
    ]);

    expect(
        fn () => ReservaInventario::query()->create([
            'empresa_id' => $empresa->id,
            'almacen_id' => $almacen->id,
            'producto_id' => $producto->id,
            'orden_servicio_id' => $orden->id,
            'cantidad_reservada' => '2.0000',
            'cantidad_consumida' => '0.0000',
            'cantidad_liberada' => '0.0000',
            'estado' => ReservaInventario::ESTADO_ACTIVA,
            'motivo' => 'Reserva duplicada.',
            'reservado_por_id' => $responsable->id,
            'actualizado_por_id' => $responsable->id,
            'reservado_at' => now(),
        ])
    )->toThrow(
        QueryException::class
    );

    expect(
        ReservaInventario::query()->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// CATÁLOGO DE ESTADOS
// Comprueba que el modelo publique únicamente los estados
// válidos definidos para el ciclo de una reserva.
// ---------------------------------------------------------

test('inventory reservation exposes valid states', function () {
    expect(
        ReservaInventario::estadosValidos()
    )->toBe([
        ReservaInventario::ESTADO_ACTIVA,
        ReservaInventario::ESTADO_PARCIAL,
        ReservaInventario::ESTADO_CONSUMIDA,
        ReservaInventario::ESTADO_LIBERADA,
    ]);
});

// ---------------------------------------------------------
// ESTADO INVÁLIDO
// Impide persistir una reserva que utilice un estado fuera
// del catálogo definido por el modelo.
// ---------------------------------------------------------

test('inventory reservation rejects invalid state', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    expect(
        fn () => crearReservaInventarioParaPrueba(
            responsable: $responsable,
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            atributos: [
                'estado' => 'estado_invalido',
            ]
        )
    )->toThrow(
        LogicException::class,
        'El estado de la reserva de inventario no es válido.'
    );

    expect(
        ReservaInventario::query()->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// CANTIDAD RESERVADA INVÁLIDA
// La cantidad originalmente reservada debe ser estrictamente
// mayor que cero.
// ---------------------------------------------------------

test('inventory reservation requires positive reserved quantity', function (
    string $cantidadReservada
) {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    expect(
        fn () => crearReservaInventarioParaPrueba(
            responsable: $responsable,
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            atributos: [
                'cantidad_reservada' => $cantidadReservada,
            ]
        )
    )->toThrow(
        LogicException::class,
        'La cantidad reservada debe ser mayor que cero.'
    );

    expect(
        ReservaInventario::query()->count()
    )->toBe(0);
})->with([
    'cantidad reservada en cero' => [
        '0.0000',
    ],
    'cantidad reservada negativa' => [
        '-1.0000',
    ],
]);

// ---------------------------------------------------------
// CANTIDAD CONSUMIDA NEGATIVA
// Impide registrar un acumulado consumido menor que cero.
// ---------------------------------------------------------

test('inventory reservation rejects negative consumed quantity', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    expect(
        fn () => crearReservaInventarioParaPrueba(
            responsable: $responsable,
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            atributos: [
                'cantidad_consumida' => '-0.0001',
            ]
        )
    )->toThrow(
        LogicException::class,
        'La cantidad consumida no puede ser negativa.'
    );

    expect(
        ReservaInventario::query()->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// CANTIDAD LIBERADA NEGATIVA
// Impide registrar un acumulado liberado menor que cero.
// ---------------------------------------------------------

test('inventory reservation rejects negative released quantity', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    expect(
        fn () => crearReservaInventarioParaPrueba(
            responsable: $responsable,
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            atributos: [
                'cantidad_liberada' => '-0.0001',
            ]
        )
    )->toThrow(
        LogicException::class,
        'La cantidad liberada no puede ser negativa.'
    );

    expect(
        ReservaInventario::query()->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// CANTIDAD FINALIZADA EXCESIVA
// El total consumido y liberado nunca puede superar las
// unidades originalmente reservadas.
// ---------------------------------------------------------

test('inventory reservation cannot finalize more than reserved quantity', function (
    string $cantidadConsumida,
    string $cantidadLiberada
) {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    expect(
        fn () => crearReservaInventarioParaPrueba(
            responsable: $responsable,
            empresa: $empresa,
            almacen: $almacen,
            producto: $producto,
            orden: $orden,
            atributos: [
                'cantidad_reservada' => '10.0000',
                'cantidad_consumida' => $cantidadConsumida,
                'cantidad_liberada' => $cantidadLiberada,
                'estado' => ReservaInventario::ESTADO_PARCIAL,
            ]
        )
    )->toThrow(
        LogicException::class,
        'La cantidad consumida y liberada no puede superar la cantidad reservada.'
    );

    expect(
        ReservaInventario::query()->count()
    )->toBe(0);
})->with([
    'consumo superior a la reserva' => [
        '10.0001',
        '0.0000',
    ],
    'liberación superior a la reserva' => [
        '0.0000',
        '10.0001',
    ],
    'suma combinada superior a la reserva' => [
        '6.0000',
        '4.0001',
    ],
]);

// ---------------------------------------------------------
// PROTECCIÓN DURANTE ACTUALIZACIÓN
// Las reglas de consistencia también aplican cuando una
// reserva existente recibe modificaciones posteriores.
// ---------------------------------------------------------

test('inventory reservation validates quantities during update', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    $reserva = crearReservaInventarioParaPrueba(
        responsable: $responsable,
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        atributos: [
            'cantidad_reservada' => '10.0000',
            'cantidad_consumida' => '3.0000',
            'cantidad_liberada' => '2.0000',
            'estado' => ReservaInventario::ESTADO_PARCIAL,
        ]
    );

    expect(
        fn () => $reserva->update([
            'cantidad_consumida' => '9.0000',
            'cantidad_liberada' => '2.0000',
        ])
    )->toThrow(
        LogicException::class,
        'La cantidad consumida y liberada no puede superar la cantidad reservada.'
    );

    $reserva->refresh();

    expect($reserva->cantidad_reservada)
        ->toBe('10.0000')
        ->and($reserva->cantidad_consumida)
        ->toBe('3.0000')
        ->and($reserva->cantidad_liberada)
        ->toBe('2.0000')
        ->and($reserva->cantidadPendiente())
        ->toBe('5.0000');
});

// ---------------------------------------------------------
// RELACIONES INVERSAS
// Comprueba que empresa, almacén, producto, orden y usuarios
// puedan consultar las reservas relacionadas.
// ---------------------------------------------------------

test('inventory reservation exposes inverse relationships', function () {
    [
        $responsable,
        $cliente,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $equipo,
        $orden,
    ] = prepararEntornoParaReservaInventario();

    $actualizador = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $reserva = crearReservaInventarioParaPrueba(
        responsable: $responsable,
        empresa: $empresa,
        almacen: $almacen,
        producto: $producto,
        orden: $orden,
        atributos: [
            'cantidad_reservada' => '10.0000',
            'cantidad_consumida' => '2.0000',
            'cantidad_liberada' => '1.0000',
            'estado' => ReservaInventario::ESTADO_PARCIAL,
            'actualizado_por_id' => $actualizador->id,
        ]
    );

    expect($empresa->reservasInventario)
        ->toHaveCount(1)
        ->and(
            $empresa
                ->reservasInventario
                ->first()
                ?->is($reserva)
        )
        ->toBeTrue();

    expect($almacen->reservasInventario)
        ->toHaveCount(1)
        ->and(
            $almacen
                ->reservasInventario
                ->first()
                ?->is($reserva)
        )
        ->toBeTrue();

    expect($producto->reservasInventario)
        ->toHaveCount(1)
        ->and(
            $producto
                ->reservasInventario
                ->first()
                ?->is($reserva)
        )
        ->toBeTrue();

    expect($orden->reservasInventario)
        ->toHaveCount(1)
        ->and(
            $orden
                ->reservasInventario
                ->first()
                ?->is($reserva)
        )
        ->toBeTrue();

    expect($responsable->reservasInventarioCreadas)
        ->toHaveCount(1)
        ->and(
            $responsable
                ->reservasInventarioCreadas
                ->first()
                ?->is($reserva)
        )
        ->toBeTrue();

    expect($actualizador->reservasInventarioActualizadas)
        ->toHaveCount(1)
        ->and(
            $actualizador
                ->reservasInventarioActualizadas
                ->first()
                ?->is($reserva)
        )
        ->toBeTrue();

    expect($cliente->reservasInventarioCreadas)
        ->toHaveCount(0)
        ->and($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id)
        ->and($equipo->user_id)
        ->toBe($cliente->id);
});
