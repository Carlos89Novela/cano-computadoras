<?php

use App\Enums\EstadoOrden;
use App\Enums\TipoEquipo;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function crearOrdenParaSeguimientoPublico(): array
{
    $cliente = User::factory()->create([
        'name' => 'Cliente Privado Seguimiento',
        'email' => 'cliente-privado@example.test',
    ]);

    $tecnico = User::factory()->create([
        'name' => 'Tecnico Interno Seguimiento',
        'email' => 'tecnico-interno@example.test',
    ]);

    $equipo = Equipo::query()->create([
        'user_id' => $cliente->id,
        'tipo' => TipoEquipo::LAPTOP->value,
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad Seguimiento',
        'numero_serie' => 'SERIE-PRIVADA-12345',
        'descripcion' => 'Descripción privada del equipo.',
    ]);

    $servicio = Servicio::query()->create([
        'nombre' => 'Diagnóstico de seguimiento',
        'descripcion' => 'Servicio utilizado en la prueba pública.',
        'precio' => 750.00,
        'activo' => true,
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'REP-PRIVACY-001',
        'token_seguimiento' => (string) Str::ulid(),
        'user_id' => $cliente->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => $servicio->id,
        'problema_reportado' => 'El equipo se apaga durante el uso.',
        'diagnostico' => 'Se detectó una falla en el sistema de enfriamiento.',
        'costo_estimado' => 950.00,
        'costo_final' => null,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $orden->historial()->create([
        'user_id' => $tecnico->id,
        'estado' => EstadoOrden::EN_DIAGNOSTICO->value,
        'comentarios' => 'Comentario interno que no debe aparecer públicamente.',
    ]);

    return [
        'cliente' => $cliente,
        'equipo' => $equipo,
        'orden' => $orden,
        'servicio' => $servicio,
        'tecnico' => $tecnico,
    ];
}

test('public tracking displays only necessary repair information', function () {
    $datos = crearOrdenParaSeguimientoPublico();

    $response = $this->get(
        route('seguimiento.show', [
            'token' => $datos['orden']->token_seguimiento,
        ])
    );

    $response
        ->assertOk()
        ->assertSee('REP-PRIVACY-001')
        ->assertSee('Laptop')
        ->assertSee('Lenovo')
        ->assertSee('ThinkPad Seguimiento')
        ->assertSee('Diagnóstico de seguimiento')
        ->assertSee('El equipo se apaga durante el uso.')
        ->assertSee('Se detectó una falla en el sistema de enfriamiento.')
        ->assertSee(EstadoOrden::EN_DIAGNOSTICO->value);
});

test('public tracking does not expose customer information', function () {
    $datos = crearOrdenParaSeguimientoPublico();

    $response = $this->get(
        route('seguimiento.show', [
            'token' => $datos['orden']->token_seguimiento,
        ])
    );

    $response
        ->assertOk()
        ->assertDontSee($datos['cliente']->name)
        ->assertDontSee($datos['cliente']->email);
});

test('public tracking does not expose equipment private information', function () {
    $datos = crearOrdenParaSeguimientoPublico();

    $response = $this->get(
        route('seguimiento.show', [
            'token' => $datos['orden']->token_seguimiento,
        ])
    );

    $response
        ->assertOk()
        ->assertDontSee($datos['equipo']->numero_serie)
        ->assertDontSee($datos['equipo']->descripcion);
});

test('public tracking does not expose internal history information', function () {
    $datos = crearOrdenParaSeguimientoPublico();

    $response = $this->get(
        route('seguimiento.show', [
            'token' => $datos['orden']->token_seguimiento,
        ])
    );

    $response
        ->assertOk()
        ->assertSee(EstadoOrden::EN_DIAGNOSTICO->value)
        ->assertDontSee(
            'Comentario interno que no debe aparecer públicamente.'
        )
        ->assertDontSee($datos['tecnico']->name)
        ->assertDontSee($datos['tecnico']->email);
});

test('public tracking returns not found for an unknown token', function () {
    $this
        ->get(
            route('seguimiento.show', [
                'token' => '01J00000000000000000000000',
            ])
        )
        ->assertNotFound();
});

test('public tracking cannot be accessed using only the order folio', function () {
    $datos = crearOrdenParaSeguimientoPublico();

    $this
        ->get('/seguimiento/'.$datos['orden']->folio)
        ->assertNotFound();
});
