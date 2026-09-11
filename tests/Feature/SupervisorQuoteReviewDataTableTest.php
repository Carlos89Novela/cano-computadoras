<?php

use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function crearUsuarioParaTablaRevisionSupervisor(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function crearOrdenParaTablaRevisionSupervisor(
    User $cliente,
    string $folio,
    EstadoRevisionCotizacion $estadoRevision
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Supervisor DataTable',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar la bandeja del supervisor.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'diagnostico' => 'Se detectó una falla en la fuente.',
        'costo_estimado' => 850,
        'costo_final' => null,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'estado_revision_cotizacion' => $estadoRevision,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignarOrdenParaTablaRevisionSupervisor(
    OrdenServicio $orden,
    User $empleado,
    User $supervisor
): OrdenAsignacion {
    return OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now(),
        'finalizado_at' => null,
        'activo' => true,
        'observaciones' => 'Preparar diagnóstico y cotización.',
    ]);
}

function parametrosParaTablaRevisionSupervisor(
    string $busqueda = '',
    int $length = 10
): array {
    return [
        'draw' => 1,
        'start' => 0,
        'length' => $length,
        'search' => [
            'value' => $busqueda,
        ],
        'order' => [
            [
                'column' => 6,
                'dir' => 'desc',
            ],
        ],
        'columns' => [
            [
                'data' => 'folio',
            ],
            [
                'data' => 'empleado',
            ],
            [
                'data' => 'cliente',
            ],
            [
                'data' => 'equipo',
            ],
            [
                'data' => 'diagnostico',
            ],
            [
                'data' => 'costo_estimado',
            ],
            [
                'data' => 'fecha_solicitud',
            ],
            [
                'data' => 'orden_id',
            ],
        ],
    ];
}

test('supervisor table returns only pending quote reviews', function () {
    $cliente = crearUsuarioParaTablaRevisionSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaTablaRevisionSupervisor(
        'supervisor'
    );

    $empleado = crearUsuarioParaTablaRevisionSupervisor(
        'empleado'
    );

    $ordenPendiente = crearOrdenParaTablaRevisionSupervisor(
        $cliente,
        'REP-TABLA-REVISION-PENDIENTE-001',
        EstadoRevisionCotizacion::PENDIENTE
    );

    $ordenAprobada = crearOrdenParaTablaRevisionSupervisor(
        $cliente,
        'REP-TABLA-REVISION-APROBADA-001',
        EstadoRevisionCotizacion::APROBADA
    );

    $ordenRechazada = crearOrdenParaTablaRevisionSupervisor(
        $cliente,
        'REP-TABLA-REVISION-RECHAZADA-001',
        EstadoRevisionCotizacion::RECHAZADA
    );

    $ordenSinSolicitar = crearOrdenParaTablaRevisionSupervisor(
        $cliente,
        'REP-TABLA-REVISION-SIN-SOLICITAR-001',
        EstadoRevisionCotizacion::SIN_SOLICITAR
    );

    asignarOrdenParaTablaRevisionSupervisor(
        $ordenPendiente,
        $empleado,
        $supervisor
    );

    asignarOrdenParaTablaRevisionSupervisor(
        $ordenAprobada,
        $empleado,
        $supervisor
    );

    asignarOrdenParaTablaRevisionSupervisor(
        $ordenRechazada,
        $empleado,
        $supervisor
    );

    asignarOrdenParaTablaRevisionSupervisor(
        $ordenSinSolicitar,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($supervisor)
        ->getJson(
            route(
                'supervisor.cotizaciones.data',
                parametrosParaTablaRevisionSupervisor()
            )
        );

    $response
        ->assertOk()
        ->assertJsonPath('draw', 1)
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.folio',
            'REP-TABLA-REVISION-PENDIENTE-001'
        )
        ->assertJsonPath(
            'data.0.empleado',
            $empleado->name
        )
        ->assertJsonPath(
            'data.0.cliente',
            $cliente->name
        )
        ->assertJsonPath(
            'data.0.orden_id',
            $ordenPendiente->id
        );

    $contenido = $response->getContent();

    expect($contenido)
        ->toContain('REP-TABLA-REVISION-PENDIENTE-001')
        ->not->toContain('REP-TABLA-REVISION-APROBADA-001')
        ->not->toContain('REP-TABLA-REVISION-RECHAZADA-001')
        ->not->toContain('REP-TABLA-REVISION-SIN-SOLICITAR-001');
});

test('supervisor table applies search to pending quote reviews', function () {
    $cliente = crearUsuarioParaTablaRevisionSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaTablaRevisionSupervisor(
        'supervisor'
    );

    $empleado = crearUsuarioParaTablaRevisionSupervisor(
        'empleado'
    );

    $ordenEncontrada = crearOrdenParaTablaRevisionSupervisor(
        $cliente,
        'REP-BUSQUEDA-COTIZACION-001',
        EstadoRevisionCotizacion::PENDIENTE
    );

    $ordenNoEncontrada = crearOrdenParaTablaRevisionSupervisor(
        $cliente,
        'REP-OTRA-COTIZACION-001',
        EstadoRevisionCotizacion::PENDIENTE
    );

    asignarOrdenParaTablaRevisionSupervisor(
        $ordenEncontrada,
        $empleado,
        $supervisor
    );

    asignarOrdenParaTablaRevisionSupervisor(
        $ordenNoEncontrada,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($supervisor)
        ->getJson(
            route(
                'supervisor.cotizaciones.data',
                parametrosParaTablaRevisionSupervisor(
                    'BUSQUEDA'
                )
            )
        );

    $response
        ->assertOk()
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.folio',
            'REP-BUSQUEDA-COTIZACION-001'
        );

    expect($response->getContent())
        ->not->toContain('REP-OTRA-COTIZACION-001');
});

test('supervisor table limits the requested page size', function () {
    $cliente = crearUsuarioParaTablaRevisionSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaTablaRevisionSupervisor(
        'supervisor'
    );

    $empleado = crearUsuarioParaTablaRevisionSupervisor(
        'empleado'
    );

    for ($indice = 1; $indice <= 105; $indice++) {
        $orden = crearOrdenParaTablaRevisionSupervisor(
            $cliente,
            'REP-LIMITE-COTIZACION-'
            .str_pad(
                (string) $indice,
                3,
                '0',
                STR_PAD_LEFT
            ),
            EstadoRevisionCotizacion::PENDIENTE
        );

        asignarOrdenParaTablaRevisionSupervisor(
            $orden,
            $empleado,
            $supervisor
        );
    }

    $response = $this
        ->actingAs($supervisor)
        ->getJson(
            route(
                'supervisor.cotizaciones.data',
                parametrosParaTablaRevisionSupervisor(
                    '',
                    500
                )
            )
        );

    $response
        ->assertOk()
        ->assertJsonPath('recordsTotal', 105)
        ->assertJsonPath('recordsFiltered', 105)
        ->assertJsonCount(100, 'data');
});

test('non supervisor roles cannot access the supervisor quote table', function () {
    $administrador = crearUsuarioParaTablaRevisionSupervisor(
        'administrador'
    );

    $empleado = crearUsuarioParaTablaRevisionSupervisor(
        'empleado'
    );

    $cliente = crearUsuarioParaTablaRevisionSupervisor(
        'cliente'
    );

    $ruta = route(
        'supervisor.cotizaciones.data',
        parametrosParaTablaRevisionSupervisor()
    );

    $this
        ->actingAs($administrador)
        ->getJson($ruta)
        ->assertForbidden();

    $this
        ->actingAs($empleado)
        ->getJson($ruta)
        ->assertForbidden();

    $this
        ->actingAs($cliente)
        ->getJson($ruta)
        ->assertForbidden();
});
