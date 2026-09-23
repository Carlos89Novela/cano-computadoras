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

test('owner passes reserved permission checks', function () {
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole(
        'administrador'
    );

    expect($propietario->can(
        'usuarios.asignar_roles'
    ))
        ->toBeTrue()
        ->and($propietario->can(
            'usuarios.asignar_permisos'
        ))
        ->toBeTrue()
        ->and($propietario->can(
            'usuarios.modificar_propietario'
        ))
        ->toBeTrue()
        ->and($propietario->can(
            'auditoria.exportar_completa'
        ))
        ->toBeTrue();
});

test('delegated administrator does not receive reserved permissions', function () {
    $administrador = User::factory()->create([
        'es_propietario' => false,
        'email_verified_at' => now(),
    ]);

    $administrador->assignRole(
        'administrador'
    );

    expect($administrador->can(
        'usuarios.asignar_roles'
    ))
        ->toBeFalse()
        ->and($administrador->can(
            'usuarios.asignar_permisos'
        ))
        ->toBeFalse()
        ->and($administrador->can(
            'usuarios.modificar_propietario'
        ))
        ->toBeFalse()
        ->and($administrador->can(
            'auditoria.exportar_completa'
        ))
        ->toBeFalse();
});

test('owner override does not turn regular administrator into owner', function () {
    $administrador = User::factory()->create([
        'es_propietario' => false,
    ]);

    $administrador->assignRole(
        'administrador'
    );

    expect($administrador->esPropietario())
        ->toBeFalse()
        ->and($administrador->can(
            'usuarios.asignar_permisos'
        ))
        ->toBeFalse();
});
