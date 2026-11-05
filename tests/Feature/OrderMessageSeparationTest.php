<?php

use App\Enums\EstadoOrden;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\EstadoReparacionActualizado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function crearAdministradorParaMensajes(): User
{
    Role::firstOrCreate([
        'name' => 'administrador',
        'guard_name' => 'web',
    ]);

    $administrador = User::factory()->create();

    $administrador->assignRole('administrador');

    return $administrador;
}

function crearOrdenParaMensajes(
    User $cliente,
    string $folio,
    string $estado = 'Recibido'
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude de mensajes',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo utilizado en pruebas de mensajes.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo presenta una falla durante el inicio.',
        'estado' => $estado,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

test('internal comment is stored without notifying the customer', function () {
    Notification::fake();

    $administrador = crearAdministradorParaMensajes();
    $cliente = User::factory()->create();

    $orden = crearOrdenParaMensajes(
        $cliente,
        'REP-INTERNAL-MESSAGE-001'
    );

    $response = $this
        ->actingAs($administrador)
        ->put(
            route('admin.ordenes.update', [
                'orden' => $orden->id,
            ]),
            [
                'estado' => EstadoOrden::RECIBIDO->value,
                'diagnostico' => null,
                'costo_estimado' => null,
                'costo_final' => null,
                'comentario' => 'Esta es una nota técnica exclusivamente interna.',
                'mensaje_cliente' => null,
            ]
        );

    $response->assertRedirect(
        route('admin.ordenes.edit', [
            'orden' => $orden->id,
        ])
    );

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'comentarios' => 'Esta es una nota técnica exclusivamente interna.',
        'mensaje_cliente' => null,
    ]);

    Notification::assertNotSentTo(
        $cliente,
        EstadoReparacionActualizado::class
    );
});

test('customer message is stored and sent without becoming an internal comment', function () {
    Notification::fake();

    $administrador = crearAdministradorParaMensajes();
    $cliente = User::factory()->create();

    $orden = crearOrdenParaMensajes(
        $cliente,
        'REP-CUSTOMER-MESSAGE-001'
    );

    $response = $this
        ->actingAs($administrador)
        ->put(
            route('admin.ordenes.update', [
                'orden' => $orden->id,
            ]),
            [
                'estado' => EstadoOrden::RECIBIDO->value,
                'diagnostico' => null,
                'costo_estimado' => null,
                'costo_final' => null,
                'comentario' => null,
                'mensaje_cliente' => 'Tu equipo está listo para continuar con el diagnóstico.',
            ]
        );

    $response->assertRedirect(
        route('admin.ordenes.edit', [
            'orden' => $orden->id,
        ])
    );

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'comentarios' => null,
        'mensaje_cliente' => 'Tu equipo está listo para continuar con el diagnóstico.',
    ]);

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class,
        function (
            EstadoReparacionActualizado $notificacion
        ): bool {
            return $notificacion->mensajeCliente
                === 'Tu equipo está listo para continuar con el diagnóstico.';
        }
    );
});

test('state change keeps internal and customer messages separated', function () {
    Notification::fake();

    $administrador = crearAdministradorParaMensajes();
    $cliente = User::factory()->create();

    $orden = crearOrdenParaMensajes(
        $cliente,
        'REP-STATE-MESSAGE-001'
    );

    $response = $this
        ->actingAs($administrador)
        ->put(
            route('admin.ordenes.update', [
                'orden' => $orden->id,
            ]),
            [
                'estado' => EstadoOrden::EN_REPARACION->value,
                'diagnostico' => 'Se inició la reparación del equipo.',
                'costo_estimado' => 850.00,
                'costo_final' => null,
                'comentario' => 'Se detectó desgaste interno en un componente.',
                'mensaje_cliente' => 'La reparación de tu equipo ya comenzó.',
            ]
        );

    $response->assertRedirect(
        route('admin.ordenes.edit', [
            'orden' => $orden->id,
        ])
    );

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'estado' => EstadoOrden::EN_REPARACION->value,
        'comentarios' => 'Se detectó desgaste interno en un componente.',
        'mensaje_cliente' => 'La reparación de tu equipo ya comenzó.',
    ]);

    Notification::assertSentTo(
        $cliente,
        EstadoReparacionActualizado::class,
        function (
            EstadoReparacionActualizado $notificacion
        ): bool {
            return $notificacion->mensajeCliente
                === 'La reparación de tu equipo ya comenzó.';
        }
    );
});

test('customer private view displays the customer message but not the internal comment', function () {
    $administrador = crearAdministradorParaMensajes();
    $cliente = User::factory()->create();

    $orden = crearOrdenParaMensajes(
        $cliente,
        'REP-PRIVATE-VIEW-001'
    );

    $orden->historial()->create([
        'user_id' => $administrador->id,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'comentarios' => 'Nota técnica que el cliente no debe visualizar.',
        'mensaje_cliente' => 'Estamos revisando cuidadosamente tu equipo.',
    ]);

    $response = $this
        ->actingAs($cliente)
        ->get(
            route('ordenes.show', [
                'orden' => $orden->id,
            ])
        );

    $response
        ->assertOk()
        ->assertSee('Estamos revisando cuidadosamente tu equipo.')
        ->assertDontSee(
            'Nota técnica que el cliente no debe visualizar.'
        );
});

test('public tracking does not display either internal or customer messages', function () {
    $administrador = crearAdministradorParaMensajes();
    $cliente = User::factory()->create();

    $orden = crearOrdenParaMensajes(
        $cliente,
        'REP-PUBLIC-MESSAGE-001'
    );

    $orden->historial()->create([
        'user_id' => $administrador->id,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'comentarios' => 'Nota interna reservada para administración.',
        'mensaje_cliente' => 'Mensaje privado disponible al iniciar sesión.',
    ]);

    $response = $this->get(
        route('seguimiento.show', [
            'token' => $orden->token_seguimiento,
        ])
    );

    $response
        ->assertOk()
        ->assertDontSee(
            'Nota interna reservada para administración.'
        )
        ->assertDontSee(
            'Mensaje privado disponible al iniciar sesión.'
        );
});
