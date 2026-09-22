<?php

use App\Actions\Ordenes\MarcarReparacionListaParaEntrega;
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

function usuarioParaEntregaTecnica(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaEntregaTecnica(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Entrega',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar el cierre técnico.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'diagnostico' => 'Se reemplazó la fuente dañada.',
        'costo_estimado' => 850,
        'costo_final' => null,
        'estado' => EstadoOrden::EN_PRUEBAS->value,
        'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => now(),
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignacionParaEntregaTecnica(
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
        'observaciones' => 'Completar pruebas de funcionamiento.',
    ]);
}

test('assigned employee can mark tested repair ready for delivery', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaTecnica(
        'cliente'
    );

    $supervisor = usuarioParaEntregaTecnica(
        'supervisor'
    );

    $empleado = usuarioParaEntregaTecnica(
        'empleado'
    );

    $orden = ordenParaEntregaTecnica(
        $cliente,
        'REP-READY-DELIVERY-001'
    );

    $asignacion = asignacionParaEntregaTecnica(
        $orden,
        $empleado,
        $supervisor
    );

    $ordenActualizada = app(
        MarcarReparacionListaParaEntrega::class
    )->ejecutar(
        $orden,
        $empleado,
        875.50,
        'Las pruebas finalizaron correctamente.'
    );

    expect($ordenActualizada->estado)
        ->toBe(
            EstadoOrden::LISTO_PARA_ENTREGA->value
        )
        ->and((float) $ordenActualizada->costo_final)
        ->toBe(875.50)
        ->and($ordenActualizada->autorizacion)
        ->toBe(
            EstadoAutorizacion::AUTORIZADA->value
        )
        ->and(
            $ordenActualizada
                ->estado_revision_cotizacion
        )
        ->toBe(EstadoRevisionCotizacion::APROBADA);

    $asignacion->refresh();

    expect((bool) $asignacion->activo)
        ->toBeFalse()
        ->and($asignacion->finalizado_at)
        ->not->toBeNull();

    $this->assertDatabaseHas(
        'historial_reparaciones',
        [
            'orden_servicio_id' => $orden->id,
            'user_id' => $empleado->id,
            'estado' => EstadoOrden::LISTO_PARA_ENTREGA->value,
            'comentarios' => 'Las pruebas finalizaron correctamente.',
            'mensaje_cliente' => 'Tu equipo está listo para entrega.',
        ]
    );

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class
    );

    Notification::assertCount(1);
});

test('default delivery comment is saved when comment is omitted', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaTecnica(
        'cliente'
    );

    $supervisor = usuarioParaEntregaTecnica(
        'supervisor'
    );

    $empleado = usuarioParaEntregaTecnica(
        'empleado'
    );

    $orden = ordenParaEntregaTecnica(
        $cliente,
        'REP-READY-DELIVERY-NO-COMMENT-001'
    );

    asignacionParaEntregaTecnica(
        $orden,
        $empleado,
        $supervisor
    );

    app(
        MarcarReparacionListaParaEntrega::class
    )->ejecutar(
        $orden,
        $empleado,
        850
    );

    $this->assertDatabaseHas(
        'historial_reparaciones',
        [
            'orden_servicio_id' => $orden->id,
            'comentarios' => 'Las pruebas finalizaron correctamente.',
        ]
    );
});

test('employee without active assignment cannot complete testing', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaTecnica(
        'cliente'
    );

    $supervisor = usuarioParaEntregaTecnica(
        'supervisor'
    );

    $empleado = usuarioParaEntregaTecnica(
        'empleado'
    );

    $orden = ordenParaEntregaTecnica(
        $cliente,
        'REP-READY-DELIVERY-NO-ASSIGNMENT-001'
    );

    asignacionParaEntregaTecnica(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    expect(
        fn () => app(
            MarcarReparacionListaParaEntrega::class
        )->ejecutar(
            $orden,
            $empleado,
            850
        )
    )->toThrow(
        AuthorizationException::class
    );

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::EN_PRUEBAS->value);
});

test('repair cannot be marked ready from invalid state', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaTecnica(
        'cliente'
    );

    $supervisor = usuarioParaEntregaTecnica(
        'supervisor'
    );

    $empleado = usuarioParaEntregaTecnica(
        'empleado'
    );

    $orden = ordenParaEntregaTecnica(
        $cliente,
        'REP-READY-DELIVERY-INVALID-STATE-001'
    );

    $orden->update([
        'estado' => EstadoOrden::EN_REPARACION->value,
    ]);

    asignacionParaEntregaTecnica(
        $orden,
        $empleado,
        $supervisor
    );

    expect(
        fn () => app(
            MarcarReparacionListaParaEntrega::class
        )->ejecutar(
            $orden,
            $empleado,
            850
        )
    )->toThrow(
        RuntimeException::class,
        'Solo una reparación en pruebas puede marcarse como lista.'
    );

    Notification::assertNothingSent();
});

test('repair cannot be marked ready twice', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaTecnica(
        'cliente'
    );

    $supervisor = usuarioParaEntregaTecnica(
        'supervisor'
    );

    $empleado = usuarioParaEntregaTecnica(
        'empleado'
    );

    $orden = ordenParaEntregaTecnica(
        $cliente,
        'REP-READY-DELIVERY-TWICE-001'
    );

    asignacionParaEntregaTecnica(
        $orden,
        $empleado,
        $supervisor
    );

    $accion = app(
        MarcarReparacionListaParaEntrega::class
    );

    $accion->ejecutar(
        $orden,
        $empleado,
        850
    );

    expect(
        fn () => $accion->ejecutar(
            $orden,
            $empleado,
            900
        )
    )->toThrow(
        AuthorizationException::class
    );

    Notification::assertCount(1);

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::LISTO_PARA_ENTREGA->value
        );
});

test('invalid final cost does not complete repair', function (
    float $costoFinal
) {
    Notification::fake();

    $cliente = usuarioParaEntregaTecnica(
        'cliente'
    );

    $supervisor = usuarioParaEntregaTecnica(
        'supervisor'
    );

    $empleado = usuarioParaEntregaTecnica(
        'empleado'
    );

    $orden = ordenParaEntregaTecnica(
        $cliente,
        'REP-READY-DELIVERY-INVALID-COST-'
        .str_replace('.', '-', (string) abs($costoFinal))
    );

    asignacionParaEntregaTecnica(
        $orden,
        $empleado,
        $supervisor
    );

    expect(
        fn () => app(
            MarcarReparacionListaParaEntrega::class
        )->ejecutar(
            $orden,
            $empleado,
            $costoFinal
        )
    )->toThrow(
        RuntimeException::class,
        'El costo final no es válido.'
    );

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::EN_PRUEBAS->value)
        ->and($orden->fresh()?->costo_final)
        ->toBeNull();
})->with([
    'costo negativo' => -1,
    'costo superior al máximo' => 100000000,
]);
