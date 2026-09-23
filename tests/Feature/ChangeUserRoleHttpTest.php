<?php

use App\Models\Auditoria;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

function propietarioParaCambioRolHttp(): User
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    return $propietario;
}

test('owner can change user role through endpoint', function () {
    $propietario = propietarioParaCambioRolHttp();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    $response = $this
        ->actingAs($propietario)
        ->patch(
            route('admin.usuarios.rol.update', [
                'usuario' => $empleado->id,
            ]),
            [
                'rol' => 'supervisor',
                'motivo' => 'Asumirá la coordinación del taller.',
            ]
        );

    $response
        ->assertRedirect(
            route('admin.usuarios.edit', [
                'usuario' => $empleado->id,
            ])
        )
        ->assertSessionHas(
            'success',
            'El rol del usuario fue actualizado correctamente.'
        );

    expect($empleado->fresh()->hasRole('supervisor'))
        ->toBeTrue()
        ->and($empleado->fresh()->hasRole('empleado'))
        ->toBeFalse();

    $this->assertDatabaseHas('auditorias', [
        'usuario_id' => $propietario->id,
        'usuario_afectado_id' => $empleado->id,
        'accion' => 'usuario.rol_actualizado',
        'motivo' => 'Asumirá la coordinación del taller.',
    ]);
});

test('role change requires a reason through endpoint', function () {
    $propietario = propietarioParaCambioRolHttp();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    $detalle = route('admin.usuarios.edit', [
        'usuario' => $empleado->id,
    ]);

    $this
        ->actingAs($propietario)
        ->from($detalle)
        ->patch(
            route('admin.usuarios.rol.update', [
                'usuario' => $empleado->id,
            ]),
            [
                'rol' => 'supervisor',
                'motivo' => '',
            ]
        )
        ->assertRedirect($detalle)
        ->assertSessionHasErrors([
            'motivo',
        ]);

    expect($empleado->fresh()->hasRole('empleado'))
        ->toBeTrue();
});

test('delegated administrator cannot change user role', function () {
    $administrador = User::factory()->create([
        'es_propietario' => false,
        'email_verified_at' => now(),
    ]);

    $administrador->assignRole('administrador');

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    $this
        ->actingAs($administrador)
        ->patch(
            route('admin.usuarios.rol.update', [
                'usuario' => $empleado->id,
            ]),
            [
                'rol' => 'supervisor',
                'motivo' => 'Cambio no autorizado.',
            ]
        )
        ->assertForbidden();

    expect($empleado->fresh()->hasRole('empleado'))
        ->toBeTrue();
});

test('owner cannot change own role through endpoint', function () {
    $propietario = propietarioParaCambioRolHttp();

    $this
        ->actingAs($propietario)
        ->patch(
            route('admin.usuarios.rol.update', [
                'usuario' => $propietario->id,
            ]),
            [
                'rol' => 'cliente',
                'motivo' => 'Intento no permitido.',
            ]
        )
        ->assertForbidden();

    expect(
        $propietario->fresh()
            ->hasRole('administrador')
    )->toBeTrue();
});

test('unrecognized role is rejected through endpoint', function () {
    $propietario = propietarioParaCambioRolHttp();

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    $detalle = route('admin.usuarios.edit', [
        'usuario' => $empleado->id,
    ]);

    $this
        ->actingAs($propietario)
        ->from($detalle)
        ->patch(
            route('admin.usuarios.rol.update', [
                'usuario' => $empleado->id,
            ]),
            [
                'rol' => 'propietario',
                'motivo' => 'Rol reservado.',
            ]
        )
        ->assertRedirect($detalle)
        ->assertSessionHasErrors([
            'rol',
        ]);

    expect($empleado->fresh()->hasRole('empleado'))
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'usuario.rol_actualizado'
            )
            ->count()
    )->toBe(0);
});
