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

test('owner can view user management', function () {
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    $usuario = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $usuario->assignRole('empleado');

    $this
        ->actingAs($propietario)
        ->get(route('admin.usuarios.index'))
        ->assertOk()
        ->assertSee('Usuarios y responsabilidades')
        ->assertSee($usuario->name);
});

test('delegated administrator cannot manage roles', function () {
    $administrador = User::factory()->create([
        'es_propietario' => false,
        'email_verified_at' => now(),
    ]);

    $administrador->assignRole('administrador');

    $this
        ->actingAs($administrador)
        ->get(route('admin.usuarios.index'))
        ->assertForbidden();
});

test('supervisor cannot access owner user management', function () {
    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $this
        ->actingAs($supervisor)
        ->get(route('admin.usuarios.index'))
        ->assertForbidden();
});

test('owner can open responsibilities for regular user', function () {
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    $usuario = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $usuario->assignRole('empleado');

    $this
        ->actingAs($propietario)
        ->get(
            route('admin.usuarios.edit', [
                'usuario' => $usuario->id,
            ])
        )
        ->assertOk()
        ->assertSee($usuario->name)
        ->assertSee('Empleado')
        ->assertSee('Cambiar rol base');
});

test('owner account is displayed as protected', function () {
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    $this
        ->actingAs($propietario)
        ->get(route('admin.usuarios.index'))
        ->assertOk()
        ->assertSee('Propietario')
        ->assertSee('Cuenta protegida');
});

test('guest cannot access owner user management', function () {
    $this
        ->get(route('admin.usuarios.index'))
        ->assertRedirect('/login');
});
