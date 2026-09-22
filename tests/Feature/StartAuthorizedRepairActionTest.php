<?php

use App\Actions\Ordenes\IniciarReparacionAutorizada;
use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\EstadoReparacionActualizado;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

function usuarioParaIniciarReparacion(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenAutorizadaParaIniciarReparacion(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Inicio Reparación',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar el inicio de reparación.',
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
        'estado' => EstadoOrden::ESPERANDO_REFACCION->value,
        'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => now(),
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignacionParaIniciarReparacion(
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
        'finalizado_at' => $activa
            ? null
            : now(),
        'activo' => $activa,
        'observaciones' => 'Continuar después de la autorización.',
    ]);
}

test('assigned employee can start an authorized repair', function () {
    Notification::fake();

    $cliente = usuarioParaIniciarReparacion(
        'cliente'
    );

    $supervisor = usuarioParaIniciarReparacion(
        'supervisor'
    );

    $empleado = usuarioParaIniciarReparacion(
        'empleado'
    );

    $orden = ordenAutorizadaParaIniciarReparacion(
        $cliente,
        'REP-START-AUTHORIZED-001'
    );

    asignacionParaIniciarReparacion(
        $orden,
        $empleado,
        $supervisor
    );

    $ordenActualizada = app(
        IniciarReparacionAutorizada::class
    )->ejecutar(
        $orden,
        $empleado
    );

    expect($ordenActualizada->estado)
        ->toBe(EstadoOrden::EN_REPARACION->value)
        ->and($ordenActualizada->autorizacion)
        ->toBe(EstadoAutorizacion::AUTORIZADA->value)
        ->and(
            $ordenActualizada
                ->estado_revision_cotizacion
        )
        ->toBe(EstadoRevisionCotizacion::APROBADA);

    $this->assertDatabaseHas(
        'historial_reparaciones',
        [
            'orden_servicio_id' => $orden->id,
            'user_id' => $empleado->id,
            'estado' => EstadoOrden::EN_REPARACION->value,
            'comentarios' => 'El empleado inició la reparación autorizada.',
            'mensaje_cliente' => 'La reparación autorizada de tu equipo ha comenzado.',
        ]
    );

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class
    );

    Notification::assertCount(1);
});

test('employee without active assignment cannot start repair', function () {
    Notification::fake();

    $cliente = usuarioParaIniciarReparacion(
        'cliente'
    );

    $supervisor = usuarioParaIniciarReparacion(
        'supervisor'
    );

    $empleado = usuarioParaIniciarReparacion(
        'empleado'
    );

    $orden = ordenAutorizadaParaIniciarReparacion(
        $cliente,
        'REP-START-NO-ASSIGNMENT-001'
    );

    asignacionParaIniciarReparacion(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    expect(
        fn () => app(
            IniciarReparacionAutorizada::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(AuthorizationException::class);

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::ESPERANDO_REFACCION->value
        );
});

test('repair cannot start without customer authorization', function () {
    Notification::fake();

    $cliente = usuarioParaIniciarReparacion(
        'cliente'
    );

    $supervisor = usuarioParaIniciarReparacion(
        'supervisor'
    );

    $empleado = usuarioParaIniciarReparacion(
        'empleado'
    );

    $orden = ordenAutorizadaParaIniciarReparacion(
        $cliente,
        'REP-START-NOT-AUTHORIZED-001'
    );

    $orden->update([
        'autorizacion' => EstadoAutorizacion::PENDIENTE->value,
    ]);

    asignacionParaIniciarReparacion(
        $orden,
        $empleado,
        $supervisor
    );

    expect(
        fn () => app(
            IniciarReparacionAutorizada::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(
        RuntimeException::class,
        'El cliente no ha autorizado el presupuesto.'
    );

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::ESPERANDO_REFACCION->value
        );
});

test('repair cannot start without internal quote approval', function () {
    Notification::fake();

    $cliente = usuarioParaIniciarReparacion(
        'cliente'
    );

    $supervisor = usuarioParaIniciarReparacion(
        'supervisor'
    );

    $empleado = usuarioParaIniciarReparacion(
        'empleado'
    );

    $orden = ordenAutorizadaParaIniciarReparacion(
        $cliente,
        'REP-START-NO-INTERNAL-APPROVAL-001'
    );

    $orden->update([
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::PENDIENTE,
    ]);

    asignacionParaIniciarReparacion(
        $orden,
        $empleado,
        $supervisor
    );

    expect(
        fn () => app(
            IniciarReparacionAutorizada::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(
        RuntimeException::class,
        'La cotización no cuenta con aprobación interna.'
    );

    Notification::assertNothingSent();
});

test('repair cannot be started twice', function () {
    Notification::fake();

    $cliente = usuarioParaIniciarReparacion(
        'cliente'
    );

    $supervisor = usuarioParaIniciarReparacion(
        'supervisor'
    );

    $empleado = usuarioParaIniciarReparacion(
        'empleado'
    );

    $orden = ordenAutorizadaParaIniciarReparacion(
        $cliente,
        'REP-START-TWICE-001'
    );

    asignacionParaIniciarReparacion(
        $orden,
        $empleado,
        $supervisor
    );

    $accion = app(
        IniciarReparacionAutorizada::class
    );

    $accion->ejecutar(
        $orden,
        $empleado
    );

    expect(
        fn () => $accion->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(
        RuntimeException::class,
        'La reparación no está lista para iniciar.'
    );

    Notification::assertCount(1);

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::EN_REPARACION->value);
});
