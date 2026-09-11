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

function usuarioParaDetalleTecnico(string $rol): User
{
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaDetalleTecnico(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude técnico',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo utilizado para probar el detalle técnico.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo presenta una falla durante el inicio.',
        'estado' => EstadoOrden::RECIBIDO->value,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignarOrdenParaDetalleTecnico(
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
        'observaciones' => 'Revisar alimentación y sistema de encendido.',
    ]);
}

test('assigned employee can open the technical order detail', function () {
    $cliente = usuarioParaDetalleTecnico('cliente');
    $supervisor = usuarioParaDetalleTecnico('supervisor');
    $empleado = usuarioParaDetalleTecnico('empleado');

    $orden = ordenParaDetalleTecnico(
        $cliente,
        'REP-EMPLOYEE-DETAIL-001'
    );

    asignarOrdenParaDetalleTecnico(
        $orden,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->get(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        );

    $response
        ->assertOk()
        ->assertSee('REP-EMPLOYEE-DETAIL-001')
        ->assertSee('Detalle técnico');
});

test('another employee cannot open the assigned order', function () {
    $cliente = usuarioParaDetalleTecnico('cliente');
    $supervisor = usuarioParaDetalleTecnico('supervisor');
    $empleadoAsignado = usuarioParaDetalleTecnico('empleado');
    $otroEmpleado = usuarioParaDetalleTecnico('empleado');

    $orden = ordenParaDetalleTecnico(
        $cliente,
        'REP-EMPLOYEE-DENIED-001'
    );

    asignarOrdenParaDetalleTecnico(
        $orden,
        $empleadoAsignado,
        $supervisor
    );

    $this
        ->actingAs($otroEmpleado)
        ->get(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertForbidden();
});

test('inactive assignment does not grant access to the technical detail', function () {
    $cliente = usuarioParaDetalleTecnico('cliente');
    $supervisor = usuarioParaDetalleTecnico('supervisor');
    $empleado = usuarioParaDetalleTecnico('empleado');

    $orden = ordenParaDetalleTecnico(
        $cliente,
        'REP-EMPLOYEE-INACTIVE-001'
    );

    asignarOrdenParaDetalleTecnico(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    $this
        ->actingAs($empleado)
        ->get(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertForbidden();
});

test('client cannot open the employee technical detail', function () {
    $cliente = usuarioParaDetalleTecnico('cliente');
    $supervisor = usuarioParaDetalleTecnico('supervisor');
    $empleado = usuarioParaDetalleTecnico('empleado');

    $orden = ordenParaDetalleTecnico(
        $cliente,
        'REP-CLIENT-TECHNICAL-DENIED-001'
    );

    asignarOrdenParaDetalleTecnico(
        $orden,
        $empleado,
        $supervisor
    );

    $this
        ->actingAs($cliente)
        ->get(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertForbidden();
});

test('guest is redirected before opening technical order detail', function () {
    $cliente = usuarioParaDetalleTecnico('cliente');
    $supervisor = usuarioParaDetalleTecnico('supervisor');
    $empleado = usuarioParaDetalleTecnico('empleado');

    $orden = ordenParaDetalleTecnico(
        $cliente,
        'REP-GUEST-TECHNICAL-DENIED-001'
    );

    asignarOrdenParaDetalleTecnico(
        $orden,
        $empleado,
        $supervisor
    );

    $this
        ->get(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertRedirect(route('login'));
});
