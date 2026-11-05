<?php

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
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ---------------------------------------------------------
// PREPARACIÓN GENERAL
// Carga la matriz real de roles y permisos antes de cada
// prueba HTTP de reservas de inventario por orden.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Crea los registros necesarios para probar una reserva HTTP
// vinculada con una orden de servicio.
// ---------------------------------------------------------

/**
 * @return array{
 *     0: User,
 *     1: Empresa,
 *     2: Sucursal,
 *     3: Almacen,
 *     4: CategoriaProducto,
 *     5: Producto,
 *     6: ExistenciaAlmacen,
 *     7: OrdenServicio
 * }
 */
function prepararEntornoParaReservaHttp(): array
{
    // -----------------------------------------------------
    // ADMINISTRADOR
    // Crea al actor autorizado para reservar inventario.
    // -----------------------------------------------------

    $administrador = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $administrador->assignRole(
        'administrador'
    );

    // -----------------------------------------------------
    // CLIENTE
    // Crea al propietario del equipo y de la orden.
    // -----------------------------------------------------

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
        'creado_por_id' => $administrador->id,
    ]);

    // -----------------------------------------------------
    // SUCURSAL
    // Crea la sucursal donde opera el almacén.
    // -----------------------------------------------------

    $sucursal = Sucursal::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'MATRIZ-HTTP',
        'nombre' => 'Sucursal principal HTTP',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    // -----------------------------------------------------
    // ALMACÉN
    // Crea el almacén seleccionado desde el formulario.
    // -----------------------------------------------------

    $almacen = Almacen::query()->create([
        'empresa_id' => $empresa->id,
        'sucursal_id' => $sucursal->id,
        'codigo' => 'ALM-HTTP',
        'nombre' => 'Almacén HTTP',
        'tipo' => Almacen::TIPO_PRINCIPAL,
        'es_virtual' => false,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    // -----------------------------------------------------
    // CATEGORÍA
    // Agrupa el producto utilizado por la prueba.
    // -----------------------------------------------------

    $categoria = CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento HTTP',
        'descripcion' => 'Categoría utilizada en la prueba HTTP.',
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    // -----------------------------------------------------
    // PRODUCTO
    // Crea el producto que será reservado para la orden.
    // -----------------------------------------------------

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => null,
        'sku' => 'SSD-RESERVA-HTTP-001',
        'codigo_barras' => '7502222222222',
        'nombre' => 'Unidad SSD para reserva HTTP',
        'descripcion' => 'Producto utilizado en la reserva HTTP.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-HTTP',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    // -----------------------------------------------------
    // EXISTENCIA
    // Registra veinte unidades físicas sin reservas previas.
    // -----------------------------------------------------

    $existencia = ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => '20.0000',
        'cantidad_reservada' => '0.0000',
        'costo_promedio' => '900.0000',
        'actualizado_por_id' => $administrador->id,
    ]);

    // -----------------------------------------------------
    // EQUIPO
    // Crea el equipo perteneciente al cliente.
    // -----------------------------------------------------

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude HTTP',
        'numero_serie' => 'SERIE-HTTP-001',
        'descripcion' => 'Equipo utilizado en la prueba HTTP.',
    ]);

    // -----------------------------------------------------
    // ORDEN
    // Crea la orden que recibirá la reserva.
    // -----------------------------------------------------

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-RESERVA-HTTP-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El almacenamiento presenta fallas.',
        'estado' => 'En reparación',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    return [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $orden,
    ];
}

// ---------------------------------------------------------
// RESERVA HTTP EXITOSA
// Un administrador autorizado puede registrar una reserva
// para una orden mediante el endpoint administrativo.
// ---------------------------------------------------------

test('administrator can reserve inventory for service order through http', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $orden,
    ] = prepararEntornoParaReservaHttp();

    $response = $this
        ->actingAs($administrador)
        ->post(
            route(
                'admin.ordenes.inventario.reservar',
                [
                    'empresa' => $empresa->id,
                    'orden' => $orden->id,
                ]
            ),
            [
                'almacen_id' => $almacen->id,
                'producto_id' => $producto->id,
                'cantidad' => '5.5000',
                'motivo' => 'Reserva HTTP para reparar la orden.',
            ]
        );

    // -----------------------------------------------------
    // REDIRECCIÓN
    // Después de reservar, regresa al detalle administrativo
    // de la misma orden con un mensaje de éxito.
    // -----------------------------------------------------

    $response
        ->assertRedirect(
            route(
                'admin.ordenes.edit',
                [
                    'orden' => $orden->id,
                ]
            )
        )
        ->assertSessionHas(
            'success',
            'El inventario fue reservado correctamente.'
        );

    // -----------------------------------------------------
    // RESERVA FORMAL
    // Comprueba el detalle creado para la orden, almacén y
    // producto seleccionados.
    // -----------------------------------------------------

    $reserva = ReservaInventario::query()
        ->where(
            'empresa_id',
            $empresa->id
        )
        ->where(
            'almacen_id',
            $almacen->id
        )
        ->where(
            'producto_id',
            $producto->id
        )
        ->where(
            'orden_servicio_id',
            $orden->id
        )
        ->firstOrFail();

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
        ->toBe(
            'Reserva HTTP para reparar la orden.'
        )
        ->and($reserva->reservado_por_id)
        ->toBe($administrador->id)
        ->and($reserva->actualizado_por_id)
        ->toBe($administrador->id)
        ->and($reserva->finalizado_at)
        ->toBeNull();

    // -----------------------------------------------------
    // EXISTENCIA CONSOLIDADA
    // La existencia física y el costo permanecen iguales.
    // Solo aumenta la cantidad reservada.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('5.5000')
        ->and($existencia->cantidadDisponible())
        ->toBe('14.5000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and($existencia->actualizado_por_id)
        ->toBe($administrador->id);

    expect($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id);
});

// ---------------------------------------------------------
// RESERVA HTTP SIN PERMISO
// Un administrador sin inventario.consumir no puede reservar
// unidades mediante el endpoint administrativo.
// ---------------------------------------------------------

test('administrator without permission cannot reserve inventory through http', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $orden,
    ] = prepararEntornoParaReservaHttp();

    // -----------------------------------------------------
    // RETIRAR PERMISO DEL ROL
    // Conserva el rol administrador para superar el middleware
    // administrativo, pero elimina el permiso de inventario.
    // -----------------------------------------------------

    // -----------------------------------------------------
    // ADMINISTRADOR NO PROPIETARIO
    // Evita que el acceso global del propietario omita la
    // comprobación específica del permiso de inventario.
    // -----------------------------------------------------

    $administrador->update([
        'es_propietario' => false,
    ]);

    // -----------------------------------------------------
    // RETIRAR PERMISO DEL ROL
    // Conserva el rol administrador para superar el middleware,
    // pero elimina el permiso específico de inventario.
    // -----------------------------------------------------

    $rolAdministrador = Role::findByName(
        'administrador',
        'web'
    );

    $rolAdministrador->revokePermissionTo(
        'inventario.consumir'
    );

    $administradorSinPermiso = $administrador->fresh();

    expect($administradorSinPermiso)
        ->not->toBeNull();

    expect(
        $administradorSinPermiso?->can(
            'inventario.consumir'
        )
    )->toBeFalse();

    // -----------------------------------------------------
    // SOLICITUD RECHAZADA
    // La ruta debe responder con estado HTTP 403.
    // -----------------------------------------------------

    $response = $this
        ->actingAs($administrador->fresh())
        ->post(
            route(
                'admin.ordenes.inventario.reservar',
                [
                    'empresa' => $empresa->id,
                    'orden' => $orden->id,
                ]
            ),
            [
                'almacen_id' => $almacen->id,
                'producto_id' => $producto->id,
                'cantidad' => '5.5000',
                'motivo' => 'Intento HTTP sin permiso.',
            ]
        );

    $response->assertForbidden();

    // -----------------------------------------------------
    // EXISTENCIA SIN CAMBIOS
    // La solicitud rechazada no modifica el saldo consolidado.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('20.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');

    // -----------------------------------------------------
    // SIN RESERVA FORMAL
    // No debe crearse ningún detalle para la orden.
    // -----------------------------------------------------

    expect(
        ReservaInventario::query()->count()
    )->toBe(0)
        ->and($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id);
});

// ---------------------------------------------------------
// ALMACÉN DE OTRA EMPRESA
// Impide reservar inventario utilizando un almacén que no
// pertenece a la empresa indicada en la ruta.
// ---------------------------------------------------------

test('warehouse from another company cannot be used for http reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $orden,
    ] = prepararEntornoParaReservaHttp();

    // -----------------------------------------------------
    // EMPRESA EXTERNA
    // Crea una segunda empresa con su propia sucursal y
    // almacén para intentar cruzar información empresarial.
    // -----------------------------------------------------

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa externa HTTP',
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $sucursalExterna = Sucursal::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'codigo' => 'EXTERNA-HTTP',
        'nombre' => 'Sucursal externa HTTP',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $almacenExterno = Almacen::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'sucursal_id' => $sucursalExterna->id,
        'codigo' => 'ALM-EXTERNO-HTTP',
        'nombre' => 'Almacén externo HTTP',
        'tipo' => Almacen::TIPO_PRINCIPAL,
        'es_virtual' => false,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    // -----------------------------------------------------
    // SOLICITUD INVÁLIDA
    // Envía el almacén externo hacia una ruta vinculada con
    // la empresa original.
    // -----------------------------------------------------

    $response = $this
        ->actingAs($administrador)
        ->from(
            route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
        )
        ->post(
            route(
                'admin.ordenes.inventario.reservar',
                [
                    'empresa' => $empresa->id,
                    'orden' => $orden->id,
                ]
            ),
            [
                'almacen_id' => $almacenExterno->id,
                'producto_id' => $producto->id,
                'cantidad' => '5.5000',
                'motivo' => 'Intento de reserva con almacén externo.',
            ]
        );

    // -----------------------------------------------------
    // ERROR DE VALIDACIÓN
    // El Form Request debe rechazar específicamente el
    // almacén ajeno a la empresa de la ruta.
    // -----------------------------------------------------

    $response
        ->assertRedirect(
            route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
        )
        ->assertSessionHasErrors([
            'almacen_id',
        ]);

    // -----------------------------------------------------
    // EXISTENCIA SIN CAMBIOS
    // La validación debe ocurrir antes de ejecutar la acción
    // y modificar el inventario consolidado.
    // -----------------------------------------------------

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('20.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000');

    // -----------------------------------------------------
    // SIN RESERVAS FORMALES
    // No debe crearse ningún detalle para la orden.
    // -----------------------------------------------------

    expect(
        ReservaInventario::query()->count()
    )->toBe(0)
        ->and($almacen->empresa_id)
        ->toBe($empresa->id)
        ->and($almacenExterno->empresa_id)
        ->toBe($otraEmpresa->id)
        ->and($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id);
});

// ---------------------------------------------------------
// PRODUCTO DE OTRA EMPRESA
// Impide reservar un producto que no pertenece a la empresa
// indicada en la ruta administrativa.
// ---------------------------------------------------------

test('product from another company cannot be used for http reservation', function () {
    [
        $administrador,
        $empresa,
        $sucursal,
        $almacen,
        $categoria,
        $producto,
        $existencia,
        $orden,
    ] = prepararEntornoParaReservaHttp();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa externa HTTP',
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $categoriaExterna = CategoriaProducto::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'nombre' => 'Categoría externa HTTP',
        'descripcion' => 'Categoría perteneciente a otra empresa.',
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $productoExterno = Producto::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'categoria_producto_id' => $categoriaExterna->id,
        'proveedor_principal_id' => null,
        'sku' => 'PRODUCTO-EXTERNO-HTTP',
        'codigo_barras' => '7503333333333',
        'nombre' => 'Producto externo HTTP',
        'descripcion' => 'Producto perteneciente a otra empresa.',
        'marca' => 'Marca externa',
        'modelo' => 'MODELO-EXTERNO',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 500,
        'precio_base' => 750,
        'activo' => true,
        'creado_por_id' => $administrador->id,
    ]);

    $response = $this
        ->actingAs($administrador)
        ->from(
            route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
        )
        ->post(
            route(
                'admin.ordenes.inventario.reservar',
                [
                    'empresa' => $empresa->id,
                    'orden' => $orden->id,
                ]
            ),
            [
                'almacen_id' => $almacen->id,
                'producto_id' => $productoExterno->id,
                'cantidad' => '5.5000',
                'motivo' => 'Intento de reserva con producto externo.',
            ]
        );

    $response
        ->assertRedirect(
            route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
        )
        ->assertSessionHasErrors([
            'producto_id',
        ]);

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('20.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(ReservaInventario::query()->count())
        ->toBe(0)
        ->and($producto->empresa_id)
        ->toBe($empresa->id)
        ->and($productoExterno->empresa_id)
        ->toBe($otraEmpresa->id)
        ->and($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id);
});

// ---------------------------------------------------------
// CANTIDAD HTTP INVÁLIDA
// La solicitud rechaza cantidades vacías, no numéricas,
// negativas, en cero o con más de cuatro decimales.
// ---------------------------------------------------------

test('http reservation requires valid positive quantity', function (
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
        $orden,
    ] = prepararEntornoParaReservaHttp();

    $response = $this
        ->actingAs($administrador)
        ->from(
            route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
        )
        ->post(
            route(
                'admin.ordenes.inventario.reservar',
                [
                    'empresa' => $empresa->id,
                    'orden' => $orden->id,
                ]
            ),
            [
                'almacen_id' => $almacen->id,
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'motivo' => 'Validación HTTP de la cantidad.',
            ]
        );

    $response
        ->assertRedirect(
            route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
        )
        ->assertSessionHasErrors([
            'cantidad',
        ]);

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('20.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(ReservaInventario::query()->count())
        ->toBe(0)
        ->and($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id);
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
// MOTIVO HTTP INVÁLIDO
// Toda reserva requiere una justificación válida de hasta
// mil caracteres.
// ---------------------------------------------------------

test('http reservation requires valid reason', function (
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
        $orden,
    ] = prepararEntornoParaReservaHttp();

    $response = $this
        ->actingAs($administrador)
        ->from(
            route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
        )
        ->post(
            route(
                'admin.ordenes.inventario.reservar',
                [
                    'empresa' => $empresa->id,
                    'orden' => $orden->id,
                ]
            ),
            [
                'almacen_id' => $almacen->id,
                'producto_id' => $producto->id,
                'cantidad' => '5.5000',
                'motivo' => $motivo,
            ]
        );

    $response
        ->assertRedirect(
            route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
        )
        ->assertSessionHasErrors([
            'motivo',
        ]);

    $existencia->refresh();

    expect($existencia->cantidad)
        ->toBe('20.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('20.0000')
        ->and($existencia->costo_promedio)
        ->toBe('900.0000')
        ->and(ReservaInventario::query()->count())
        ->toBe(0)
        ->and($sucursal->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->empresa_id)
        ->toBe($empresa->id);
})->with([
    'motivo vacío' => [
        '   ',
    ],
    'motivo mayor de mil caracteres' => [
        str_repeat('A', 1001),
    ],
]);
