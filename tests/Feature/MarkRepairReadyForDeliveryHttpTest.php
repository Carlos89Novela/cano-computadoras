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

function usuarioParaEntregaHttp(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaEntregaHttp(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Entrega HTTP',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar el cierre técnico HTTP.',
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

function asignacionParaEntregaHttp(
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

test('assigned employee can mark repair ready through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttp('cliente');
    $supervisor = usuarioParaEntregaHttp('supervisor');
    $empleado = usuarioParaEntregaHttp('empleado');

    $orden = ordenParaEntregaHttp(
        $cliente,
        'REP-READY-HTTP-001'
    );

    $asignacion = asignacionParaEntregaHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->post(
            route(
                'empleado.ordenes.lista-para-entrega',
                ['orden' => $orden->id]
            ),
            [
                'costo_final' => 875.50,
                'comentario' => 'El equipo aprobó todas las pruebas.',
            ]
        );

    $response
        ->assertRedirect(
            route('empleado.dashboard')
        )
        ->assertSessionHas(
            'success',
            'La reparación quedó lista para entrega.'
        );

    $orden->refresh();
    $asignacion->refresh();

    expect($orden->estado)
        ->toBe(
            EstadoOrden::LISTO_PARA_ENTREGA->value
        )
        ->and((float) $orden->costo_final)
        ->toBe(875.50)
        ->and((bool) $asignacion->activo)
        ->toBeFalse()
        ->and($asignacion->finalizado_at)
        ->not->toBeNull();

    $this->assertDatabaseHas(
        'historial_reparaciones',
        [
            'orden_servicio_id' => $orden->id,
            'user_id' => $empleado->id,
            'estado' => EstadoOrden::LISTO_PARA_ENTREGA->value,
            'comentarios' => 'El equipo aprobó todas las pruebas.',
            'mensaje_cliente' => 'Tu equipo está listo para entrega.',
        ]
    );

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class
    );

    Notification::assertCount(1);
});

test('final cost is required through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttp('cliente');
    $supervisor = usuarioParaEntregaHttp('supervisor');
    $empleado = usuarioParaEntregaHttp('empleado');

    $orden = ordenParaEntregaHttp(
        $cliente,
        'REP-READY-HTTP-NO-COST-001'
    );

    asignacionParaEntregaHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $detalle = route(
        'empleado.ordenes.show',
        ['orden' => $orden->id]
    );

    $this
        ->actingAs($empleado)
        ->from($detalle)
        ->post(
            route(
                'empleado.ordenes.lista-para-entrega',
                ['orden' => $orden->id]
            ),
            [
                'comentario' => 'Pruebas terminadas.',
            ]
        )
        ->assertRedirect($detalle)
        ->assertSessionHasErrors([
            'costo_final',
        ]);

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::EN_PRUEBAS->value)
        ->and($orden->fresh()?->costo_final)
        ->toBeNull();
});

test('employee without active assignment cannot mark repair ready', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttp('cliente');
    $supervisor = usuarioParaEntregaHttp('supervisor');
    $empleado = usuarioParaEntregaHttp('empleado');

    $orden = ordenParaEntregaHttp(
        $cliente,
        'REP-READY-HTTP-NO-ASSIGNMENT-001'
    );

    asignacionParaEntregaHttp(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    $this
        ->actingAs($empleado)
        ->post(
            route(
                'empleado.ordenes.lista-para-entrega',
                ['orden' => $orden->id]
            ),
            [
                'costo_final' => 850,
            ]
        )
        ->assertForbidden();

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::EN_PRUEBAS->value);
});

test('repair cannot be marked ready twice through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttp('cliente');
    $supervisor = usuarioParaEntregaHttp('supervisor');
    $empleado = usuarioParaEntregaHttp('empleado');

    $orden = ordenParaEntregaHttp(
        $cliente,
        'REP-READY-HTTP-TWICE-001'
    );

    asignacionParaEntregaHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $ruta = route(
        'empleado.ordenes.lista-para-entrega',
        ['orden' => $orden->id]
    );

    $this
        ->actingAs($empleado)
        ->post(
            $ruta,
            ['costo_final' => 850]
        )
        ->assertRedirect();

    $this
        ->actingAs($empleado)
        ->post(
            $ruta,
            ['costo_final' => 900]
        )
        ->assertForbidden();

    Notification::assertCount(1);

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::LISTO_PARA_ENTREGA->value
        )
        ->and((float) $orden->fresh()?->costo_final)
        ->toBe(850.0);
});

test('client and supervisor cannot use ready endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttp('cliente');
    $supervisor = usuarioParaEntregaHttp('supervisor');
    $empleado = usuarioParaEntregaHttp('empleado');

    $orden = ordenParaEntregaHttp(
        $cliente,
        'REP-READY-HTTP-ROLES-001'
    );

    asignacionParaEntregaHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $ruta = route(
        'empleado.ordenes.lista-para-entrega',
        ['orden' => $orden->id]
    );

    $datos = [
        'costo_final' => 850,
    ];

    $this
        ->actingAs($cliente)
        ->post($ruta, $datos)
        ->assertForbidden();

    $this
        ->actingAs($supervisor)
        ->post($ruta, $datos)
        ->assertForbidden();

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::EN_PRUEBAS->value);
});
