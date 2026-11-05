<?php

use App\Actions\Ordenes\EntregarOrdenServicio;
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

function usuarioParaEntregaFinal(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenListaParaEntregaFinal(
    User $cliente,
    string $folio
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Entrega Final',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar la entrega final.',
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

test('administrator can deliver a ready service order', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaFinal('cliente');
    $administrador = usuarioParaEntregaFinal(
        'administrador'
    );

    $orden = ordenListaParaEntregaFinal(
        $cliente,
        'REP-DELIVERY-ADMIN-001'
    );

    $ordenActualizada = app(
        EntregarOrdenServicio::class
    )->ejecutar(
        $orden,
        $administrador,
        'Equipo entregado y recibido por el cliente.'
    );

    expect($ordenActualizada->estado)
        ->toBe(EstadoOrden::ENTREGADO->value)
        ->and($ordenActualizada->fecha_entrega)
        ->not->toBeNull()
        ->and((float) $ordenActualizada->costo_final)
        ->toBe(875.50);

    $this->assertDatabaseHas(
        'historial_reparaciones',
        [
            'orden_servicio_id' => $orden->id,
            'user_id' => $administrador->id,
            'estado' => EstadoOrden::ENTREGADO->value,
            'comentarios' => 'Equipo entregado y recibido por el cliente.',
            'mensaje_cliente' => 'Tu equipo fue entregado. Gracias por confiar en Cano Computadoras.',
        ]
    );

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class
    );

    Notification::assertCount(1);
});

test('supervisor can deliver a ready service order', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaFinal('cliente');
    $supervisor = usuarioParaEntregaFinal(
        'supervisor'
    );

    $orden = ordenListaParaEntregaFinal(
        $cliente,
        'REP-DELIVERY-SUPERVISOR-001'
    );

    app(EntregarOrdenServicio::class)->ejecutar(
        $orden,
        $supervisor
    );

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::ENTREGADO->value)
        ->and($orden->fresh()?->fecha_entrega)
        ->not->toBeNull();

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class
    );
});

test('employee cannot deliver a service order', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaFinal('cliente');
    $empleado = usuarioParaEntregaFinal('empleado');

    $orden = ordenListaParaEntregaFinal(
        $cliente,
        'REP-DELIVERY-EMPLOYEE-DENIED-001'
    );

    expect(
        fn () => app(
            EntregarOrdenServicio::class
        )->ejecutar(
            $orden,
            $empleado
        )
    )->toThrow(AuthorizationException::class);

    expect($orden->fresh()?->estado)
        ->toBe(
            EstadoOrden::LISTO_PARA_ENTREGA->value
        )
        ->and($orden->fresh()?->fecha_entrega)
        ->toBeNull();

    Notification::assertNothingSent();
});

test('order cannot be delivered from an invalid state', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaFinal('cliente');
    $administrador = usuarioParaEntregaFinal(
        'administrador'
    );

    $orden = ordenListaParaEntregaFinal(
        $cliente,
        'REP-DELIVERY-INVALID-STATE-001'
    );

    $orden->update([
        'estado' => EstadoOrden::EN_PRUEBAS->value,
    ]);

    expect(
        fn () => app(
            EntregarOrdenServicio::class
        )->ejecutar(
            $orden,
            $administrador
        )
    )->toThrow(
        RuntimeException::class,
        'Solo una reparación lista para entrega puede marcarse como entregada.'
    );

    Notification::assertNothingSent();
});

test('order without final cost cannot be delivered', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaFinal('cliente');
    $administrador = usuarioParaEntregaFinal(
        'administrador'
    );

    $orden = ordenListaParaEntregaFinal(
        $cliente,
        'REP-DELIVERY-NO-COST-001'
    );

    $orden->update([
        'costo_final' => null,
    ]);

    expect(
        fn () => app(
            EntregarOrdenServicio::class
        )->ejecutar(
            $orden,
            $administrador
        )
    )->toThrow(
        RuntimeException::class,
        'Debes registrar el costo final antes de entregar el equipo.'
    );

    Notification::assertNothingSent();
});

test('service order cannot be delivered twice', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaFinal('cliente');
    $administrador = usuarioParaEntregaFinal(
        'administrador'
    );

    $orden = ordenListaParaEntregaFinal(
        $cliente,
        'REP-DELIVERY-TWICE-001'
    );

    $accion = app(EntregarOrdenServicio::class);

    $accion->ejecutar(
        $orden,
        $administrador
    );

    expect(
        fn () => $accion->ejecutar(
            $orden,
            $administrador
        )
    )->toThrow(
        RuntimeException::class,
        'Solo una reparación lista para entrega puede marcarse como entregada.'
    );

    Notification::assertCount(1);

    expect($orden->fresh()?->estado)
        ->toBe(EstadoOrden::ENTREGADO->value);
});

test('delivery closes any residual active assignment', function () {
    Notification::fake();

    $cliente = usuarioParaEntregaFinal('cliente');
    $administrador = usuarioParaEntregaFinal(
        'administrador'
    );
    $supervisor = usuarioParaEntregaFinal('supervisor');
    $empleado = usuarioParaEntregaFinal('empleado');

    $orden = ordenListaParaEntregaFinal(
        $cliente,
        'REP-DELIVERY-RESIDUAL-001'
    );

    $asignacion = OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now(),
        'finalizado_at' => null,
        'activo' => true,
        'observaciones' => 'Asignación residual.',
    ]);

    app(EntregarOrdenServicio::class)->ejecutar(
        $orden,
        $administrador
    );

    $asignacion->refresh();

    expect((bool) $asignacion->activo)
        ->toBeFalse()
        ->and($asignacion->finalizado_at)
        ->not->toBeNull();
});
