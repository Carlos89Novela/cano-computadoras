<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    app(PermissionRegistrar::class)
        ->forgetCachedPermissions();
});

function crearUsuarioConRolParaPanel(string $rol): User
{
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

test('guest users cannot access internal role dashboards', function () {
    $this
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    $this
        ->get(route('supervisor.dashboard'))
        ->assertRedirect(route('login'));

    $this
        ->get(route('empleado.dashboard'))
        ->assertRedirect(route('login'));
});

test('administrator can access only the administrator dashboard', function () {
    $administrador = crearUsuarioConRolParaPanel(
        'administrador'
    );

    $this
        ->actingAs($administrador)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Panel del administrador');

    $this
        ->actingAs($administrador)
        ->get(route('supervisor.dashboard'))
        ->assertForbidden();

    $this
        ->actingAs($administrador)
        ->get(route('empleado.dashboard'))
        ->assertForbidden();
});

test('supervisor can access only the supervisor dashboard', function () {
    $supervisor = crearUsuarioConRolParaPanel(
        'supervisor'
    );

    $this
        ->actingAs($supervisor)
        ->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee('Panel del supervisor');

    $this
        ->actingAs($supervisor)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this
        ->actingAs($supervisor)
        ->get(route('empleado.dashboard'))
        ->assertForbidden();
});

test('employee can access only the employee dashboard', function () {
    $empleado = crearUsuarioConRolParaPanel(
        'empleado'
    );

    $this
        ->actingAs($empleado)
        ->get(route('empleado.dashboard'))
        ->assertOk()
        ->assertSee('Panel del empleado');

    $this
        ->actingAs($empleado)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this
        ->actingAs($empleado)
        ->get(route('supervisor.dashboard'))
        ->assertForbidden();
});

test('client cannot access internal role dashboards', function () {
    $cliente = crearUsuarioConRolParaPanel(
        'cliente'
    );

    $this
        ->actingAs($cliente)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this
        ->actingAs($cliente)
        ->get(route('supervisor.dashboard'))
        ->assertForbidden();

    $this
        ->actingAs($cliente)
        ->get(route('empleado.dashboard'))
        ->assertForbidden();
});
