<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertOk();
});

test('new users can register as clients', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $response->assertRedirect(
        route('dashboard', absolute: false)
    );

    $usuario = User::query()
        ->where('email', 'test@example.com')
        ->firstOrFail();

    expect($usuario->hasRole('cliente'))
        ->toBeTrue()
        ->and($usuario->getRoleNames()->all())
        ->toBe([
            'cliente',
        ]);
});
