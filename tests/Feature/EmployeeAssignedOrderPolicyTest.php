<?php

use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Policies\OrdenServicioPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function usuarioParaPolicyDeEmpleado(string $rol): User
{
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaPolicyDeEmpleado(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Policy',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar acceso técnico.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

test('assigned employee can view the technical order', function () {
    $cliente = usuarioParaPolicyDeEmpleado('cliente');
    $empleado = usuarioParaPolicyDeEmpleado('empleado');
    $supervisor = usuarioParaPolicyDeEmpleado('supervisor');

    $orden = ordenParaPolicyDeEmpleado(
        $cliente,
        'REP-POLICY-EMPLOYEE-001'
    );

    OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now(),
        'activo' => true,
    ]);

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->viewAssigned($empleado, $orden)
    )->toBeTrue();
});

test('another employee cannot view the assigned technical order', function () {
    $cliente = usuarioParaPolicyDeEmpleado('cliente');
    $empleadoAsignado = usuarioParaPolicyDeEmpleado('empleado');
    $otroEmpleado = usuarioParaPolicyDeEmpleado('empleado');
    $supervisor = usuarioParaPolicyDeEmpleado('supervisor');

    $orden = ordenParaPolicyDeEmpleado(
        $cliente,
        'REP-POLICY-EMPLOYEE-002'
    );

    OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleadoAsignado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now(),
        'activo' => true,
    ]);

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->viewAssigned($otroEmpleado, $orden)
    )->toBeFalse();
});

test('inactive assignment does not grant technical access', function () {
    $cliente = usuarioParaPolicyDeEmpleado('cliente');
    $empleado = usuarioParaPolicyDeEmpleado('empleado');
    $supervisor = usuarioParaPolicyDeEmpleado('supervisor');

    $orden = ordenParaPolicyDeEmpleado(
        $cliente,
        'REP-POLICY-EMPLOYEE-003'
    );

    OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now()->subDay(),
        'finalizado_at' => now(),
        'activo' => false,
    ]);

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->viewAssigned($empleado, $orden)
    )->toBeFalse();
});

test('client permission does not grant employee technical access', function () {
    $cliente = usuarioParaPolicyDeEmpleado('cliente');

    $orden = ordenParaPolicyDeEmpleado(
        $cliente,
        'REP-POLICY-CLIENT-001'
    );

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->viewAssigned($cliente, $orden)
    )->toBeFalse();
});
