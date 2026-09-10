<?php

use App\Models\Equipo;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('order exposes its assignment history and active assignment', function () {
    $cliente = User::factory()->create();
    $empleado = User::factory()->create();
    $supervisor = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Dell',
        'modelo' => 'Latitude',
        'numero_serie' => 'ASIGNACION-RELACION-001',
        'descripcion' => 'Equipo para probar asignaciones.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-ASIGNACION-RELACION-001',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'problema_reportado' => 'El equipo presenta una falla al iniciar.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $asignacionAnterior = OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now()->subDay(),
        'finalizado_at' => now()->subHour(),
        'activo' => false,
        'observaciones' => 'Asignación anterior.',
    ]);

    $asignacionActiva = OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now(),
        'finalizado_at' => null,
        'activo' => true,
        'observaciones' => 'Asignación vigente.',
    ]);

    expect($orden->asignaciones()->count())
        ->toBe(2)
        ->and($orden->asignacionActiva?->is($asignacionActiva))
        ->toBeTrue()
        ->and($orden->asignacionActiva?->is($asignacionAnterior))
        ->toBeFalse();
});

test('users expose employee and author assignment relationships', function () {
    $cliente = User::factory()->create();
    $empleado = User::factory()->create();
    $supervisor = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => 'Laptop',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad',
        'numero_serie' => 'ASIGNACION-RELACION-002',
        'descripcion' => 'Segundo equipo de prueba.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-ASIGNACION-RELACION-002',
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'problema_reportado' => 'El equipo pierde la conexión durante el uso.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $asignacion = OrdenAsignacion::query()->create([
        'orden_servicio_id' => $orden->id,
        'empleado_id' => $empleado->id,
        'asignado_por_id' => $supervisor->id,
        'asignado_at' => now(),
        'activo' => true,
        'observaciones' => null,
    ]);

    expect(
        $empleado->asignacionesComoEmpleado()
            ->whereKey($asignacion->id)
            ->exists()
    )->toBeTrue()
        ->and(
            $supervisor->asignacionesRealizadas()
                ->whereKey($asignacion->id)
                ->exists()
        )
        ->toBeTrue()
        ->and($asignacion->ordenServicio->is($orden))
        ->toBeTrue()
        ->and($asignacion->empleado->is($empleado))
        ->toBeTrue()
        ->and($asignacion->asignadoPor->is($supervisor))
        ->toBeTrue();
});
