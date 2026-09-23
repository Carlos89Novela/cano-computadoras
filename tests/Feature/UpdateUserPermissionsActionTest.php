<?php

use App\Actions\Usuarios\ActualizarPermisosUsuario;
use App\Models\Auditoria;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

function propietarioParaPermisos(): User
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    return $propietario;
}

test('owner can grant delegable direct permissions', function () {
    $propietario = propietarioParaPermisos();

    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $usuarioActualizado = app(
        ActualizarPermisosUsuario::class
    )->ejecutar(
        actor: $propietario,
        usuario: $supervisor,
        permisos: [
            'servicios.actualizar_precios',
            'inventario.ver',
        ],
        motivo: 'Responsable temporal de precios e inventario.'
    );

    expect(
        $usuarioActualizado->hasDirectPermission(
            'servicios.actualizar_precios'
        )
    )
        ->toBeTrue()
        ->and(
            $usuarioActualizado->hasDirectPermission(
                'inventario.ver'
            )
        )
        ->toBeTrue();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'usuario.permisos_actualizados'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->usuario_afectado_id)
        ->toBe($supervisor->id)
        ->and($auditoria->motivo)
        ->toBe(
            'Responsable temporal de precios e inventario.'
        )
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'permisos_directos' => [
                'inventario.ver',
                'servicios.actualizar_precios',
            ],
        ]);
});

test('owner can remove previously granted permissions', function () {
    $propietario = propietarioParaPermisos();

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    $empleado->givePermissionTo([
        'ordenes.asignar',
        'inventario.ver',
    ]);

    $usuarioActualizado = app(
        ActualizarPermisosUsuario::class
    )->ejecutar(
        actor: $propietario,
        usuario: $empleado,
        permisos: [
            'inventario.ver',
        ],
        motivo: 'Ya no realizará asignaciones.'
    );

    expect(
        $usuarioActualizado->hasDirectPermission(
            'inventario.ver'
        )
    )
        ->toBeTrue()
        ->and(
            $usuarioActualizado->hasDirectPermission(
                'ordenes.asignar'
            )
        )
        ->toBeFalse();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'usuario.permisos_actualizados'
        )
        ->firstOrFail();

    expect($auditoria->metadatos)
        ->toMatchArray([
            'permisos_retirados' => [
                'ordenes.asignar',
            ],
        ]);
});

test('empty selection removes all direct permissions', function () {
    $propietario = propietarioParaPermisos();

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    $empleado->givePermissionTo(
        'inventario.ver'
    );

    $usuarioActualizado = app(
        ActualizarPermisosUsuario::class
    )->ejecutar(
        actor: $propietario,
        usuario: $empleado,
        permisos: [],
        motivo: 'Se retiraron responsabilidades adicionales.'
    );

    expect(
        $usuarioActualizado
            ->getDirectPermissions()
            ->count()
    )->toBe(0);
});

test('reserved permission cannot be delegated', function () {
    $propietario = propietarioParaPermisos();

    $administrador = User::factory()->create([
        'es_propietario' => false,
    ]);

    $administrador->assignRole('administrador');

    expect(
        fn () => app(
            ActualizarPermisosUsuario::class
        )->ejecutar(
            actor: $propietario,
            usuario: $administrador,
            permisos: [
                'usuarios.asignar_roles',
            ],
            motivo: 'Intento de permiso reservado.'
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        $administrador
            ->fresh()
            ->can('usuarios.asignar_roles')
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

test('delegated administrator cannot assign permissions', function () {
    $administrador = User::factory()->create([
        'es_propietario' => false,
    ]);

    $administrador->assignRole('administrador');

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            ActualizarPermisosUsuario::class
        )->ejecutar(
            actor: $administrador,
            usuario: $empleado,
            permisos: [
                'ordenes.asignar',
            ],
            motivo: 'Cambio no autorizado.'
        )
    )->toThrow(
        AuthorizationException::class,
        'Solo el propietario puede asignar permisos.'
    );

    expect(
        $empleado
            ->fresh()
            ->getDirectPermissions()
            ->count()
    )->toBe(0);
});

test('owner cannot modify own direct permissions', function () {
    $propietario = propietarioParaPermisos();

    expect(
        fn () => app(
            ActualizarPermisosUsuario::class
        )->ejecutar(
            actor: $propietario,
            usuario: $propietario,
            permisos: [
                'inventario.ver',
            ],
            motivo: 'Intento no permitido.'
        )
    )->toThrow(
        AuthorizationException::class,
        'El propietario no puede modificar sus propios permisos.'
    );
});

test('permission change requires a reason', function () {
    $propietario = propietarioParaPermisos();

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            ActualizarPermisosUsuario::class
        )->ejecutar(
            actor: $propietario,
            usuario: $empleado,
            permisos: [
                'inventario.ver',
            ],
            motivo: '   '
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        $empleado
            ->fresh()
            ->getDirectPermissions()
            ->count()
    )->toBe(0);
});
