<?php

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function crearUsuarioParaRevisionHttp(string $rol): User
{
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function crearOrdenParaRevisionHttp(
    User $cliente,
    string $folio,
    ?string $diagnostico = 'Falla detectada en la fuente.',
    int|float|null $costoEstimado = 850,
    string $estado = 'En diagnóstico'
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Revision HTTP',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar revision HTTP.',
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

function asignarOrdenParaRevisionHttp(
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

test('assigned employee can request quote review through the endpoint', function () {
    $cliente = crearUsuarioParaRevisionHttp('cliente');
    $supervisor = crearUsuarioParaRevisionHttp('supervisor');
    $empleado = crearUsuarioParaRevisionHttp('empleado');

    $orden = crearOrdenParaRevisionHttp(
        $cliente,
        'REP-QUOTE-HTTP-001'
    );

    asignarOrdenParaRevisionHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->post(
            route(
                'empleado.ordenes.cotizacion.revision',
                [
                    'orden' => $orden->id,
                ]
            )
        );

    $response
        ->assertRedirect(
            route(
                'empleado.ordenes.show',
                [
                    'orden' => $orden->id,
                ]
            )
        )
        ->assertSessionHas(
            'success',
            'La cotizacion fue enviada a revision del supervisor.'
        );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::PENDIENTE)
        ->and($orden->estado)
        ->toBe(EstadoOrden::EN_DIAGNOSTICO->value)
        ->and($orden->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value)
        ->and($orden->cotizacion_revisada_por_id)
        ->toBeNull()
        ->and($orden->cotizacion_revisada_at)
        ->toBeNull()
        ->and($orden->observacion_revision_cotizacion)
        ->toBeNull();

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $empleado->id,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'comentarios' => 'Cotizacion enviada a revision del supervisor.',
        'mensaje_cliente' => null,
    ]);
});

test('request without diagnosis returns a controlled error', function () {
    $cliente = crearUsuarioParaRevisionHttp('cliente');
    $supervisor = crearUsuarioParaRevisionHttp('supervisor');
    $empleado = crearUsuarioParaRevisionHttp('empleado');

    $orden = crearOrdenParaRevisionHttp(
        $cliente,
        'REP-QUOTE-HTTP-NO-DIAGNOSIS-001',
        null,
        850
    );

    asignarOrdenParaRevisionHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->from(
            route(
                'empleado.ordenes.show',
                [
                    'orden' => $orden->id,
                ]
            )
        )
        ->post(
            route(
                'empleado.ordenes.cotizacion.revision',
                [
                    'orden' => $orden->id,
                ]
            )
        );

    $response
        ->assertRedirect(
            route(
                'empleado.ordenes.show',
                [
                    'orden' => $orden->id,
                ]
            )
        )
        ->assertSessionHasErrors([
            'cotizacion',
        ]);

    expect(
        session('errors')
            ?->getBag('default')
            ->first('cotizacion')
    )->toBe(
        'Debes registrar el diagnostico antes de solicitar la revision.'
    );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('request without estimated cost returns a controlled error', function () {
    $cliente = crearUsuarioParaRevisionHttp('cliente');
    $supervisor = crearUsuarioParaRevisionHttp('supervisor');
    $empleado = crearUsuarioParaRevisionHttp('empleado');

    $orden = crearOrdenParaRevisionHttp(
        $cliente,
        'REP-QUOTE-HTTP-NO-COST-001',
        'Falla detectada en la tarjeta principal.',
        null
    );

    asignarOrdenParaRevisionHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->from(
            route(
                'empleado.ordenes.show',
                [
                    'orden' => $orden->id,
                ]
            )
        )
        ->post(
            route(
                'empleado.ordenes.cotizacion.revision',
                [
                    'orden' => $orden->id,
                ]
            )
        );

    $response
        ->assertRedirect(
            route(
                'empleado.ordenes.show',
                [
                    'orden' => $orden->id,
                ]
            )
        )
        ->assertSessionHasErrors([
            'cotizacion',
        ]);

    expect(
        session('errors')
            ?->getBag('default')
            ->first('cotizacion')
    )->toBe(
        'Debes registrar el costo estimado antes de solicitar la revision.'
    );

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('another employee cannot request quote review', function () {
    $cliente = crearUsuarioParaRevisionHttp('cliente');
    $supervisor = crearUsuarioParaRevisionHttp('supervisor');
    $empleadoAsignado = crearUsuarioParaRevisionHttp('empleado');
    $otroEmpleado = crearUsuarioParaRevisionHttp('empleado');

    $orden = crearOrdenParaRevisionHttp(
        $cliente,
        'REP-QUOTE-HTTP-OTHER-001'
    );

    asignarOrdenParaRevisionHttp(
        $orden,
        $empleadoAsignado,
        $supervisor
    );

    $this
        ->actingAs($otroEmpleado)
        ->post(
            route(
                'empleado.ordenes.cotizacion.revision',
                [
                    'orden' => $orden->id,
                ]
            )
        )
        ->assertForbidden();

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('inactive assignment cannot request quote review', function () {
    $cliente = crearUsuarioParaRevisionHttp('cliente');
    $supervisor = crearUsuarioParaRevisionHttp('supervisor');
    $empleado = crearUsuarioParaRevisionHttp('empleado');

    $orden = crearOrdenParaRevisionHttp(
        $cliente,
        'REP-QUOTE-HTTP-INACTIVE-001'
    );

    asignarOrdenParaRevisionHttp(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    $this
        ->actingAs($empleado)
        ->post(
            route(
                'empleado.ordenes.cotizacion.revision',
                [
                    'orden' => $orden->id,
                ]
            )
        )
        ->assertForbidden();

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('pending quote review cannot be requested again', function () {
    $cliente = crearUsuarioParaRevisionHttp('cliente');
    $supervisor = crearUsuarioParaRevisionHttp('supervisor');
    $empleado = crearUsuarioParaRevisionHttp('empleado');

    $orden = crearOrdenParaRevisionHttp(
        $cliente,
        'REP-QUOTE-HTTP-DUPLICATE-001'
    );

    asignarOrdenParaRevisionHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $orden->update([
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::PENDIENTE,
    ]);

    $this
        ->actingAs($empleado)
        ->post(
            route(
                'empleado.ordenes.cotizacion.revision',
                [
                    'orden' => $orden->id,
                ]
            )
        )
        ->assertForbidden();

    expect($orden->historial()->count())
        ->toBe(0);
});

test('non employee roles cannot use the quote review endpoint', function () {
    $cliente = crearUsuarioParaRevisionHttp('cliente');
    $supervisor = crearUsuarioParaRevisionHttp('supervisor');
    $administrador = crearUsuarioParaRevisionHttp(
        'administrador'
    );
    $empleado = crearUsuarioParaRevisionHttp('empleado');

    $orden = crearOrdenParaRevisionHttp(
        $cliente,
        'REP-QUOTE-HTTP-ROLES-001'
    );

    asignarOrdenParaRevisionHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $ruta = route(
        'empleado.ordenes.cotizacion.revision',
        [
            'orden' => $orden->id,
        ]
    );

    $this
        ->actingAs($cliente)
        ->post($ruta)
        ->assertForbidden();

    $this
        ->actingAs($supervisor)
        ->post($ruta)
        ->assertForbidden();

    $this
        ->actingAs($administrador)
        ->post($ruta)
        ->assertForbidden();

    $orden->refresh();

    expect($orden->estado_revision_cotizacion)
        ->toBe(EstadoRevisionCotizacion::SIN_SOLICITAR)
        ->and($orden->historial()->count())
        ->toBe(0);
});
