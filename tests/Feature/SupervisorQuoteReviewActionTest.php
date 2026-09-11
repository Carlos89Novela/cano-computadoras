<?php

use App\Actions\Ordenes\AprobarRevisionCotizacion;
use App\Actions\Ordenes\RechazarRevisionCotizacion;
use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function crearUsuarioParaRevisionSupervisor(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function crearOrdenPendienteParaRevisionSupervisor(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Revision Supervisor',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar revision del supervisor.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'diagnostico' => 'Se detecto una falla en la fuente.',
        'costo_estimado' => 850,
        'costo_final' => null,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'autorizacion' => EstadoAutorizacion::PENDIENTE->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::PENDIENTE,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

test('supervisor can approve a pending quote review', function () {
    $cliente = crearUsuarioParaRevisionSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionSupervisor(
        $cliente,
        'REP-SUPERVISOR-APPROVE-001'
    );

    $ordenActualizada = app(
        AprobarRevisionCotizacion::class
    )->ejecutar(
        $orden,
        $supervisor
    );

    expect($ordenActualizada->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::APROBADA)
        ->and($ordenActualizada->cotizacion_revisada_por_id)
        ->toBe($supervisor->id)
        ->and($ordenActualizada->cotizacion_revisada_at)
        ->not->toBeNull()
        ->and($ordenActualizada->observacion_revision_cotizacion)
        ->toBeNull()
        ->and($ordenActualizada->estado)
        ->toBe(EstadoOrden::EN_DIAGNOSTICO->value)
        ->and($ordenActualizada->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value)
        ->and((float) $ordenActualizada->costo_estimado)
        ->toBe(850.0);

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $supervisor->id,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'comentarios' => 'Cotizacion aprobada por supervision.',
        'mensaje_cliente' => null,
    ]);
});

test('administrator can approve a pending quote review', function () {
    $cliente = crearUsuarioParaRevisionSupervisor(
        'cliente'
    );

    $administrador = crearUsuarioParaRevisionSupervisor(
        'administrador'
    );

    $orden = crearOrdenPendienteParaRevisionSupervisor(
        $cliente,
        'REP-ADMIN-APPROVE-001'
    );

    $ordenActualizada = app(
        AprobarRevisionCotizacion::class
    )->ejecutar(
        $orden,
        $administrador
    );

    expect($ordenActualizada->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::APROBADA)
        ->and($ordenActualizada->cotizacion_revisada_por_id)
        ->toBe($administrador->id);
});

test('supervisor can reject a pending quote with an observation', function () {
    $cliente = crearUsuarioParaRevisionSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionSupervisor(
        $cliente,
        'REP-SUPERVISOR-REJECT-001'
    );

    $ordenActualizada = app(
        RechazarRevisionCotizacion::class
    )->ejecutar(
        $orden,
        $supervisor,
        'Corregir el costo de la refaccion.'
    );

    expect($ordenActualizada->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::RECHAZADA)
        ->and($ordenActualizada->cotizacion_revisada_por_id)
        ->toBe($supervisor->id)
        ->and($ordenActualizada->cotizacion_revisada_at)
        ->not->toBeNull()
        ->and($ordenActualizada->observacion_revision_cotizacion)
        ->toBe('Corregir el costo de la refaccion.')
        ->and($ordenActualizada->estado)
        ->toBe(EstadoOrden::EN_DIAGNOSTICO->value)
        ->and($ordenActualizada->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value);

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $supervisor->id,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'comentarios' => 'Cotizacion devuelta para correccion: '
            .'Corregir el costo de la refaccion.',
        'mensaje_cliente' => null,
    ]);
});

test('administrator can reject a pending quote review', function () {
    $cliente = crearUsuarioParaRevisionSupervisor(
        'cliente'
    );

    $administrador = crearUsuarioParaRevisionSupervisor(
        'administrador'
    );

    $orden = crearOrdenPendienteParaRevisionSupervisor(
        $cliente,
        'REP-ADMIN-REJECT-001'
    );

    $ordenActualizada = app(
        RechazarRevisionCotizacion::class
    )->ejecutar(
        $orden,
        $administrador,
        'Revisar el importe de mano de obra.'
    );

    expect($ordenActualizada->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::RECHAZADA)
        ->and($ordenActualizada->cotizacion_revisada_por_id)
        ->toBe($administrador->id);
});

test('rejection requires an observation', function () {
    $cliente = crearUsuarioParaRevisionSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionSupervisor(
        $cliente,
        'REP-REJECT-NO-OBSERVATION-001'
    );

    expect(
        fn () => app(
            RechazarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $supervisor,
            '   '
        )
    )->toThrow(
        InvalidArgumentException::class,
        'Debes indicar el motivo del rechazo.'
    );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::PENDIENTE)
        ->and($orden->cotizacion_revisada_por_id)
        ->toBeNull()
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('rejection observation cannot exceed the allowed length', function () {
    $cliente = crearUsuarioParaRevisionSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionSupervisor(
        $cliente,
        'REP-REJECT-LONG-OBSERVATION-001'
    );

    expect(
        fn () => app(
            RechazarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $supervisor,
            str_repeat('a', 2001)
        )
    )->toThrow(
        InvalidArgumentException::class,
        'La observacion no puede superar los 2000 caracteres.'
    );

    expect($orden->historial()->count())
        ->toBe(0);
});

test('employee cannot approve or reject a quote review', function () {
    $cliente = crearUsuarioParaRevisionSupervisor(
        'cliente'
    );

    $empleado = crearUsuarioParaRevisionSupervisor(
        'empleado'
    );

    $orden = crearOrdenPendienteParaRevisionSupervisor(
        $cliente,
        'REP-EMPLOYEE-REVIEW-DENIED-001'
    );

    expect(
        fn () => app(
            AprobarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(AuthorizationException::class);

    expect(
        fn () => app(
            RechazarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $empleado,
            'Intento no autorizado.'
        )
    )->toThrow(AuthorizationException::class);

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::PENDIENTE)
        ->and($orden->historial()->count())
        ->toBe(0);
});
