<?php

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\PresupuestoAutorizadoPorCliente;
use App\Notifications\PresupuestoRechazadoPorCliente;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

function usuarioParaDecisionPresupuesto(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaDecisionPresupuesto(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Decisión Cliente',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar la decisión del cliente.',
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
        'estado' => EstadoOrden::ESPERANDO_AUTORIZACION->value,
        'autorizacion' => EstadoAutorizacion::PENDIENTE->value,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::APROBADA,
        'fecha_autorizacion' => null,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignacionParaDecisionPresupuesto(
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
        'observaciones' => 'Continuar después de la decisión del cliente.',
    ]);
}

test('authorized budget notifies assigned employee and supervisors', function () {
    Notification::fake();

    $cliente = usuarioParaDecisionPresupuesto(
        'cliente'
    );

    $supervisorUno = usuarioParaDecisionPresupuesto(
        'supervisor'
    );

    $supervisorDos = usuarioParaDecisionPresupuesto(
        'supervisor'
    );

    $empleado = usuarioParaDecisionPresupuesto(
        'empleado'
    );

    $otroEmpleado = usuarioParaDecisionPresupuesto(
        'empleado'
    );

    $orden = ordenParaDecisionPresupuesto(
        $cliente,
        'REP-CUSTOMER-AUTHORIZED-001'
    );

    asignacionParaDecisionPresupuesto(
        $orden,
        $empleado,
        $supervisorUno
    );

    $this
        ->actingAs($cliente)
        ->post(
            route('ordenes.autorizar', [
                'orden' => $orden->id,
            ]),
            [
                'decision' => EstadoAutorizacion::AUTORIZADA->value,
            ]
        )
        ->assertRedirect(
            route('ordenes.show', [
                'orden' => $orden->id,
            ])
        );

    Notification::assertSentTo(
        $empleado,
        PresupuestoAutorizadoPorCliente::class
    );

    Notification::assertSentTo(
        $supervisorUno,
        PresupuestoAutorizadoPorCliente::class
    );

    Notification::assertSentTo(
        $supervisorDos,
        PresupuestoAutorizadoPorCliente::class
    );

    Notification::assertNotSentTo(
        $cliente,
        PresupuestoAutorizadoPorCliente::class
    );

    Notification::assertNotSentTo(
        $otroEmpleado,
        PresupuestoAutorizadoPorCliente::class
    );

    Notification::assertCount(3);

    $orden->refresh();

    expect($orden->autorizacion)
        ->toBe(EstadoAutorizacion::AUTORIZADA->value)
        ->and($orden->estado)
        ->toBe(EstadoOrden::ESPERANDO_REFACCION->value)
        ->and($orden->fecha_autorizacion)
        ->not->toBeNull();
});

test('rejected budget notifies assigned employee and supervisors', function () {
    Notification::fake();

    $cliente = usuarioParaDecisionPresupuesto(
        'cliente'
    );

    $supervisorUno = usuarioParaDecisionPresupuesto(
        'supervisor'
    );

    $supervisorDos = usuarioParaDecisionPresupuesto(
        'supervisor'
    );

    $empleado = usuarioParaDecisionPresupuesto(
        'empleado'
    );

    $orden = ordenParaDecisionPresupuesto(
        $cliente,
        'REP-CUSTOMER-REJECTED-001'
    );

    asignacionParaDecisionPresupuesto(
        $orden,
        $empleado,
        $supervisorUno
    );

    $this
        ->actingAs($cliente)
        ->post(
            route('ordenes.autorizar', [
                'orden' => $orden->id,
            ]),
            [
                'decision' => EstadoAutorizacion::RECHAZADA->value,
            ]
        )
        ->assertRedirect(
            route('ordenes.show', [
                'orden' => $orden->id,
            ])
        );

    Notification::assertSentTo(
        $empleado,
        PresupuestoRechazadoPorCliente::class
    );

    Notification::assertSentTo(
        $supervisorUno,
        PresupuestoRechazadoPorCliente::class
    );

    Notification::assertSentTo(
        $supervisorDos,
        PresupuestoRechazadoPorCliente::class
    );

    Notification::assertNotSentTo(
        $cliente,
        PresupuestoRechazadoPorCliente::class
    );

    Notification::assertCount(3);

    $orden->refresh();

    expect($orden->autorizacion)
        ->toBe(EstadoAutorizacion::RECHAZADA->value)
        ->and($orden->estado)
        ->toBe(EstadoOrden::CANCELADO->value)
        ->and($orden->fecha_autorizacion)
        ->not->toBeNull();
});

test('budget decision without active assignment notifies only supervisors', function () {
    Notification::fake();

    $cliente = usuarioParaDecisionPresupuesto(
        'cliente'
    );

    $supervisor = usuarioParaDecisionPresupuesto(
        'supervisor'
    );

    $empleado = usuarioParaDecisionPresupuesto(
        'empleado'
    );

    $orden = ordenParaDecisionPresupuesto(
        $cliente,
        'REP-CUSTOMER-NO-ACTIVE-ASSIGNMENT-001'
    );

    OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now()->subDay(),
        'finalizado_at' => now(),
        'activo' => false,
        'observaciones' => 'Asignación finalizada.',
    ]);

    $this
        ->actingAs($cliente)
        ->post(
            route('ordenes.autorizar', [
                'orden' => $orden->id,
            ]),
            [
                'decision' => EstadoAutorizacion::AUTORIZADA->value,
            ]
        )
        ->assertRedirect();

    Notification::assertSentTo(
        $supervisor,
        PresupuestoAutorizadoPorCliente::class
    );

    Notification::assertNotSentTo(
        $empleado,
        PresupuestoAutorizadoPorCliente::class
    );

    Notification::assertNotSentTo(
        $cliente,
        PresupuestoAutorizadoPorCliente::class
    );

    Notification::assertCount(1);
});

test('invalid budget decision sends no notifications', function () {
    Notification::fake();

    $cliente = usuarioParaDecisionPresupuesto(
        'cliente'
    );

    $supervisor = usuarioParaDecisionPresupuesto(
        'supervisor'
    );

    $empleado = usuarioParaDecisionPresupuesto(
        'empleado'
    );

    $orden = ordenParaDecisionPresupuesto(
        $cliente,
        'REP-CUSTOMER-INVALID-DECISION-001'
    );

    asignacionParaDecisionPresupuesto(
        $orden,
        $empleado,
        $supervisor
    );

    $this
        ->actingAs($cliente)
        ->post(
            route('ordenes.autorizar', [
                'orden' => $orden->id,
            ]),
            [
                'decision' => 'decision_invalida',
            ]
        )
        ->assertSessionHasErrors([
            'decision',
        ]);

    Notification::assertNothingSent();

    $orden->refresh();

    expect($orden->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value)
        ->and($orden->fecha_autorizacion)
        ->toBeNull()
        ->and($orden->estado)
        ->toBe(
            EstadoOrden::ESPERANDO_AUTORIZACION->value
        );
});
