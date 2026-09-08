<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoEquipo;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearDatosParaRutasAdministrativas(): array
{
    $cliente = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => TipoEquipo::LAPTOP->value,
        'marca' => 'Dell',
        'modelo' => 'Latitude de prueba',
        'numero_serie' => 'TEST-ADMIN-ACCESS-001',
        'descripcion' => 'Equipo para comprobar rutas administrativas.',
    ]);

    $servicio = Servicio::query()->create([
        'nombre' => 'Servicio de acceso administrativo',
        'descripcion' => 'Servicio utilizado para probar el middleware.',
        'precio' => 500.00,
        'activo' => true,
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'TEST-ADMIN-ACCESS-ORDER-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => $servicio->id,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'estado' => EstadoOrden::RECIBIDO->value,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    return [
        'equipo' => $equipo,
        'orden' => $orden,
        'servicio' => $servicio,
    ];
}

function rutasAdministrativasProtegidas(
    OrdenServicio $orden,
    Servicio $servicio
): array {
    return [
        [
            'method' => 'get',
            'url' => route('admin.ordenes.index'),
            'data' => [],
        ],
        [
            'method' => 'get',
            'url' => route('admin.ordenes.data'),
            'data' => [],
        ],
        [
            'method' => 'post',
            'url' => route('admin.ordenes.bulk_update'),
            'data' => [
                'ids' => [$orden->id],
                'estado' => EstadoOrden::EN_REPARACION->value,
            ],
        ],
        [
            'method' => 'get',
            'url' => route('admin.ordenes.exportar.csv'),
            'data' => [],
        ],
        [
            'method' => 'get',
            'url' => route('admin.ordenes.exportar.pdf'),
            'data' => [],
        ],
        [
            'method' => 'get',
            'url' => route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ]),
            'data' => [],
        ],
        [
            'method' => 'put',
            'url' => route('admin.ordenes.update', [
                'orden' => $orden->id,
            ]),
            'data' => [
                'estado' => EstadoOrden::EN_REPARACION->value,
            ],
        ],
        [
            'method' => 'get',
            'url' => route('admin.ordenes.pdf', [
                'orden' => $orden->id,
            ]),
            'data' => [],
        ],
        [
            'method' => 'get',
            'url' => route('admin.servicios.index'),
            'data' => [],
        ],
        [
            'method' => 'get',
            'url' => route('admin.servicios.create'),
            'data' => [],
        ],
        [
            'method' => 'post',
            'url' => route('admin.servicios.store'),
            'data' => [
                'nombre' => 'Servicio no autorizado',
                'descripcion' => 'Intento realizado sin permisos.',
                'precio' => 100.00,
                'activo' => true,
            ],
        ],
        [
            'method' => 'get',
            'url' => route('admin.servicios.edit', [
                'servicio' => $servicio->id,
            ]),
            'data' => [],
        ],
        [
            'method' => 'put',
            'url' => route('admin.servicios.update', [
                'servicio' => $servicio->id,
            ]),
            'data' => [
                'nombre' => $servicio->nombre,
                'descripcion' => $servicio->descripcion,
                'precio' => $servicio->precio,
                'activo' => true,
            ],
        ],
        [
            'method' => 'delete',
            'url' => route('admin.servicios.destroy', [
                'servicio' => $servicio->id,
            ]),
            'data' => [],
        ],
    ];
}

test('guest users are redirected from every administrative route', function () {
    $datos = crearDatosParaRutasAdministrativas();

    $rutas = rutasAdministrativasProtegidas(
        $datos['orden'],
        $datos['servicio']
    );

    foreach ($rutas as $ruta) {
        $response = $this->{$ruta['method']}(
            $ruta['url'],
            $ruta['data']
        );

        $response->assertRedirect(route('login'));
    }
});

test('regular users receive forbidden response from every administrative route', function () {
    $usuario = User::factory()->create();
    $datos = crearDatosParaRutasAdministrativas();

    $rutas = rutasAdministrativasProtegidas(
        $datos['orden'],
        $datos['servicio']
    );

    foreach ($rutas as $ruta) {
        $response = $this
            ->actingAs($usuario)
            ->{$ruta['method']}(
                $ruta['url'],
                $ruta['data']
            );

        $response->assertForbidden();
    }
});

test('blocked administrative requests do not modify records', function () {
    $usuario = User::factory()->create();
    $datos = crearDatosParaRutasAdministrativas();

    $orden = $datos['orden'];
    $servicio = $datos['servicio'];

    $this
        ->actingAs($usuario)
        ->put(
            route('admin.ordenes.update', [
                'orden' => $orden->id,
            ]),
            [
                'estado' => EstadoOrden::EN_REPARACION->value,
            ]
        )
        ->assertForbidden();

    $this
        ->actingAs($usuario)
        ->delete(
            route('admin.servicios.destroy', [
                'servicio' => $servicio->id,
            ])
        )
        ->assertForbidden();

    $this->assertDatabaseHas('orden_servicios', [
        'id' => $orden->id,
        'estado' => EstadoOrden::RECIBIDO->value,
    ]);

    $this->assertDatabaseHas('servicios', [
        'id' => $servicio->id,
        'activo' => true,
    ]);
});
