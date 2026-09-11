<?php

use App\Actions\Ordenes\ActualizarTrabajoTecnico;
use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function usuarioParaTrabajoTecnico(string $rol): User
{
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaTrabajoTecnico(
    User $cliente,
    string $folio,
    string $estado = 'Recibido'
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Trabajo Técnico',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar actualizaciones técnicas.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo presenta una falla durante el inicio.',
        'diagnostico' => null,
        'costo_estimado' => null,
        'costo_final' => 1500,
        'estado' => $estado,
        'autorizacion' => EstadoAutorizacion::PENDIENTE->value,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignarParaTrabajoTecnico(
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
        'observaciones' => 'Realizar diagnóstico completo.',
    ]);
}

test('assigned employee can update diagnosis and estimated cost', function () {
    $cliente = usuarioParaTrabajoTecnico('cliente');
    $supervisor = usuarioParaTrabajoTecnico('supervisor');
    $empleado = usuarioParaTrabajoTecnico('empleado');

    $orden = ordenParaTrabajoTecnico(
        $cliente,
        'REP-TECHNICAL-UPDATE-001',
        EstadoOrden::EN_DIAGNOSTICO->value
    );

    asignarParaTrabajoTecnico(
        $orden,
        $empleado,
        $supervisor
    );

    $ordenActualizada = app(
        ActualizarTrabajoTecnico::class
    )->ejecutar(
        $orden,
        $empleado,
        [
            'diagnostico' => 'Se detectó una falla en el sistema de alimentación.',
            'costo_estimado' => 850,
            'comentario' => 'Se realizaron pruebas de voltaje.',
            'estado' => EstadoOrden::ENTREGADO->value,
            'costo_final' => 9999,
            'mensaje_cliente' => 'Este mensaje no debe guardarse.',
            'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
        ]
    );

    expect($ordenActualizada->diagnostico)
        ->toBe('Se detectó una falla en el sistema de alimentación.')
        ->and((float) $ordenActualizada->costo_estimado)
        ->toBe(850.0)
        ->and($ordenActualizada->estado)
        ->toBe(EstadoOrden::EN_DIAGNOSTICO->value)
        ->and((float) $ordenActualizada->costo_final)
        ->toBe(1500.0)
        ->and($ordenActualizada->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value);

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $empleado->id,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'comentarios' => 'Se realizaron pruebas de voltaje.',
        'mensaje_cliente' => null,
    ]);
});

test('technical changes without a comment create an automatic internal history entry', function () {
    $cliente = usuarioParaTrabajoTecnico('cliente');
    $supervisor = usuarioParaTrabajoTecnico('supervisor');
    $empleado = usuarioParaTrabajoTecnico('empleado');

    $orden = ordenParaTrabajoTecnico(
        $cliente,
        'REP-TECHNICAL-AUTOMATIC-HISTORY-001'
    );

    asignarParaTrabajoTecnico(
        $orden,
        $empleado,
        $supervisor
    );

    app(ActualizarTrabajoTecnico::class)->ejecutar(
        $orden,
        $empleado,
        [
            'diagnostico' => 'El disco presenta sectores dañados.',
            'costo_estimado' => 1200,
            'comentario' => null,
        ]
    );

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $empleado->id,
        'comentarios' => 'Información técnica actualizada por el empleado.',
        'mensaje_cliente' => null,
    ]);
});

test('new internal comment creates history even when technical values do not change', function () {
    $cliente = usuarioParaTrabajoTecnico('cliente');
    $supervisor = usuarioParaTrabajoTecnico('supervisor');
    $empleado = usuarioParaTrabajoTecnico('empleado');

    $orden = ordenParaTrabajoTecnico(
        $cliente,
        'REP-TECHNICAL-COMMENT-001'
    );

    $orden->update([
        'diagnostico' => 'Diagnóstico existente.',
        'costo_estimado' => 500,
    ]);

    asignarParaTrabajoTecnico(
        $orden,
        $empleado,
        $supervisor
    );

    app(ActualizarTrabajoTecnico::class)->ejecutar(
        $orden,
        $empleado,
        [
            'diagnostico' => 'Diagnóstico existente.',
            'costo_estimado' => 500,
            'comentario' => 'Se verificó nuevamente el diagnóstico.',
        ]
    );

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $empleado->id,
        'comentarios' => 'Se verificó nuevamente el diagnóstico.',
        'mensaje_cliente' => null,
    ]);
});

test('unchanged technical data without a comment does not create history', function () {
    $cliente = usuarioParaTrabajoTecnico('cliente');
    $supervisor = usuarioParaTrabajoTecnico('supervisor');
    $empleado = usuarioParaTrabajoTecnico('empleado');

    $orden = ordenParaTrabajoTecnico(
        $cliente,
        'REP-TECHNICAL-NO-CHANGE-001'
    );

    $orden->update([
        'diagnostico' => 'Diagnóstico sin cambios.',
        'costo_estimado' => 700,
    ]);

    asignarParaTrabajoTecnico(
        $orden,
        $empleado,
        $supervisor
    );

    app(ActualizarTrabajoTecnico::class)->ejecutar(
        $orden,
        $empleado,
        [
            'diagnostico' => 'Diagnóstico sin cambios.',
            'costo_estimado' => '700.00',
            'comentario' => null,
        ]
    );

    expect($orden->historial()->count())
        ->toBe(0);
});

test('employee without an active assignment cannot update technical work', function () {
    $cliente = usuarioParaTrabajoTecnico('cliente');
    $supervisor = usuarioParaTrabajoTecnico('supervisor');
    $empleadoAsignado = usuarioParaTrabajoTecnico('empleado');
    $otroEmpleado = usuarioParaTrabajoTecnico('empleado');

    $orden = ordenParaTrabajoTecnico(
        $cliente,
        'REP-TECHNICAL-OTHER-EMPLOYEE-001'
    );

    asignarParaTrabajoTecnico(
        $orden,
        $empleadoAsignado,
        $supervisor
    );

    expect(
        fn () => app(
            ActualizarTrabajoTecnico::class
        )->ejecutar(
            $orden,
            $otroEmpleado,
            [
                'diagnostico' => 'Cambio no autorizado.',
                'costo_estimado' => 300,
                'comentario' => 'Intento no autorizado.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'La reparación ya no está asignada a este empleado.'
    );

    $orden->refresh();

    expect($orden->diagnostico)
        ->toBeNull()
        ->and($orden->costo_estimado)
        ->toBeNull()
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('inactive assignment cannot update technical work', function () {
    $cliente = usuarioParaTrabajoTecnico('cliente');
    $supervisor = usuarioParaTrabajoTecnico('supervisor');
    $empleado = usuarioParaTrabajoTecnico('empleado');

    $orden = ordenParaTrabajoTecnico(
        $cliente,
        'REP-TECHNICAL-INACTIVE-001'
    );

    asignarParaTrabajoTecnico(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    expect(
        fn () => app(
            ActualizarTrabajoTecnico::class
        )->ejecutar(
            $orden,
            $empleado,
            [
                'diagnostico' => 'Cambio no autorizado.',
                'costo_estimado' => 400,
                'comentario' => null,
            ]
        )
    )->toThrow(AuthorizationException::class);

    expect($orden->historial()->count())
        ->toBe(0);
});

test('finalized order cannot receive technical updates', function (
    string $estado
) {
    $cliente = usuarioParaTrabajoTecnico('cliente');
    $supervisor = usuarioParaTrabajoTecnico('supervisor');
    $empleado = usuarioParaTrabajoTecnico('empleado');

    $orden = ordenParaTrabajoTecnico(
        $cliente,
        'REP-TECHNICAL-FINALIZED-'.md5($estado),
        $estado
    );

    asignarParaTrabajoTecnico(
        $orden,
        $empleado,
        $supervisor
    );

    expect(
        fn () => app(
            ActualizarTrabajoTecnico::class
        )->ejecutar(
            $orden,
            $empleado,
            [
                'diagnostico' => 'Cambio no permitido.',
                'costo_estimado' => 600,
                'comentario' => null,
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No se puede modificar una reparación finalizada.'
    );

    $orden->refresh();

    expect($orden->diagnostico)
        ->toBeNull()
        ->and($orden->historial()->count())
        ->toBe(0);
})->with([
    EstadoOrden::ENTREGADO->value,
    EstadoOrden::CANCELADO->value,
]);
