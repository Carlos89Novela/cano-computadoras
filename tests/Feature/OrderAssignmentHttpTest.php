<?php

use App\Enums\EstadoOrden;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function usuarioParaAsignacionHttp(string $rol): User
{
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaAsignacionHttp(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude HTTP',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para prueba HTTP de asignación.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo presenta una falla durante el arranque.',
        'estado' => EstadoOrden::RECIBIDO->value,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

test('supervisor can assign an order through the endpoint', function () {
    $cliente = usuarioParaAsignacionHttp('cliente');
    $supervisor = usuarioParaAsignacionHttp('supervisor');
    $empleado = usuarioParaAsignacionHttp('empleado');

    $orden = ordenParaAsignacionHttp(
        $cliente,
        'REP-HTTP-ASSIGN-001'
    );

    $response = $this
        ->actingAs($supervisor)
        ->from(route('supervisor.dashboard'))
        ->post(
            route('operacion.ordenes.asignar', [
                'orden' => $orden->id,
            ]),
            [
                'empleado_id' => $empleado->id,
                'observaciones' => 'Revisar el sistema de alimentación.',
            ]
        );

    $response
        ->assertRedirect(route('supervisor.dashboard'))
        ->assertSessionHas(
            'success',
            'La reparación fue asignada correctamente.'
        );

    $this->assertDatabaseHas('orden_asignaciones', [
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'activo' => true,
        'observaciones' => 'Revisar el sistema de alimentación.',
    ]);
});

test('administrator can assign an order through the endpoint', function () {
    $cliente = usuarioParaAsignacionHttp('cliente');
    $administrador = usuarioParaAsignacionHttp('administrador');
    $empleado = usuarioParaAsignacionHttp('empleado');

    $orden = ordenParaAsignacionHttp(
        $cliente,
        'REP-HTTP-ADMIN-001'
    );

    $this
        ->actingAs($administrador)
        ->from(route('admin.dashboard'))
        ->post(
            route('operacion.ordenes.asignar', [
                'orden' => $orden->id,
            ]),
            [
                'empleado_id' => $empleado->id,
                'observaciones' => null,
            ]
        )
        ->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseHas('orden_asignaciones', [
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $administrador->id,
        'activo' => true,
    ]);
});

test('employee cannot assign orders through the endpoint', function () {
    $cliente = usuarioParaAsignacionHttp('cliente');
    $empleado = usuarioParaAsignacionHttp('empleado');
    $otroEmpleado = usuarioParaAsignacionHttp('empleado');

    $orden = ordenParaAsignacionHttp(
        $cliente,
        'REP-HTTP-DENIED-001'
    );

    $this
        ->actingAs($empleado)
        ->post(
            route('operacion.ordenes.asignar', [
                'orden' => $orden->id,
            ]),
            [
                'empleado_id' => $otroEmpleado->id,
            ]
        )
        ->assertForbidden();

    expect($orden->asignaciones()->count())
        ->toBe(0);
});

test('client cannot assign orders through the endpoint', function () {
    $cliente = usuarioParaAsignacionHttp('cliente');
    $empleado = usuarioParaAsignacionHttp('empleado');

    $orden = ordenParaAsignacionHttp(
        $cliente,
        'REP-HTTP-CLIENT-DENIED-001'
    );

    $this
        ->actingAs($cliente)
        ->post(
            route('operacion.ordenes.asignar', [
                'orden' => $orden->id,
            ]),
            [
                'empleado_id' => $empleado->id,
            ]
        )
        ->assertForbidden();

    expect($orden->asignaciones()->count())
        ->toBe(0);
});

test('assignment endpoint validates the selected employee', function () {
    $cliente = usuarioParaAsignacionHttp('cliente');
    $supervisor = usuarioParaAsignacionHttp('supervisor');

    $orden = ordenParaAsignacionHttp(
        $cliente,
        'REP-HTTP-VALIDATION-001'
    );

    $response = $this
        ->actingAs($supervisor)
        ->from(route('supervisor.dashboard'))
        ->post(
            route('operacion.ordenes.asignar', [
                'orden' => $orden->id,
            ]),
            [
                'empleado_id' => null,
                'observaciones' => null,
            ]
        );

    $response
        ->assertRedirect(route('supervisor.dashboard'))
        ->assertSessionHasErrors('empleado_id');

    expect($orden->asignaciones()->count())
        ->toBe(0);
});
