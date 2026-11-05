<?php

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
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

function usuarioParaEntregaHttpFinal(
    string $rol
): User {
    $usuario = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenListaParaEntregaHttpFinal(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Entrega HTTP',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar la entrega HTTP.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'diagnostico' => 'Se reemplazó la fuente dañada.',
        'costo_estimado' => 850,
        'costo_final' => 875.50,
        'estado' => EstadoOrden::LISTO_PARA_ENTREGA->value,
        'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => now(),
        'fecha_ingreso' => now()->toDateString(),
        'fecha_entrega' => null,
    ]);
}

test('administrator can deliver ready order through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttpFinal(
        'cliente'
    );

    $administrador = usuarioParaEntregaHttpFinal(
        'administrador'
    );

    $orden = ordenListaParaEntregaHttpFinal(
        $cliente,
        'REP-DELIVERY-HTTP-ADMIN-001'
    );

    $response = $this
        ->actingAs($administrador)
        ->post(
            route('operacion.ordenes.entregar', [
                'orden' => $orden->id,
            ]),
            [
                'comentario' => 'Equipo entregado directamente al cliente.',
            ]
        );

    $response
        ->assertRedirect(
            route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
        )
        ->assertSessionHas(
            'success',
            'La entrega del equipo fue registrada correctamente.'
        );

    $orden->refresh();

    expect($orden->estado)
        ->toBe(EstadoOrden::ENTREGADO->value)
        ->and($orden->fecha_entrega)
        ->not->toBeNull();

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class
    );

    Notification::assertCount(1);
});

test('supervisor can deliver ready order through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttpFinal(
        'cliente'
    );

    $supervisor = usuarioParaEntregaHttpFinal(
        'supervisor'
    );

    $orden = ordenListaParaEntregaHttpFinal(
        $cliente,
        'REP-DELIVERY-HTTP-SUPERVISOR-001'
    );

    $this
        ->actingAs($supervisor)
        ->post(
            route('operacion.ordenes.entregar', [
                'orden' => $orden->id,
            ])
        )
        ->assertRedirect(
            route('supervisor.dashboard')
            .'#entregas'
        )
        ->assertSessionHas(
            'success',
            'La entrega del equipo fue registrada correctamente.'
        );

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::ENTREGADO->value);

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class
    );
});

test('employee and client cannot use delivery endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttpFinal(
        'cliente'
    );

    $empleado = usuarioParaEntregaHttpFinal(
        'empleado'
    );

    $orden = ordenListaParaEntregaHttpFinal(
        $cliente,
        'REP-DELIVERY-HTTP-DENIED-001'
    );

    $ruta = route('operacion.ordenes.entregar', [
        'orden' => $orden->id,
    ]);

    $this
        ->actingAs($empleado)
        ->post($ruta)
        ->assertForbidden();

    $this
        ->actingAs($cliente)
        ->post($ruta)
        ->assertForbidden();

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::LISTO_PARA_ENTREGA->value
        );

    Notification::assertNothingSent();
});

test('order cannot be delivered twice through endpoint', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttpFinal(
        'cliente'
    );

    $administrador = usuarioParaEntregaHttpFinal(
        'administrador'
    );

    $orden = ordenListaParaEntregaHttpFinal(
        $cliente,
        'REP-DELIVERY-HTTP-TWICE-001'
    );

    $ruta = route('operacion.ordenes.entregar', [
        'orden' => $orden->id,
    ]);

    $this
        ->actingAs($administrador)
        ->post($ruta)
        ->assertRedirect();

    $this
        ->actingAs($administrador)
        ->post($ruta)
        ->assertForbidden();

    Notification::assertCount(1);

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::ENTREGADO->value);
});

test('delivery comment cannot exceed maximum length', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaHttpFinal(
        'cliente'
    );

    $administrador = usuarioParaEntregaHttpFinal(
        'administrador'
    );

    $orden = ordenListaParaEntregaHttpFinal(
        $cliente,
        'REP-DELIVERY-HTTP-LONG-COMMENT-001'
    );

    $detalle = route('admin.ordenes.edit', [
        'orden' => $orden->id,
    ]);

    $this
        ->actingAs($administrador)
        ->from($detalle)
        ->post(
            route('operacion.ordenes.entregar', [
                'orden' => $orden->id,
            ]),
            [
                'comentario' => str_repeat('a', 2001),
            ]
        )
        ->assertRedirect($detalle)
        ->assertSessionHasErrors([
            'comentario',
        ]);

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::LISTO_PARA_ENTREGA->value
        )
        ->and($orden->fresh()?->fecha_entrega)
        ->toBeNull();

    Notification::assertNothingSent();
});
