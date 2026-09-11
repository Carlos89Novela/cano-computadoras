<?php

use App\Actions\Ordenes\SolicitarRevisionCotizacion;
use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
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

function crearUsuarioParaRevisionCotizacion(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function crearOrdenParaRevisionCotizacion(
    User $cliente,
    string $folio,
    string $estado = 'En diagnóstico',
    ?string $diagnostico = 'Falla detectada en la fuente.',
    int|float|null $costoEstimado = 850
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Revision',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar revision de cotizacion.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'diagnostico' => $diagnostico,
        'costo_estimado' => $costoEstimado,
        'costo_final' => null,
        'estado' => $estado,
        'autorizacion' => EstadoAutorizacion::PENDIENTE->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::SIN_SOLICITAR,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function crearAsignacionParaRevisionCotizacion(
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
        'observaciones' => 'Preparar diagnostico y cotizacion.',
    ]);
}

test('assigned employee can request quote review', function () {
    $cliente = crearUsuarioParaRevisionCotizacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionCotizacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $orden = crearOrdenParaRevisionCotizacion(
        $cliente,
        'REP-QUOTE-REVIEW-001',
        EstadoOrden::EN_DIAGNOSTICO->value
    );

    crearAsignacionParaRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    $ordenActualizada = app(
        SolicitarRevisionCotizacion::class
    )->ejecutar(
        $orden,
        $empleado
    );

    expect($ordenActualizada->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::PENDIENTE)
        ->and($ordenActualizada->estado)
        ->toBe(EstadoOrden::EN_DIAGNOSTICO->value)
        ->and($ordenActualizada->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value)
        ->and($ordenActualizada->cotizacion_revisada_por_id)
        ->toBeNull()
        ->and($ordenActualizada->cotizacion_revisada_at)
        ->toBeNull()
        ->and($ordenActualizada->observacion_revision_cotizacion)
        ->toBeNull();

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $empleado->id,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'comentarios' => 'Cotizacion enviada a revision del supervisor.',
        'mensaje_cliente' => null,
    ]);
});

test('quote review requires a diagnosis', function () {
    $cliente = crearUsuarioParaRevisionCotizacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionCotizacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $orden = crearOrdenParaRevisionCotizacion(
        $cliente,
        'REP-QUOTE-NO-DIAGNOSIS-001',
        EstadoOrden::EN_DIAGNOSTICO->value,
        null,
        850
    );

    crearAsignacionParaRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    expect(
        fn () => app(
            SolicitarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(
        RuntimeException::class,
        'Debes registrar el diagnostico antes de solicitar la revision.'
    );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('quote review requires an estimated cost', function () {
    $cliente = crearUsuarioParaRevisionCotizacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionCotizacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $orden = crearOrdenParaRevisionCotizacion(
        $cliente,
        'REP-QUOTE-NO-COST-001',
        EstadoOrden::EN_DIAGNOSTICO->value,
        'Falla detectada en la tarjeta principal.',
        null
    );

    crearAsignacionParaRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    expect(
        fn () => app(
            SolicitarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(
        RuntimeException::class,
        'Debes registrar el costo estimado antes de solicitar la revision.'
    );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('employee cannot request review for another employee assignment', function () {
    $cliente = crearUsuarioParaRevisionCotizacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionCotizacion(
        'supervisor'
    );

    $empleadoAsignado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $otroEmpleado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $orden = crearOrdenParaRevisionCotizacion(
        $cliente,
        'REP-QUOTE-OTHER-EMPLOYEE-001'
    );

    crearAsignacionParaRevisionCotizacion(
        $orden,
        $empleadoAsignado,
        $supervisor
    );

    expect(
        fn () => app(
            SolicitarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $otroEmpleado
        )
    )->toThrow(
        AuthorizationException::class,
        'La reparacion ya no esta asignada a este empleado.'
    );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('inactive assignment cannot request quote review', function () {
    $cliente = crearUsuarioParaRevisionCotizacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionCotizacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $orden = crearOrdenParaRevisionCotizacion(
        $cliente,
        'REP-QUOTE-INACTIVE-001'
    );

    crearAsignacionParaRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    expect(
        fn () => app(
            SolicitarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(AuthorizationException::class);

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('duplicate pending review request is rejected', function () {
    $cliente = crearUsuarioParaRevisionCotizacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionCotizacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $orden = crearOrdenParaRevisionCotizacion(
        $cliente,
        'REP-QUOTE-DUPLICATE-001'
    );

    crearAsignacionParaRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    app(SolicitarRevisionCotizacion::class)->ejecutar(
        $orden,
        $empleado
    );

    expect(
        fn () => app(
            SolicitarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(
        RuntimeException::class,
        'La cotizacion no puede enviarse nuevamente a revision.'
    );

    expect($orden->historial()->count())
        ->toBe(1);
});

test('rejected quote can be submitted again and clears prior review data', function () {
    $cliente = crearUsuarioParaRevisionCotizacion(
        'cliente'
    );

    $supervisorAnterior = crearUsuarioParaRevisionCotizacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $orden = crearOrdenParaRevisionCotizacion(
        $cliente,
        'REP-QUOTE-RESUBMIT-001'
    );

    crearAsignacionParaRevisionCotizacion(
        $orden,
        $empleado,
        $supervisorAnterior
    );

    $orden->update([
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::RECHAZADA,
        'cotizacion_revisada_por_id' => $supervisorAnterior->id,
        'cotizacion_revisada_at' => now()->subHour(),
        'observacion_revision_cotizacion' => 'Corregir el costo estimado.',
    ]);

    $ordenActualizada = app(
        SolicitarRevisionCotizacion::class
    )->ejecutar(
        $orden,
        $empleado
    );

    expect($ordenActualizada->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::PENDIENTE)
        ->and($ordenActualizada->cotizacion_revisada_por_id)
        ->toBeNull()
        ->and($ordenActualizada->cotizacion_revisada_at)
        ->toBeNull()
        ->and($ordenActualizada->observacion_revision_cotizacion)
        ->toBeNull();
});

test('approved quote cannot be submitted again', function () {
    $cliente = crearUsuarioParaRevisionCotizacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionCotizacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $orden = crearOrdenParaRevisionCotizacion(
        $cliente,
        'REP-QUOTE-APPROVED-001'
    );

    crearAsignacionParaRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    $orden->update([
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'cotizacion_revisada_por_id' => $supervisor->id,
        'cotizacion_revisada_at' => now(),
    ]);

    expect(
        fn () => app(
            SolicitarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(
        RuntimeException::class,
        'La cotizacion no puede enviarse nuevamente a revision.'
    );

    expect($orden->historial()->count())
        ->toBe(0);
});

test('finalized order cannot request quote review', function (
    string $estado
) {
    $cliente = crearUsuarioParaRevisionCotizacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionCotizacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaRevisionCotizacion(
        'empleado'
    );

    $orden = crearOrdenParaRevisionCotizacion(
        $cliente,
        'REP-QUOTE-FINALIZED-'.md5($estado),
        $estado
    );

    crearAsignacionParaRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    expect(
        fn () => app(
            SolicitarRevisionCotizacion::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(
        RuntimeException::class,
        'No se puede solicitar la revision de una reparacion finalizada.'
    );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
})->with([
    'orden entregada' => EstadoOrden::ENTREGADO->value,
    'orden cancelada' => EstadoOrden::CANCELADO->value,
]);
