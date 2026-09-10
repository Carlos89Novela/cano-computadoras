<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function usuarioDeAutenticacionConRol(
    string $rol
): User {
    $usuario = User::factory()->create();

    $usuario->assignRole($rol);

    return $usuario;
}

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk();
});

test('administrator is redirected to the administrator dashboard', function () {
    $usuario = usuarioDeAutenticacionConRol(
        'administrador'
    );

    $response = $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($usuario);

    $response->assertRedirect(
        route('admin.dashboard', absolute: false)
    );
});

test('supervisor is redirected to the supervisor dashboard', function () {
    $usuario = usuarioDeAutenticacionConRol(
        'supervisor'
    );

    $response = $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($usuario);

    $response->assertRedirect(
        route('supervisor.dashboard', absolute: false)
    );
});

test('employee is redirected to the employee dashboard', function () {
    $usuario = usuarioDeAutenticacionConRol(
        'empleado'
    );

    $response = $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($usuario);

    $response->assertRedirect(
        route('empleado.dashboard', absolute: false)
    );
});

test('client is redirected to the client dashboard', function () {
    $usuario = usuarioDeAutenticacionConRol(
        'cliente'
    );

    $response = $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($usuario);

    $response->assertRedirect(
        route('dashboard', absolute: false)
    );
});

test('users without a role temporarily use the client dashboard', function () {
    $usuario = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($usuario);

    $response->assertRedirect(
        route('dashboard', absolute: false)
    );
});

test('users cannot authenticate with an invalid password', function () {
    $usuario = User::factory()->create();

    $this->post('/login', [
        'email' => $usuario->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $usuario = User::factory()->create();

    $response = $this
        ->actingAs($usuario)
        ->post('/logout');

    $this->assertGuest();

    $response->assertRedirect('/');
});
