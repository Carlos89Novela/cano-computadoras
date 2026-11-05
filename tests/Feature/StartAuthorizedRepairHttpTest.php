<?php

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\EstadoReparacionActualizado;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

function usuarioParaInicioReparacionHttp(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaInicioReparacionHttp(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Inicio HTTP',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar el inicio HTTP.',
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

function asignacionParaInicioReparacionHttp(
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

test('assigned employee can start authorized repair through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaInicioReparacionHttp(
        'cliente'
    );

    $supervisor = usuarioParaInicioReparacionHttp(
        'supervisor'
    );

    $empleado = usuarioParaInicioReparacionHttp(
        'empleado'
    );

    $orden = ordenParaInicioReparacionHttp(
        $cliente,
        'REP-START-HTTP-001'
    );

    asignacionParaInicioReparacionHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->post(
            route('empleado.ordenes.iniciar', [
                'orden' => $orden->id,
            ])
        );

    $response
        ->assertRedirect(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertSessionHas(
            'success',
            'La reparación fue iniciada correctamente.'
        );

    $orden->refresh();

    expect($orden->estado)
        ->toBe(EstadoOrden::EN_REPARACION->value)
        ->and($orden->autorizacion)
        ->toBe(EstadoAutorizacion::AUTORIZADA->value)
        ->and($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::APROBADA);

    $this->assertDatabaseHas(
        'historial_reparaciones',
        [
            'orden_servicio_id' => $orden->id,
            'user_id' => $empleado->id,
            'estado' => EstadoOrden::EN_REPARACION->value,
            'comentarios' => 'El empleado inició la reparación autorizada.',
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

    $cliente = usuarioParaInicioReparacionHttp(
        'cliente'
    );

    $supervisor = usuarioParaInicioReparacionHttp(
        'supervisor'
    );

    $empleado = usuarioParaInicioReparacionHttp(
        'empleado'
    );

    $orden = ordenParaInicioReparacionHttp(
        $cliente,
        'REP-START-HTTP-NO-ASSIGNMENT-001'
    );

    asignacionParaInicioReparacionHttp(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    $this
        ->actingAs($empleado)
        ->post(
            route('empleado.ordenes.iniciar', [
                'orden' => $orden->id,
            ])
        )
        ->assertForbidden();

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::ESPERANDO_REFACCION->value
        );
});

test('employee cannot start repair before customer authorization', function () {
    Notification::fake();

    $cliente = usuarioParaInicioReparacionHttp(
        'cliente'
    );

    $supervisor = usuarioParaInicioReparacionHttp(
        'supervisor'
    );

    $empleado = usuarioParaInicioReparacionHttp(
        'empleado'
    );

    $orden = ordenParaInicioReparacionHttp(
        $cliente,
        'REP-START-HTTP-NOT-AUTHORIZED-001'
    );

    $orden->update([
        'autorizacion' => EstadoAutorizacion::PENDIENTE->value,
    ]);

    asignacionParaInicioReparacionHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $this
        ->actingAs($empleado)
        ->post(
            route('empleado.ordenes.iniciar', [
                'orden' => $orden->id,
            ])
        )
        ->assertForbidden();

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::ESPERANDO_REFACCION->value
        );
});

test('repair cannot be started twice through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaInicioReparacionHttp(
        'cliente'
    );

    $supervisor = usuarioParaInicioReparacionHttp(
        'supervisor'
    );

    $empleado = usuarioParaInicioReparacionHttp(
        'empleado'
    );

    $orden = ordenParaInicioReparacionHttp(
        $cliente,
        'REP-START-HTTP-TWICE-001'
    );

    asignacionParaInicioReparacionHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $ruta = route('empleado.ordenes.iniciar', [
        'orden' => $orden->id,
    ]);

    $this
        ->actingAs($empleado)
        ->post($ruta)
        ->assertRedirect();

    $this
        ->actingAs($empleado)
        ->post($ruta)
        ->assertForbidden();

    Notification::assertCount(1);

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::EN_REPARACION->value);
});

test('client and supervisor cannot use employee start endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaInicioReparacionHttp(
        'cliente'
    );

    $supervisor = usuarioParaInicioReparacionHttp(
        'supervisor'
    );

    $empleado = usuarioParaInicioReparacionHttp(
        'empleado'
    );

    $orden = ordenParaInicioReparacionHttp(
        $cliente,
        'REP-START-HTTP-ROLES-DENIED-001'
    );

    asignacionParaInicioReparacionHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $ruta = route('empleado.ordenes.iniciar', [
        'orden' => $orden->id,
    ]);

    $this
        ->actingAs($cliente)
        ->post($ruta)
        ->assertForbidden();

    $this
        ->actingAs($supervisor)
        ->post($ruta)
        ->assertForbidden();

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::ESPERANDO_REFACCION->value
        );
});
