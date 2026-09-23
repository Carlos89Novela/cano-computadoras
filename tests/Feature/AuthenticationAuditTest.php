<?php

use App\Models\Auditoria;
use App\Models\RegistroAcceso;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

test('successful login creates access and audit records', function () {
    $usuario = User::factory()->create([
        'email' => 'acceso@cano.test',
        'password' => Hash::make('Password123!'),
        'email_verified_at' => now(),
    ]);

    $usuario->assignRole('cliente');

    $this->post('/login', [
        'email' => 'acceso@cano.test',
        'password' => 'Password123!',
    ])->assertRedirect();

    $registro = RegistroAcceso::query()
        ->where('evento', 'inicio_exitoso')
        ->firstOrFail();

    expect($registro->user_id)
        ->toBe($usuario->id)
        ->and($registro->correo_intentado)
        ->toBe('acceso@cano.test')
        ->and($registro->resultado)
        ->toBe('exitoso')
        ->and($registro->sesion_id)
        ->not->toBeNull();

    $this->assertDatabaseHas('auditorias', [
        'usuario_id' => $usuario->id,
        'accion' => 'autenticacion.inicio_exitoso',
        'resultado' => 'exitoso',
    ]);
});

test('failed login records email without password', function () {
    User::factory()->create([
        'email' => 'fallo@cano.test',
        'password' => Hash::make('Password123!'),
    ]);

    $this->post('/login', [
        'email' => 'fallo@cano.test',
        'password' => 'ContrasenaIncorrecta',
    ])->assertSessionHasErrors([
        'email',
    ]);

    $registro = RegistroAcceso::query()
        ->where('evento', 'inicio_fallido')
        ->firstOrFail();

    expect($registro->correo_intentado)
        ->toBe('fallo@cano.test')
        ->and($registro->resultado)
        ->toBe('fallido')
        ->and(json_encode($registro->toArray()))
        ->not->toContain('ContrasenaIncorrecta');

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'autenticacion.inicio_fallido'
        )
        ->firstOrFail();

    expect(json_encode($auditoria->toArray()))
        ->not->toContain('ContrasenaIncorrecta');
});

test('unknown email failed login has no user id', function () {
    $this->post('/login', [
        'email' => 'desconocido@cano.test',
        'password' => 'Password123!',
    ])->assertSessionHasErrors([
        'email',
    ]);

    $registro = RegistroAcceso::query()
        ->where('evento', 'inicio_fallido')
        ->firstOrFail();

    expect($registro->user_id)
        ->toBeNull()
        ->and($registro->correo_intentado)
        ->toBe('desconocido@cano.test');
});

test('logout creates access and audit records', function () {
    $usuario = User::factory()->create();

    $this
        ->actingAs($usuario)
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertDatabaseHas(
        'registros_acceso',
        [
            'user_id' => $usuario->id,
            'evento' => 'cierre_sesion',
            'resultado' => 'exitoso',
        ]
    );

    $this->assertDatabaseHas(
        'auditorias',
        [
            'usuario_id' => $usuario->id,
            'accion' => 'autenticacion.cierre_sesion',
            'resultado' => 'exitoso',
        ]
    );
});

test('access records cannot be updated or deleted', function () {
    $registro = RegistroAcceso::query()->create([
        'evento' => 'inicio_fallido',
        'resultado' => 'fallido',
        'correo_intentado' => 'prueba@cano.test',
    ]);

    expect(
        fn () => $registro->update([
            'resultado' => 'exitoso',
        ])
    )->toThrow(LogicException::class);

    expect(
        fn () => $registro->delete()
    )->toThrow(LogicException::class);
});
