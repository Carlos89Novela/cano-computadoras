<?php

use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Policies\OrdenServicioPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function usuarioParaPolicyRevisionCotizacion(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

function ordenParaPolicyRevisionCotizacion(
    User $cliente,
    string $folio,
    string $estado = 'En diagnóstico'
): OrdenServicio {
    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude Revision Policy',
        'numero_serie' => 'SERIE-'.$folio,
        'descripcion' => 'Equipo para probar revision de cotizacion.',
    ]);

    return OrdenServicio::query()->create([
        'folio' => $folio,
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo no inicia correctamente.',
        'diagnostico' => 'Falla detectada en la fuente.',
        'costo_estimado' => 850,
        'estado' => $estado,
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::SIN_SOLICITAR,
        'fecha_ingreso' => now()->toDateString(),
    ]);
}

function asignarParaPolicyRevisionCotizacion(
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
        'observaciones' => 'Preparar cotizacion.',
    ]);
}

test('assigned employee can request a new quote review', function () {
    $cliente = usuarioParaPolicyRevisionCotizacion(
        'cliente'
    );

    $supervisor = usuarioParaPolicyRevisionCotizacion(
        'supervisor'
    );

    $empleado = usuarioParaPolicyRevisionCotizacion(
        'empleado'
    );

    $orden = ordenParaPolicyRevisionCotizacion(
        $cliente,
        'REP-QUOTE-POLICY-001'
    );

    asignarParaPolicyRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->requestQuoteReview(
            $empleado,
            $orden
        )
    )->toBeTrue();
});

test('assigned employee can resubmit a rejected quote', function () {
    $cliente = usuarioParaPolicyRevisionCotizacion(
        'cliente'
    );

    $supervisor = usuarioParaPolicyRevisionCotizacion(
        'supervisor'
    );

    $empleado = usuarioParaPolicyRevisionCotizacion(
        'empleado'
    );

    $orden = ordenParaPolicyRevisionCotizacion(
        $cliente,
        'REP-QUOTE-POLICY-REJECTED-001'
    );

    asignarParaPolicyRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    $orden->update([
        'estado_revision_cotizacion' => EstadoRevisionCotizacion::RECHAZADA,
        'cotizacion_revisada_por_id' => $supervisor->id,
        'cotizacion_revisada_at' => now(),
        'observacion_revision_cotizacion' => 'Corregir el costo estimado.',
    ]);

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->requestQuoteReview(
            $empleado,
            $orden
        )
    )->toBeTrue();
});

test('another employee cannot request quote review', function () {
    $cliente = usuarioParaPolicyRevisionCotizacion(
        'cliente'
    );

    $supervisor = usuarioParaPolicyRevisionCotizacion(
        'supervisor'
    );

    $empleadoAsignado = usuarioParaPolicyRevisionCotizacion(
        'empleado'
    );

    $otroEmpleado = usuarioParaPolicyRevisionCotizacion(
        'empleado'
    );

    $orden = ordenParaPolicyRevisionCotizacion(
        $cliente,
        'REP-QUOTE-POLICY-OTHER-001'
    );

    asignarParaPolicyRevisionCotizacion(
        $orden,
        $empleadoAsignado,
        $supervisor
    );

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->requestQuoteReview(
            $otroEmpleado,
            $orden
        )
    )->toBeFalse();
});

test('inactive assignment cannot request quote review', function () {
    $cliente = usuarioParaPolicyRevisionCotizacion(
        'cliente'
    );

    $supervisor = usuarioParaPolicyRevisionCotizacion(
        'supervisor'
    );

    $empleado = usuarioParaPolicyRevisionCotizacion(
        'empleado'
    );

    $orden = ordenParaPolicyRevisionCotizacion(
        $cliente,
        'REP-QUOTE-POLICY-INACTIVE-001'
    );

    asignarParaPolicyRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor,
        false
    );

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->requestQuoteReview(
            $empleado,
            $orden
        )
    )->toBeFalse();
});

test('pending or approved quote cannot be submitted again', function (
    EstadoRevisionCotizacion $estadoRevision
) {
    $cliente = usuarioParaPolicyRevisionCotizacion(
        'cliente'
    );

    $supervisor = usuarioParaPolicyRevisionCotizacion(
        'supervisor'
    );

    $empleado = usuarioParaPolicyRevisionCotizacion(
        'empleado'
    );

    $orden = ordenParaPolicyRevisionCotizacion(
        $cliente,
        'REP-QUOTE-POLICY-STATUS-'
        .$estadoRevision->value
    );

    asignarParaPolicyRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    $orden->update([
        'estado_revision_cotizacion' => $estadoRevision,
    ]);

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->requestQuoteReview(
            $empleado,
            $orden
        )
    )->toBeFalse();
})->with([
    'revision pendiente' => EstadoRevisionCotizacion::PENDIENTE,
    'revision aprobada' => EstadoRevisionCotizacion::APROBADA,
]);

test('finalized order cannot request quote review', function (
    string $estado
) {
    $cliente = usuarioParaPolicyRevisionCotizacion(
        'cliente'
    );

    $supervisor = usuarioParaPolicyRevisionCotizacion(
        'supervisor'
    );

    $empleado = usuarioParaPolicyRevisionCotizacion(
        'empleado'
    );

    $orden = ordenParaPolicyRevisionCotizacion(
        $cliente,
        'REP-QUOTE-POLICY-FINAL-'.md5($estado),
        $estado
    );

    asignarParaPolicyRevisionCotizacion(
        $orden,
        $empleado,
        $supervisor
    );

    $policy = app(OrdenServicioPolicy::class);

    expect(
        $policy->requestQuoteReview(
            $empleado,
            $orden
        )
    )->toBeFalse();
})->with([
    'orden entregada' => EstadoOrden::ENTREGADO->value,
    'orden cancelada' => EstadoOrden::CANCELADO->value,
]);
