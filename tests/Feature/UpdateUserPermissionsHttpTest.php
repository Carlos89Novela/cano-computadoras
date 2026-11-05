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

function propietarioParaPermisosHttp(): User
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole(
        'administrador'
    );

    return $propietario;
}

test('owner can update direct permissions through endpoint', function () {
    $propietario =
        propietarioParaPermisosHttp();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole(
        'supervisor'
    );

    $response = $this
        ->actingAs($propietario)
        ->patch(
            route(
                'admin.usuarios.permisos.update',
                [
                    'usuario' => $supervisor->id,
                ]
            ),
            [
                'permisos' => [
                    'servicios.actualizar_precios',
                    'inventario.ver',
                ],
                'motivo_permisos' => 'Responsable temporal de precios e inventario.',
            ]
        );

    $response
        ->assertRedirect(
            route(
                'admin.usuarios.edit',
                [
                    'usuario' => $supervisor->id,
                ]
            )
        )
        ->assertSessionHas(
            'success',
            'Los permisos del usuario fueron actualizados correctamente.'
        );

    $supervisor->refresh();

    expect(
        $supervisor->hasDirectPermission(
            'servicios.actualizar_precios'
        )
    )
        ->toBeTrue()
        ->and(
            $supervisor->hasDirectPermission(
                'inventario.ver'
            )
        )
        ->toBeTrue();

    $this->assertDatabaseHas(
        'auditorias',
        [
            'usuario_id' => $propietario->id,
            'usuario_afectado_id' => $supervisor->id,
            'accion' => 'usuario.permisos_actualizados',
            'motivo' => 'Responsable temporal de precios e inventario.',
        ]
    );
});

test('empty selection removes direct permissions through endpoint', function () {
    $propietario =
        propietarioParaPermisosHttp();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    $empleado->givePermissionTo(
        'inventario.ver'
    );

    $this
        ->actingAs($propietario)
        ->patch(
            route(
                'admin.usuarios.permisos.update',
                [
                    'usuario' => $empleado->id,
                ]
            ),
            [
                'permisos' => [],
                'motivo_permisos' => 'Se retiraron las responsabilidades adicionales.',
            ]
        )
        ->assertRedirect();

    expect(
        $empleado
            ->fresh()
            ->getDirectPermissions()
            ->count()
    )->toBe(0);
});

test('permission change requires a reason through endpoint', function () {
    $propietario =
        propietarioParaPermisosHttp();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    $detalle = route(
        'admin.usuarios.edit',
        [
            'usuario' => $empleado->id,
        ]
    );

    $this
        ->actingAs($propietario)
        ->from($detalle)
        ->patch(
            route(
                'admin.usuarios.permisos.update',
                [
                    'usuario' => $empleado->id,
                ]
            ),
            [
                'permisos' => [
                    'inventario.ver',
                ],
                'motivo_permisos' => '',
            ]
        )
        ->assertRedirect($detalle)
        ->assertSessionHasErrors([
            'motivo_permisos',
        ]);

    expect(
        $empleado
            ->fresh()
            ->getDirectPermissions()
            ->count()
    )->toBe(0);
});

test('reserved permission cannot be assigned through endpoint', function () {
    $propietario =
        propietarioParaPermisosHttp();

    $administrador = User::factory()->create([
        'es_propietario' => false,
        'email_verified_at' => now(),
    ]);

    $administrador->assignRole(
        'administrador'
    );

    $detalle = route(
        'admin.usuarios.edit',
        [
            'usuario' => $administrador->id,
        ]
    );

    $this
        ->actingAs($propietario)
        ->from($detalle)
        ->patch(
            route(
                'admin.usuarios.permisos.update',
                [
                    'usuario' => $administrador->id,
                ]
            ),
            [
                'permisos' => [
                    'usuarios.asignar_roles',
                ],
                'motivo_permisos' => 'Intento de permiso reservado.',
            ]
        )
        ->assertRedirect($detalle)
        ->assertSessionHasErrors([
            'permisos.0',
        ]);

    expect(
        $administrador
            ->fresh()
            ->can(
                'usuarios.asignar_roles'
            )
    )->toBeFalse();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'usuario.permisos_actualizados'
            )
            ->count()
    )->toBe(0);
});

test('delegated administrator cannot update permissions through endpoint', function () {
    $administrador = User::factory()->create([
        'es_propietario' => false,
        'email_verified_at' => now(),
    ]);

    $administrador->assignRole(
        'administrador'
    );

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    $this
        ->actingAs($administrador)
        ->patch(
            route(
                'admin.usuarios.permisos.update',
                [
                    'usuario' => $empleado->id,
                ]
            ),
            [
                'permisos' => [
                    'inventario.ver',
                ],
                'motivo_permisos' => 'Cambio no autorizado.',
            ]
        )
        ->assertForbidden();

    expect(
        $empleado
            ->fresh()
            ->getDirectPermissions()
            ->count()
    )->toBe(0);
});

test('owner cannot update own permissions through endpoint', function () {
    $propietario =
        propietarioParaPermisosHttp();

    $this
        ->actingAs($propietario)
        ->patch(
            route(
                'admin.usuarios.permisos.update',
                [
                    'usuario' => $propietario->id,
                ]
            ),
            [
                'permisos' => [
                    'inventario.ver',
                ],
                'motivo_permisos' => 'Intento no permitido.',
            ]
        )
        ->assertForbidden();
});
