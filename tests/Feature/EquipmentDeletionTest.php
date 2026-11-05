<?php

use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user cannot delete equipment with repair orders', function () {
    $user = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $user->id,
        'tipo' => 'Laptop',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad de prueba',
        'numero_serie' => 'TEST-EQUIPMENT-001',
        'descripcion' => 'Equipo protegido por una orden.',
    ]);

    $orden = OrdenServicio::query()->create([
        'folio' => 'TEST-EQUIPMENT-ORDER-001',
        'user_id' => $user->id,
        'equipo_id' => $equipo->id,
        'servicio_id' => null,
        'problema_reportado' => 'El equipo presenta una falla intermitente al iniciar.',
        'estado' => 'Recibido',
        'fecha_ingreso' => now()->toDateString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(
            route('equipos.destroy', [
                'equipo' => $equipo->id,
            ])
        );

    $response
        ->assertRedirect(route('equipos.index'))
        ->assertSessionHas(
            'error',
            'No puedes eliminar un equipo que tiene órdenes de reparación registradas.'
        );

    $this->assertModelExists($equipo);
    $this->assertModelExists($orden);
});

test('user can delete equipment without repair orders', function () {
    $user = User::factory()->create();

    $equipo = Equipo::query()->create([
        'user_id' => $user->id,
        'tipo' => 'Computadora de escritorio',
        'marca' => 'Genérica',
        'modelo' => 'Equipo de prueba',
        'numero_serie' => 'TEST-EQUIPMENT-002',
        'descripcion' => 'Equipo sin órdenes relacionadas.',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(
            route('equipos.destroy', [
                'equipo' => $equipo->id,
            ])
        );

    $response
        ->assertRedirect(route('equipos.index'))
        ->assertSessionHas(
            'success',
            'Equipo eliminado correctamente.'
        );

    $this->assertModelMissing($equipo);
});
