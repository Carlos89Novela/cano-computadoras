<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

test('owner flag is cast to boolean', function () {
    $usuario = User::factory()->create([
        'es_propietario' => true,
    ]);

    expect($usuario->es_propietario)
        ->toBeTrue()
        ->and($usuario->esPropietario())
        ->toBeTrue();
});

test('regular administrator is not system owner', function () {
    $administrador = User::factory()->create([
        'es_propietario' => false,
    ]);

    $administrador->assignRole('administrador');

    expect($administrador->hasRole('administrador'))
        ->toBeTrue()
        ->and($administrador->esPropietario())
        ->toBeFalse();
});

test('owner remains an administrator with protected owner flag', function () {
    $propietario = User::factory()->create([
        'es_propietario' => true,
    ]);

    $propietario->assignRole('administrador');

    expect($propietario->hasRole('administrador'))
        ->toBeTrue()
        ->and($propietario->esPropietario())
        ->toBeTrue();
});

test('reserved permissions are defined in access configuration', function () {
    expect(
        config('access_control.permisos_reservados')
    )->toContain(
        'usuarios.asignar_roles',
        'usuarios.asignar_permisos',
        'usuarios.modificar_propietario',
        'auditoria.exportar_completa'
    );
});

test('assignable roles do not include a separate owner role', function () {
    expect(
        config('access_control.roles_asignables')
    )
        ->toContain(
            'cliente',
            'empleado',
            'supervisor',
            'administrador'
        )
        ->not->toContain('propietario');
});
