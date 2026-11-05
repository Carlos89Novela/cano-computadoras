<?php

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
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

function crearUsuarioParaActualizacionTecnicaHttp(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function crearOrdenParaActualizacionTecnicaHttp(
    User $cliente,
    string $folio,
    string $estado = 'Recibido'
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude HTTP',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar actualización técnica HTTP.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo presenta una falla durante el inicio.',
        'diagnostico' => null,
        'costo_estimado' => null,
        'costo_final' => 1500,
        'estado' => $estado,
        'autorizacion' => EstadoAutorizacion::PENDIENTE->value,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignarOrdenParaActualizacionTecnicaHttp(
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
        'observaciones' => 'Realizar diagnóstico completo.',
    ]);
}

test('assigned employee can update technical information through the endpoint', function () {
    $cliente = crearUsuarioParaActualizacionTecnicaHttp(
        'cliente'
    );

    $supervisor = crearUsuarioParaActualizacionTecnicaHttp(
        'supervisor'
    );

    $empleado = crearUsuarioParaActualizacionTecnicaHttp(
        'empleado'
    );

    $orden = crearOrdenParaActualizacionTecnicaHttp(
        $cliente,
        'REP-TECHNICAL-HTTP-001',
        EstadoOrden::EN_DIAGNOSTICO->value
    );

    asignarOrdenParaActualizacionTecnicaHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $response = $this
        ->actingAs($empleado)
        ->patch(
            route('empleado.ordenes.tecnica.update', [
                'orden' => $orden->id,
            ]),
            [
                'diagnostico' => 'Se detectó una falla en la fuente de alimentación.',
                'costo_estimado' => 850,
                'comentario' => 'Se realizaron pruebas de voltaje.',
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
            'La información técnica fue actualizada correctamente.'
        );

    $orden->refresh();

    expect($orden->diagnostico)
        ->toBe('Se detectó una falla en la fuente de alimentación.')
        ->and((float) $orden->costo_estimado)
        ->toBe(850.0)
        ->and($orden->estado)
        ->toBe(EstadoOrden::EN_DIAGNOSTICO->value)
        ->and((float) $orden->costo_final)
        ->toBe(1500.0)
        ->and($orden->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value);

    $this->assertDatabaseHas('historial_reparaciones', [
        'orden_servicio_id' => $orden->id,
        'user_id' => $empleado->id,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'comentarios' => 'Se realizaron pruebas de voltaje.',
        'mensaje_cliente' => null,
    ]);
});

test('employee cannot update an order assigned to another employee', function () {
    $cliente = crearUsuarioParaActualizacionTecnicaHttp(
        'cliente'
    );

    $supervisor = crearUsuarioParaActualizacionTecnicaHttp(
        'supervisor'
    );

    $empleadoAsignado = crearUsuarioParaActualizacionTecnicaHttp(
        'empleado'
    );

    $otroEmpleado = crearUsuarioParaActualizacionTecnicaHttp(
        'empleado'
    );

    $orden = crearOrdenParaActualizacionTecnicaHttp(
        $cliente,
        'REP-TECHNICAL-HTTP-DENIED-001'
    );

    asignarOrdenParaActualizacionTecnicaHttp(
        $orden,
        $empleadoAsignado,
        $supervisor
    );

    $this
        ->actingAs($otroEmpleado)
        ->patch(
            route('empleado.ordenes.tecnica.update', [
                'orden' => $orden->id,
            ]),
            [
                'diagnostico' => 'Intento no autorizado.',
                'costo_estimado' => 300,
                'comentario' => 'Este comentario no debe guardarse.',
            ]
        )
        ->assertForbidden();

    $orden->refresh();

    expect($orden->diagnostico)
        ->toBeNull()
        ->and($orden->costo_estimado)
        ->toBeNull()
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('inactive assignment cannot update technical information', function () {
    $cliente = crearUsuarioParaActualizacionTecnicaHttp(
        'cliente'
    );

    $supervisor = crearUsuarioParaActualizacionTecnicaHttp(
        'supervisor'
    );

    $empleado = crearUsuarioParaActualizacionTecnicaHttp(
        'empleado'
    );

    $orden = crearOrdenParaActualizacionTecnicaHttp(
        $cliente,
        'REP-TECHNICAL-HTTP-INACTIVE-001'
    );

    asignarOrdenParaActualizacionTecnicaHttp(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    $this
        ->actingAs($empleado)
        ->patch(
            route('empleado.ordenes.tecnica.update', [
                'orden' => $orden->id,
            ]),
            [
                'diagnostico' => 'Cambio no autorizado.',
                'costo_estimado' => 400,
                'comentario' => null,
            ]
        )
        ->assertForbidden();

    expect($orden->historial()->count())
        ->toBe(0);
});

test('employee endpoint rejects protected order fields', function () {
    $cliente = crearUsuarioParaActualizacionTecnicaHttp(
        'cliente'
    );

    $supervisor = crearUsuarioParaActualizacionTecnicaHttp(
        'supervisor'
    );

    $empleado = crearUsuarioParaActualizacionTecnicaHttp(
        'empleado'
    );

    $orden = crearOrdenParaActualizacionTecnicaHttp(
        $cliente,
        'REP-TECHNICAL-HTTP-PROTECTED-001',
        EstadoOrden::EN_DIAGNOSTICO->value
    );

    asignarOrdenParaActualizacionTecnicaHttp(
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
        ->patch(
            route('empleado.ordenes.tecnica.update', [
                'orden' => $orden->id,
            ]),
            [
                'diagnostico' => 'Diagnóstico permitido.',
                'costo_estimado' => 500,
                'comentario' => null,
                'estado' => EstadoOrden::ENTREGADO->value,
                'costo_final' => 9999,
                'mensaje_cliente' => 'Mensaje no permitido.',
                'empleado_id' => $empleado->id,
                'autorizacion' => EstadoAutorizacion::AUTORIZADA->value,
                'fecha_entrega' => now()->toDateString(),
            ]
        );

    $response
        ->assertRedirect(
            route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
        )
        ->assertSessionHasErrors([
            'estado',
            'costo_final',
            'mensaje_cliente',
            'empleado_id',
            'autorizacion',
            'fecha_entrega',
        ]);

    $orden->refresh();

    expect($orden->diagnostico)
        ->toBeNull()
        ->and($orden->costo_estimado)
        ->toBeNull()
        ->and($orden->estado)
        ->toBe(EstadoOrden::EN_DIAGNOSTICO->value)
        ->and((float) $orden->costo_final)
        ->toBe(1500.0)
        ->and($orden->autorizacion)
        ->toBe(EstadoAutorizacion::PENDIENTE->value)
        ->and($orden->historial()->count())
        ->toBe(0);
});

test('non employee roles cannot use the technical update endpoint', function () {
    $cliente = crearUsuarioParaActualizacionTecnicaHttp(
        'cliente'
    );

    $supervisor = crearUsuarioParaActualizacionTecnicaHttp(
        'supervisor'
    );

    $administrador = crearUsuarioParaActualizacionTecnicaHttp(
        'administrador'
    );

    $empleado = crearUsuarioParaActualizacionTecnicaHttp(
        'empleado'
    );

    $orden = crearOrdenParaActualizacionTecnicaHttp(
        $cliente,
        'REP-TECHNICAL-HTTP-ROLES-001'
    );

    asignarOrdenParaActualizacionTecnicaHttp(
        $orden,
        $empleado,
        $supervisor
    );

    $ruta = route('empleado.ordenes.tecnica.update', [
        'orden' => $orden->id,
    ]);

    $datos = [
        'diagnostico' => 'Cambio no autorizado.',
        'costo_estimado' => 200,
        'comentario' => null,
    ];

    $this
        ->actingAs($cliente)
        ->patch($ruta, $datos)
        ->assertForbidden();

    $this
        ->actingAs($supervisor)
        ->patch($ruta, $datos)
        ->assertForbidden();

    $this
        ->actingAs($administrador)
        ->patch($ruta, $datos)
        ->assertForbidden();

    $orden->refresh();

    expect($orden->diagnostico)
        ->toBeNull()
        ->and($orden->historial()->count())
        ->toBe(0);
});
