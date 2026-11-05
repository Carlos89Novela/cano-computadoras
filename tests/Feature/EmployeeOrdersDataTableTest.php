<?php

use App\Enums\EstadoOrden;
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

function crearUsuarioParaTablaEmpleado(string $rol): User
{
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function crearOrdenParaTablaEmpleado(
    User $cliente,
    string $folio,
    string $estado = 'Recibido'
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude DataTable',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar la tabla del empleado.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo presenta una falla al iniciar.',
        'estado' => $estado,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function crearAsignacionParaTablaEmpleado(
    OrdenServicio $orden,
    User $empleado,
    User $supervisor,
    bool $activa = true
): OrdenAsignacion {
    return OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now(),
        'finalizado_at' => $activa ? null : now(),
        'activo' => $activa,
        'observaciones' => 'Revisar el sistema de alimentación.',
    ]);
}

function parametrosParaTablaEmpleado(
    string $busqueda = ''
): array {
    return [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'search' => [
            'value' => $busqueda,
        ],
        'order' => [
            [
                'column' => 5,
                'dir' => 'desc',
            ],
        ],
        'columns' => [
            [
                'data' => 'folio',
            ],
            [
                'data' => 'cliente',
            ],
            [
                'data' => 'equipo',
            ],
            [
                'data' => 'servicio',
            ],
            [
                'data' => 'estado',
            ],
            [
                'data' => 'fecha_asignacion',
            ],
            [
                'data' => 'acciones',
            ],
        ],
    ];
}

test('employee table returns only active assignments belonging to the authenticated employee', function () {
    $cliente = crearUsuarioParaTablaEmpleado('cliente');
    $supervisor = crearUsuarioParaTablaEmpleado('supervisor');
    $empleado = crearUsuarioParaTablaEmpleado('empleado');
    $otroEmpleado = crearUsuarioParaTablaEmpleado('empleado');

    $ordenPropia = crearOrdenParaTablaEmpleado(
        $cliente,
        'REP-TABLA-PROPIA-001'
    );

    $ordenAjena = crearOrdenParaTablaEmpleado(
        $cliente,
        'REP-TABLA-AJENA-001'
    );

    $ordenInactiva = crearOrdenParaTablaEmpleado(
        $cliente,
        'REP-TABLA-INACTIVA-001'
    );

    crearAsignacionParaTablaEmpleado(
        $ordenPropia,
        $empleado,
        $supervisor
    );

    crearAsignacionParaTablaEmpleado(
        $ordenAjena,
        $otroEmpleado,
        $supervisor
    );

    crearAsignacionParaTablaEmpleado(
        $ordenInactiva,
        $empleado,
        $supervisor,
        false
    );

    $response = $this
        ->actingAs($empleado)
        ->getJson(
            route(
                'empleado.ordenes.data',
                parametrosParaTablaEmpleado()
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
            'REP-TABLA-PROPIA-001'
        );

    expect($response->getContent())
        ->toContain('REP-TABLA-PROPIA-001')
        ->not->toContain('REP-TABLA-AJENA-001')
        ->not->toContain('REP-TABLA-INACTIVA-001');
});

test('employee table excludes finalized repair orders', function () {
    $cliente = crearUsuarioParaTablaEmpleado('cliente');
    $supervisor = crearUsuarioParaTablaEmpleado('supervisor');
    $empleado = crearUsuarioParaTablaEmpleado('empleado');

    $ordenActiva = crearOrdenParaTablaEmpleado(
        $cliente,
        'REP-TABLA-ACTIVA-001',
        EstadoOrden::EN_REPARACION->value
    );

    $ordenFinalizada = crearOrdenParaTablaEmpleado(
        $cliente,
        'REP-TABLA-FINALIZADA-001',
        EstadoOrden::ENTREGADO->value
    );

    crearAsignacionParaTablaEmpleado(
        $ordenActiva,
        $empleado,
        $supervisor
    );

    crearAsignacionParaTablaEmpleado(
        $ordenFinalizada,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->getJson(
            route(
                'empleado.ordenes.data',
                parametrosParaTablaEmpleado()
            )
        );

    $response
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath(
            'data.0.folio',
            'REP-TABLA-ACTIVA-001'
        );

    expect($response->getContent())
        ->not->toContain('REP-TABLA-FINALIZADA-001');
});

test('employee table search does not expose assignments belonging to another employee', function () {
    $cliente = crearUsuarioParaTablaEmpleado('cliente');
    $supervisor = crearUsuarioParaTablaEmpleado('supervisor');
    $empleado = crearUsuarioParaTablaEmpleado('empleado');
    $otroEmpleado = crearUsuarioParaTablaEmpleado('empleado');

    $ordenPropia = crearOrdenParaTablaEmpleado(
        $cliente,
        'REP-BUSQUEDA-PROPIA-001'
    );

    $ordenAjena = crearOrdenParaTablaEmpleado(
        $cliente,
        'REP-BUSQUEDA-AJENA-001'
    );

    crearAsignacionParaTablaEmpleado(
        $ordenPropia,
        $empleado,
        $supervisor
    );

    crearAsignacionParaTablaEmpleado(
        $ordenAjena,
        $otroEmpleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->getJson(
            route(
                'empleado.ordenes.data',
                parametrosParaTablaEmpleado('AJENA')
            )
        );

    $response
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonPath('recordsFiltered', 0)
        ->assertJsonCount(0, 'data');

    expect($response->getContent())
        ->not->toContain('REP-BUSQUEDA-AJENA-001');
});

test('non employee roles cannot access the employee table endpoint', function () {
    $administrador = crearUsuarioParaTablaEmpleado(
        'administrador'
    );

    $supervisor = crearUsuarioParaTablaEmpleado(
        'supervisor'
    );

    $cliente = crearUsuarioParaTablaEmpleado(
        'cliente'
    );

    $ruta = route(
        'empleado.ordenes.data',
        parametrosParaTablaEmpleado()
    );

    $this
        ->actingAs($administrador)
        ->getJson($ruta)
        ->assertForbidden();

    $this
        ->actingAs($supervisor)
        ->getJson($ruta)
        ->assertForbidden();

    $this
        ->actingAs($cliente)
        ->getJson($ruta)
        ->assertForbidden();
});
