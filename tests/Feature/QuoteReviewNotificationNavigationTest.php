<?php

use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\CotizacionAprobadaInternamente;
use App\Notifications\CotizacionPendienteRevision;
use App\Notifications\CotizacionRechazadaInternamente;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function crearUsuarioParaNavegacionNotificacion(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function crearOrdenParaNavegacionNotificacion(
    User $cliente,
    string $folio,
    EstadoRevisionCotizacion $estadoRevision
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Notification Navigation',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar navegación de notificaciones.',
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
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'estado_revision_cotizacion' => $estadoRevision,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignarOrdenParaNavegacionNotificacion(
    OrdenServicio $orden,
    User $empleado,
    User $supervisor
): OrdenAsignacion {
    return OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now(),
        'finalizado_at' => null,
        'activo' => true,
        'observaciones' => 'Preparar diagnóstico y cotización.',
    ]);
}

test('supervisor notification opens a pending quote review', function () {
    $cliente = crearUsuarioParaNavegacionNotificacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaNavegacionNotificacion(
        'supervisor'
    );

    $orden = crearOrdenParaNavegacionNotificacion(
        $cliente,
        'REP-NOTIFICATION-NAVIGATION-SUPERVISOR-001',
        EstadoRevisionCotizacion::PENDIENTE
    );

    $supervisor->notifyNow(
        new CotizacionPendienteRevision($orden)
    );

    $notificacion = $supervisor
        ->notifications()
        ->firstOrFail();

    expect($notificacion->read_at)
        ->toBeNull();

    $response = $this
        ->actingAs($supervisor)
        ->post(
            route('notificaciones.leer', [
                'notificacion' => $notificacion->id,
            ])
        );

    $response->assertRedirect(
        route(
            'supervisor.cotizaciones.show',
            [
                'orden' => $orden->id,
            ],
            false
        )
    );

    expect(
        $notificacion
            ->fresh()
            ?->read_at
    )->not->toBeNull();
});

test('approved quote notification opens employee order detail', function () {
    $cliente = crearUsuarioParaNavegacionNotificacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaNavegacionNotificacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaNavegacionNotificacion(
        'empleado'
    );

    $orden = crearOrdenParaNavegacionNotificacion(
        $cliente,
        'REP-NOTIFICATION-NAVIGATION-APPROVED-001',
        EstadoRevisionCotizacion::APROBADA
    );

    asignarOrdenParaNavegacionNotificacion(
        $orden,
        $empleado,
        $supervisor
    );

    $empleado->notifyNow(
        new CotizacionAprobadaInternamente($orden)
    );

    $notificacion = $empleado
        ->notifications()
        ->firstOrFail();

    $response = $this
        ->actingAs($empleado)
        ->post(
            route('notificaciones.leer', [
                'notificacion' => $notificacion->id,
            ])
        );

    $response->assertRedirect(
        route(
            'empleado.ordenes.show',
            [
                'orden' => $orden->id,
            ],
            false
        )
    );

    expect(
        $notificacion
            ->fresh()
            ?->read_at
    )->not->toBeNull();
});

test('rejected quote notification opens employee order detail', function () {
    $cliente = crearUsuarioParaNavegacionNotificacion(
        'cliente'
    );

    $supervisor = crearUsuarioParaNavegacionNotificacion(
        'supervisor'
    );

    $empleado = crearUsuarioParaNavegacionNotificacion(
        'empleado'
    );

    $orden = crearOrdenParaNavegacionNotificacion(
        $cliente,
        'REP-NOTIFICATION-NAVIGATION-REJECTED-001',
        EstadoRevisionCotizacion::RECHAZADA
    );

    asignarOrdenParaNavegacionNotificacion(
        $orden,
        $empleado,
        $supervisor
    );

    $empleado->notifyNow(
        new CotizacionRechazadaInternamente(
            $orden,
            'Corregir el costo de la refacción.'
        )
    );

    $notificacion = $empleado
        ->notifications()
        ->firstOrFail();

    $response = $this
        ->actingAs($empleado)
        ->post(
            route('notificaciones.leer', [
                'notificacion' => $notificacion->id,
            ])
        );

    $response->assertRedirect(
        route(
            'empleado.ordenes.show',
            [
                'orden' => $orden->id,
            ],
            false
        )
    );

    expect(
        $notificacion
            ->fresh()
            ?->read_at
    )->not->toBeNull();
});

test('user cannot open another users notification', function () {
    $cliente = crearUsuarioParaNavegacionNotificacion(
        'cliente'
    );

    $supervisorUno = crearUsuarioParaNavegacionNotificacion(
        'supervisor'
    );

    $supervisorDos = crearUsuarioParaNavegacionNotificacion(
        'supervisor'
    );

    $orden = crearOrdenParaNavegacionNotificacion(
        $cliente,
        'REP-NOTIFICATION-NAVIGATION-OWNER-001',
        EstadoRevisionCotizacion::PENDIENTE
    );

    $supervisorUno->notifyNow(
        new CotizacionPendienteRevision($orden)
    );

    $notificacion = $supervisorUno
        ->notifications()
        ->firstOrFail();

    $this
        ->actingAs($supervisorDos)
        ->post(
            route('notificaciones.leer', [
                'notificacion' => $notificacion->id,
            ])
        )
        ->assertNotFound();

    expect(
        $notificacion
            ->fresh()
            ?->read_at
    )->toBeNull();
});

test('external notification url is rejected', function () {
    $supervisor = crearUsuarioParaNavegacionNotificacion(
        'supervisor'
    );

    $notificacion = $supervisor
        ->notifications()
        ->create([
            'id' => (string) str()->uuid(),
            'type' => CotizacionPendienteRevision::class,
            'data' => [
                'mensaje' => 'Notificación con destino externo.',
                'url' => 'https://example.com/phishing',
            ],
        ]);

    $response = $this
        ->actingAs($supervisor)
        ->post(
            route('notificaciones.leer', [
                'notificacion' => $notificacion->id,
            ])
        );

    $response
        ->assertRedirect(
            route('notificaciones.index')
        )
        ->assertSessionHasErrors([
            'notificacion',
        ]);

    expect(
        $notificacion
            ->fresh()
            ?->read_at
    )->not->toBeNull();
});

test('protocol relative notification url is rejected', function () {
    $supervisor = crearUsuarioParaNavegacionNotificacion(
        'supervisor'
    );

    $notificacion = $supervisor
        ->notifications()
        ->create([
            'id' => (string) str()->uuid(),
            'type' => CotizacionPendienteRevision::class,
            'data' => [
                'mensaje' => 'Notificación con destino inválido.',
                'url' => '//example.com/phishing',
            ],
        ]);

    $response = $this
        ->actingAs($supervisor)
        ->post(
            route('notificaciones.leer', [
                'notificacion' => $notificacion->id,
            ])
        );

    $response
        ->assertRedirect(
            route('notificaciones.index')
        )
        ->assertSessionHasErrors([
            'notificacion',
        ]);
});
