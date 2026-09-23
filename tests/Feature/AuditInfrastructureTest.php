<?php

use App\Models\Auditoria;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use LogicException;

uses(RefreshDatabase::class);

test('audit service records actor target and changed values', function () {
    $actor = User::factory()->create();
    $afectado = User::factory()->create();

    $request = Request::create(
        '/propietario/usuarios/'.$afectado->id,
        'PUT',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '192.168.1.50',
            'HTTP_USER_AGENT' => 'Navegador de prueba',
        ]
    );

    $auditoria = app(
        RegistrarAuditoria::class
    )->registrar(
        accion: 'usuario.rol_asignado',
        modulo: 'usuarios',
        descripcion: 'Se cambió el rol del usuario.',
        actor: $actor,
        modelo: $afectado,
        usuarioAfectado: $afectado,
        valoresAnteriores: [
            'rol' => 'empleado',
        ],
        valoresNuevos: [
            'rol' => 'supervisor',
        ],
        metadatos: [
            'origen' => 'prueba',
        ],
        motivo: 'Cambio de responsabilidades.',
        request: $request
    );

    expect($auditoria->usuario_id)
        ->toBe($actor->id)
        ->and($auditoria->usuario_afectado_id)
        ->toBe($afectado->id)
        ->and($auditoria->accion)
        ->toBe('usuario.rol_asignado')
        ->and($auditoria->modulo)
        ->toBe('usuarios')
        ->and($auditoria->modelo_tipo)
        ->toBe(User::class)
        ->and((int) $auditoria->modelo_id)
        ->toBe($afectado->id)
        ->and($auditoria->valores_anteriores)
        ->toBe([
            'rol' => 'empleado',
        ])
        ->and($auditoria->valores_nuevos)
        ->toBe([
            'rol' => 'supervisor',
        ])
        ->and($auditoria->motivo)
        ->toBe('Cambio de responsabilidades.')
        ->and($auditoria->direccion_ip)
        ->toBe('192.168.1.50')
        ->and($auditoria->metodo_http)
        ->toBe('PUT')
        ->and($auditoria->resultado)
        ->toBe('exitoso');
});

test('audit service removes sensitive fields', function () {
    $actor = User::factory()->create();

    $auditoria = app(
        RegistrarAuditoria::class
    )->registrar(
        accion: 'usuario.actualizado',
        modulo: 'usuarios',
        descripcion: 'Usuario actualizado.',
        actor: $actor,
        modelo: $actor,
        valoresAnteriores: [
            'email' => 'anterior@cano.test',
            'password' => 'secreto-anterior',
            '_token' => 'token-anterior',
        ],
        valoresNuevos: [
            'email' => 'nuevo@cano.test',
            'password' => 'secreto-nuevo',
            '_token' => 'token-nuevo',
        ]
    );

    expect($auditoria->valores_anteriores)
        ->toBe([
            'email' => 'anterior@cano.test',
        ])
        ->and($auditoria->valores_nuevos)
        ->toBe([
            'email' => 'nuevo@cano.test',
        ]);
});

test('audit records cannot be updated', function () {
    $auditoria = Auditoria::query()->create([
        'accion' => 'prueba.creada',
        'modulo' => 'pruebas',
        'descripcion' => 'Registro de prueba.',
        'resultado' => 'exitoso',
    ]);

    expect(
        fn () => $auditoria->update([
            'descripcion' => 'Descripción modificada.',
        ])
    )->toThrow(LogicException::class);
});

test('audit records cannot be deleted', function () {
    $auditoria = Auditoria::query()->create([
        'accion' => 'prueba.creada',
        'modulo' => 'pruebas',
        'descripcion' => 'Registro de prueba.',
        'resultado' => 'exitoso',
    ]);

    expect(
        fn () => $auditoria->delete()
    )->toThrow(LogicException::class);
});

test('audit can record an action without authenticated user', function () {
    $auditoria = app(
        RegistrarAuditoria::class
    )->registrar(
        accion: 'autenticacion.inicio_fallido',
        modulo: 'autenticacion',
        descripcion: 'Intento de inicio de sesión fallido.',
        metadatos: [
            'correo_intentado' => 'desconocido@cano.test',
        ],
        resultado: 'fallido'
    );

    expect($auditoria->usuario_id)
        ->toBeNull()
        ->and($auditoria->accion)
        ->toBe('autenticacion.inicio_fallido')
        ->and($auditoria->resultado)
        ->toBe('fallido');
});
