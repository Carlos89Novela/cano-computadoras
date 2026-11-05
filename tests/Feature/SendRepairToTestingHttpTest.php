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

function usuarioParaPruebasHttp(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaPruebasHttp(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Testing HTTP',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar el envío HTTP a pruebas.',
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
        'estado' => EstadoOrden::EN_REPARACION->value,
        'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => now(),
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignacionParaPruebasHttp(
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
        'observaciones' => 'Realizar reparación y pruebas.',
    ]);
}

test('assigned employee can send repair to testing through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaPruebasHttp(
        'cliente'
    );

    $supervisor = usuarioParaPruebasHttp(
        'supervisor'
    );

    $empleado = usuarioParaPruebasHttp(
        'empleado'
    );

    $orden = ordenParaPruebasHttp(
        $cliente,
        'REP-TESTING-HTTP-001'
    );

    asignacionParaPruebasHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->post(
            route('empleado.ordenes.pruebas', [
                'orden' => $orden->id,
            ]),
            [
                'comentario' => 'La reparación quedó lista para pruebas.',
            ]
        );

    $response
        ->assertRedirect(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertSessionHas(
            'success',
            'La reparación fue enviada a pruebas correctamente.'
        );

    $orden->refresh();

    expect($orden->estado)
        ->toBe(EstadoOrden::EN_PRUEBAS->value)
        ->and($orden->autorizacion)
        ->toBe(EstadoAutorizacion::AUTORIZADA->value)
        ->and($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::APROBADA);

    $this->assertDatabaseHas(
        'historial_reparaciones',
        [
            'orden_servicio_id' => $orden->id,
            'user_id' => $empleado->id,
            'estado' => EstadoOrden::EN_PRUEBAS->value,
            'comentarios' => 'La reparación quedó lista para pruebas.',
            'mensaje_cliente' => 'La reparación de tu equipo se encuentra en pruebas.',
        ]
    );

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class
    );

    Notification::assertCount(1);
});

test('testing comment is optional through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaPruebasHttp(
        'cliente'
    );

    $supervisor = usuarioParaPruebasHttp(
        'supervisor'
    );

    $empleado = usuarioParaPruebasHttp(
        'empleado'
    );

    $orden = ordenParaPruebasHttp(
        $cliente,
        'REP-TESTING-HTTP-NO-COMMENT-001'
    );

    asignacionParaPruebasHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $this
        ->actingAs($empleado)
        ->post(
            route('empleado.ordenes.pruebas', [
                'orden' => $orden->id,
            ])
        )
        ->assertRedirect();

    $this->assertDatabaseHas(
        'historial_reparaciones',
        [
            'orden_servicio_id' => $orden->id,
            'estado' => EstadoOrden::EN_PRUEBAS->value,
            'comentarios' => 'La reparación fue enviada a pruebas.',
        ]
    );
});

test('employee without active assignment cannot send repair to testing', function () {
    Notification::fake();

    $cliente = usuarioParaPruebasHttp(
        'cliente'
    );

    $supervisor = usuarioParaPruebasHttp(
        'supervisor'
    );

    $empleado = usuarioParaPruebasHttp(
        'empleado'
    );

    $orden = ordenParaPruebasHttp(
        $cliente,
        'REP-TESTING-HTTP-NO-ASSIGNMENT-001'
    );

    asignacionParaPruebasHttp(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    $this
        ->actingAs($empleado)
        ->post(
            route('empleado.ordenes.pruebas', [
                'orden' => $orden->id,
            ])
        )
        ->assertForbidden();

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::EN_REPARACION->value);
});

test('repair cannot be sent to testing twice through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaPruebasHttp(
        'cliente'
    );

    $supervisor = usuarioParaPruebasHttp(
        'supervisor'
    );

    $empleado = usuarioParaPruebasHttp(
        'empleado'
    );

    $orden = ordenParaPruebasHttp(
        $cliente,
        'REP-TESTING-HTTP-TWICE-001'
    );

    asignacionParaPruebasHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $ruta = route('empleado.ordenes.pruebas', [
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
        ->toBe(EstadoOrden::EN_PRUEBAS->value);
});

test('testing comment cannot exceed maximum length through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaPruebasHttp(
        'cliente'
    );

    $supervisor = usuarioParaPruebasHttp(
        'supervisor'
    );

    $empleado = usuarioParaPruebasHttp(
        'empleado'
    );

    $orden = ordenParaPruebasHttp(
        $cliente,
        'REP-TESTING-HTTP-LONG-COMMENT-001'
    );

    asignacionParaPruebasHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->from(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->post(
            route('empleado.ordenes.pruebas', [
                'orden' => $orden->id,
            ]),
            [
                'comentario' => str_repeat('a', 2001),
            ]
        );

    $response
        ->assertRedirect(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertSessionHasErrors([
            'comentario',
        ]);

    Notification::assertNothingSent();

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::EN_REPARACION->value);
});
