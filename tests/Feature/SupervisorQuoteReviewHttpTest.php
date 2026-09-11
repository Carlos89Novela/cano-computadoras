<?php

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function crearUsuarioParaRevisionHttpSupervisor(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function crearOrdenPendienteParaRevisionHttpSupervisor(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Revisión HTTP',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar la revisión HTTP.',
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
        'autorizacion' => EstadoAutorizacion::PENDIENTE->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::PENDIENTE,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

test('supervisor can approve a pending quote through the endpoint', function () {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionHttpSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-SUPERVISOR-HTTP-APPROVE-001'
    );

    $response = $this
        ->actingAs($supervisor)
        ->from(route('supervisor.dashboard'))
        ->post(
            route('operacion.cotizaciones.aprobar', [
                'orden' => $orden->id,
            ])
        );

    $response
        ->assertRedirect(route('supervisor.dashboard'))
        ->assertSessionHas(
            'success',
            'La cotización fue aprobada correctamente.'
        );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::APROBADA)
        ->and($orden->cotizacion_revisada_por_id)
        ->toBe($supervisor->id)
        ->and($orden->cotizacion_revisada_at)
        ->not->toBeNull()
        ->and($orden->observacion_revision_cotizacion)
        ->toBeNull()
        ->and($orden->estado)
        ->toBe(EstadoOrden::EN_DIAGNOSTICO->value)
        ->and($orden->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value)
        ->and((float) $orden->costo_estimado)
        ->toBe(850.0);

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $supervisor->id,
        'comentarios' => 'Cotizacion aprobada por supervision.',
        'mensaje_cliente' => null,
    ]);
});

test('administrator can approve a pending quote through the endpoint', function () {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $administrador = crearUsuarioParaRevisionHttpSupervisor(
        'administrador'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-ADMIN-HTTP-APPROVE-001'
    );

    $this
        ->actingAs($administrador)
        ->from(route('admin.dashboard'))
        ->post(
            route('operacion.cotizaciones.aprobar', [
                'orden' => $orden->id,
            ])
        )
        ->assertRedirect(route('admin.dashboard'));

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::APROBADA)
        ->and($orden->cotizacion_revisada_por_id)
        ->toBe($administrador->id);
});

test('supervisor can reject a pending quote with an observation', function () {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionHttpSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-SUPERVISOR-HTTP-REJECT-001'
    );

    $response = $this
        ->actingAs($supervisor)
        ->from(route('supervisor.dashboard'))
        ->post(
            route('operacion.cotizaciones.rechazar', [
                'orden' => $orden->id,
            ]),
            [
                'observacion' => 'Corregir el costo de la refacción.',
            ]
        );

    $response
        ->assertRedirect(route('supervisor.dashboard'))
        ->assertSessionHas(
            'success',
            'La cotización fue devuelta al empleado.'
        );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::RECHAZADA)
        ->and($orden->cotizacion_revisada_por_id)
        ->toBe($supervisor->id)
        ->and($orden->cotizacion_revisada_at)
        ->not->toBeNull()
        ->and($orden->observacion_revision_cotizacion)
        ->toBe('Corregir el costo de la refacción.')
        ->and($orden->estado)
        ->toBe(EstadoOrden::EN_DIAGNOSTICO->value)
        ->and($orden->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value);

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $supervisor->id,
        'comentarios' => 'Cotizacion devuelta para correccion: '
            .'Corregir el costo de la refacción.',
        'mensaje_cliente' => null,
    ]);
});

test('administrator can reject a pending quote through the endpoint', function () {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $administrador = crearUsuarioParaRevisionHttpSupervisor(
        'administrador'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-ADMIN-HTTP-REJECT-001'
    );

    $this
        ->actingAs($administrador)
        ->from(route('admin.dashboard'))
        ->post(
            route('operacion.cotizaciones.rechazar', [
                'orden' => $orden->id,
            ]),
            [
                'observacion' => 'Revisar el importe de mano de obra.',
            ]
        )
        ->assertRedirect(route('admin.dashboard'));

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::RECHAZADA)
        ->and($orden->cotizacion_revisada_por_id)
        ->toBe($administrador->id)
        ->and($orden->observacion_revision_cotizacion)
        ->toBe('Revisar el importe de mano de obra.');
});

test('rejecting a quote requires an observation', function () {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionHttpSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-SUPERVISOR-HTTP-NO-OBSERVATION-001'
    );

    $response = $this
        ->actingAs($supervisor)
        ->from(route('supervisor.dashboard'))
        ->post(
            route('operacion.cotizaciones.rechazar', [
                'orden' => $orden->id,
            ]),
            [
                'observacion' => '   ',
            ]
        );

    $response
        ->assertRedirect(route('supervisor.dashboard'))
        ->assertSessionHasErrors([
            'observacion',
        ]);

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::PENDIENTE)
        ->and($orden->cotizacion_revisada_por_id)
        ->toBeNull()
        ->and($orden->cotizacion_revisada_at)
        ->toBeNull()
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('employee and client cannot review pending quotes', function () {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $empleado = crearUsuarioParaRevisionHttpSupervisor(
        'empleado'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-HTTP-REVIEW-ROLES-DENIED-001'
    );

    $rutaAprobar = route(
        'operacion.cotizaciones.aprobar',
        [
            'orden' => $orden->id,
        ]
    );

    $rutaRechazar = route(
        'operacion.cotizaciones.rechazar',
        [
            'orden' => $orden->id,
        ]
    );

    $this
        ->actingAs($empleado)
        ->post($rutaAprobar)
        ->assertForbidden();

    $this
        ->actingAs($empleado)
        ->post($rutaRechazar, [
            'observacion' => 'Intento no autorizado.',
        ])
        ->assertForbidden();

    $this
        ->actingAs($cliente)
        ->post($rutaAprobar)
        ->assertForbidden();

    $this
        ->actingAs($cliente)
        ->post($rutaRechazar, [
            'observacion' => 'Intento no autorizado.',
        ])
        ->assertForbidden();

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::PENDIENTE)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('quote that is no longer pending cannot be approved or rejected', function (
    EstadoRevisionCotizacion $estadoRevision
) {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionHttpSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-HTTP-NOT-PENDING-'.$estadoRevision->value
    );

    $orden->update([
        'estado_revision_cotizacion' => $estadoRevision,
    ]);

    $this
        ->actingAs($supervisor)
        ->post(
            route('operacion.cotizaciones.aprobar', [
                'orden' => $orden->id,
            ])
        )
        ->assertForbidden();

    $this
        ->actingAs($supervisor)
        ->post(
            route('operacion.cotizaciones.rechazar', [
                'orden' => $orden->id,
            ]),
            [
                'observacion' => 'No debe procesarse.',
            ]
        )
        ->assertForbidden();

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe($estadoRevision)
        ->and($orden->historial()->count())
        ->toBe(0);
})->with([
    'sin solicitar' => EstadoRevisionCotizacion::SIN_SOLICITAR,
    'aprobada' => EstadoRevisionCotizacion::APROBADA,
    'rechazada' => EstadoRevisionCotizacion::RECHAZADA,
]);

test('supervisor can open a pending quote review detail', function () {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionHttpSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-SUPERVISOR-DETAIL-001'
    );

    $this
        ->actingAs($supervisor)
        ->get(
            route('supervisor.cotizaciones.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertOk()
        ->assertSee('REP-SUPERVISOR-DETAIL-001')
        ->assertSee('Revisión de cotización')
        ->assertSee('Aprobar cotización')
        ->assertSee('Rechazar cotización');
});

test('employee and client cannot open supervisor quote review detail', function () {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $empleado = crearUsuarioParaRevisionHttpSupervisor(
        'empleado'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-SUPERVISOR-DETAIL-DENIED-001'
    );

    $ruta = route('supervisor.cotizaciones.show', [
        'orden' => $orden->id,
    ]);

    $this
        ->actingAs($empleado)
        ->get($ruta)
        ->assertForbidden();

    $this
        ->actingAs($cliente)
        ->get($ruta)
        ->assertForbidden();
});

test('processed quote review can no longer be opened as pending', function (
    EstadoRevisionCotizacion $estadoRevision
) {
    $cliente = crearUsuarioParaRevisionHttpSupervisor(
        'cliente'
    );

    $supervisor = crearUsuarioParaRevisionHttpSupervisor(
        'supervisor'
    );

    $orden = crearOrdenPendienteParaRevisionHttpSupervisor(
        $cliente,
        'REP-SUPERVISOR-PROCESSED-'
        .$estadoRevision->value
    );

    $orden->update([
        'estado_revision_cotizacion' => $estadoRevision,
    ]);

    $this
        ->actingAs($supervisor)
        ->get(
            route('supervisor.cotizaciones.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertForbidden();
})->with([
    'cotización aprobada' => EstadoRevisionCotizacion::APROBADA,
    'cotización rechazada' => EstadoRevisionCotizacion::RECHAZADA,
]);
