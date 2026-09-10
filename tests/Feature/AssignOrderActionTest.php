<?php

use App\Actions\Ordenes\AsignarOrden;
use App\Enums\EstadoOrden;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function crearUsuarioParaAsignacion(string $rol): User
{
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function crearOrdenParaAsignacion(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo utilizado para probar asignaciones.',
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

test('supervisor can assign an order to an employee', function () {
    $cliente = crearUsuarioParaAsignacion('cliente');
    $supervisor = crearUsuarioParaAsignacion('supervisor');
    $empleado = crearUsuarioParaAsignacion('empleado');

    $orden = crearOrdenParaAsignacion(
        $cliente,
        'REP-ASSIGN-SUPERVISOR-001'
    );

    $asignacion = app(AsignarOrden::class)->ejecutar(
        $orden,
        $empleado,
        $supervisor,
        'Revisar el sistema de encendido.'
    );

    expect($asignacion->orden_servicio_id)
        ->toBe($orden->id)
        ->and($asignacion->empleado_id)
        ->toBe($empleado->id)
        ->and($asignacion->asignado_por_id)
        ->toBe($supervisor->id)
        ->and($asignacion->activo)
        ->toBeTrue()
        ->and($asignacion->finalizado_at)
        ->toBeNull()
        ->and($asignacion->observaciones)
        ->toBe('Revisar el sistema de encendido.');

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $supervisor->id,
        'comentarios' => 'Reparación asignada a '.$empleado->name.'.',
        'mensaje_cliente' => null,
    ]);
});

test('administrator can assign an order to an employee', function () {
    $cliente = crearUsuarioParaAsignacion('cliente');
    $administrador = crearUsuarioParaAsignacion('administrador');
    $empleado = crearUsuarioParaAsignacion('empleado');

    $orden = crearOrdenParaAsignacion(
        $cliente,
        'REP-ASSIGN-ADMIN-001'
    );

    $asignacion = app(AsignarOrden::class)->ejecutar(
        $orden,
        $empleado,
        $administrador
    );

    expect($asignacion->empleado_id)
        ->toBe($empleado->id)
        ->and($asignacion->asignado_por_id)
        ->toBe($administrador->id)
        ->and($asignacion->activo)
        ->toBeTrue();
});

test('employee cannot assign an order', function () {
    $cliente = crearUsuarioParaAsignacion('cliente');
    $empleadoQueAsigna = crearUsuarioParaAsignacion('empleado');
    $empleadoSeleccionado = crearUsuarioParaAsignacion('empleado');

    $orden = crearOrdenParaAsignacion(
        $cliente,
        'REP-ASSIGN-DENIED-001'
    );

    expect(
        fn () => app(AsignarOrden::class)->ejecutar(
            $orden,
            $empleadoSeleccionado,
            $empleadoQueAsigna
        )
    )->toThrow(AuthorizationException::class);

    expect($orden->asignaciones()->count())
        ->toBe(0)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('an order cannot be assigned to a user without employee role', function () {
    $cliente = crearUsuarioParaAsignacion('cliente');
    $supervisor = crearUsuarioParaAsignacion('supervisor');

    $orden = crearOrdenParaAsignacion(
        $cliente,
        'REP-ASSIGN-INVALID-EMPLOYEE-001'
    );

    expect(
        fn () => app(AsignarOrden::class)->ejecutar(
            $orden,
            $cliente,
            $supervisor
        )
    )->toThrow(
        InvalidArgumentException::class,
        'El usuario seleccionado no tiene el rol de empleado.'
    );

    expect($orden->asignaciones()->count())
        ->toBe(0);
});

test('reassigning an order closes its previous assignment', function () {
    $cliente = crearUsuarioParaAsignacion('cliente');
    $supervisor = crearUsuarioParaAsignacion('supervisor');
    $primerEmpleado = crearUsuarioParaAsignacion('empleado');
    $segundoEmpleado = crearUsuarioParaAsignacion('empleado');

    $orden = crearOrdenParaAsignacion(
        $cliente,
        'REP-REASSIGN-001'
    );

    $primeraAsignacion = app(AsignarOrden::class)->ejecutar(
        $orden,
        $primerEmpleado,
        $supervisor,
        'Primera asignación.'
    );

    $segundaAsignacion = app(AsignarOrden::class)->ejecutar(
        $orden,
        $segundoEmpleado,
        $supervisor,
        'Reasignación por carga de trabajo.'
    );

    $primeraAsignacion->refresh();

    expect($primeraAsignacion->activo)
        ->toBeFalse()
        ->and($primeraAsignacion->finalizado_at)
        ->not->toBeNull()
        ->and($segundaAsignacion->activo)
        ->toBeTrue()
        ->and($segundaAsignacion->empleado_id)
        ->toBe($segundoEmpleado->id)
        ->and(
            $orden->asignaciones()
                ->where('activo', true)
                ->count()
        )
        ->toBe(1)
        ->and($orden->asignaciones()->count())
        ->toBe(2);

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $supervisor->id,
        'comentarios' => 'Reparación reasignada a '.$segundoEmpleado->name.'.',
    ]);
});

test('assigning the same employee twice does not create duplicates', function () {
    $cliente = crearUsuarioParaAsignacion('cliente');
    $supervisor = crearUsuarioParaAsignacion('supervisor');
    $empleado = crearUsuarioParaAsignacion('empleado');

    $orden = crearOrdenParaAsignacion(
        $cliente,
        'REP-ASSIGN-NO-DUPLICATE-001'
    );

    $primeraAsignacion = app(AsignarOrden::class)->ejecutar(
        $orden,
        $empleado,
        $supervisor,
        'Asignación original.'
    );

    $segundaAsignacion = app(AsignarOrden::class)->ejecutar(
        $orden,
        $empleado,
        $supervisor,
        'Intento repetido.'
    );

    expect($segundaAsignacion->is($primeraAsignacion))
        ->toBeTrue()
        ->and($orden->asignaciones()->count())
        ->toBe(1)
        ->and(
            $orden->historial()
                ->where(
                    'comentarios',
                    'Reparación asignada a '.$empleado->name.'.'
                )
                ->count()
        )
        ->toBe(1);
});
