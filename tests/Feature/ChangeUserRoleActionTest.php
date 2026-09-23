<?php

use App\Actions\Usuarios\CambiarRolUsuario;
use App\Models\Auditoria;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

function propietarioParaCambiarRol(): User
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    return $propietario;
}

test('owner can promote employee to supervisor', function () {
    $propietario = propietarioParaCambiarRol();

    $empleado = User::factory()->create([
        'es_propietario' => false,
    ]);

    $empleado->assignRole('empleado');

    $request = Request::create(
        '/admin/usuarios/'.$empleado->id.'/rol',
        'PATCH',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '192.168.1.80',
            'HTTP_USER_AGENT' => 'Navegador de prueba',
        ]
    );

    $usuarioActualizado = app(
        CambiarRolUsuario::class
    )->ejecutar(
        $propietario,
        $empleado,
        'supervisor',
        'Asumirá la coordinación del área técnica.',
        $request
    );

    expect($usuarioActualizado->hasRole('supervisor'))
        ->toBeTrue()
        ->and($usuarioActualizado->hasRole('empleado'))
        ->toBeFalse();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'usuario.rol_actualizado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->usuario_afectado_id)
        ->toBe($empleado->id)
        ->and($auditoria->valores_anteriores)
        ->toMatchArray([
            'roles' => [
                'empleado',
            ],
        ])
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'roles' => [
                'supervisor',
            ],
        ])
        ->and($auditoria->motivo)
        ->toBe(
            'Asumirá la coordinación del área técnica.'
        )
        ->and($auditoria->direccion_ip)
        ->toBe('192.168.1.80');
});

test('role change preserves direct permissions', function () {
    $propietario = propietarioParaCambiarRol();

    $empleado = User::factory()->create();

    $empleado->assignRole('empleado');

    $empleado->givePermissionTo(
        'ordenes.asignar'
    );

    $usuarioActualizado = app(
        CambiarRolUsuario::class
    )->ejecutar(
        $propietario,
        $empleado,
        'supervisor',
        'Promoción con responsabilidad operativa.'
    );

    expect(
        $usuarioActualizado->hasDirectPermission(
            'ordenes.asignar'
        )
    )->toBeTrue();
});

test('delegated administrator cannot change roles', function () {
    $administrador = User::factory()->create([
        'es_propietario' => false,
    ]);

    $administrador->assignRole(
        'administrador'
    );

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CambiarRolUsuario::class
        )->ejecutar(
            $administrador,
            $empleado,
            'supervisor',
            'Cambio no autorizado.'
        )
    )->toThrow(
        AuthorizationException::class,
        'Solo el propietario puede cambiar roles.'
    );

    expect($empleado->fresh()->hasRole('empleado'))
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'usuario.rol_actualizado'
            )
            ->count()
    )->toBe(0);
});

test('owner cannot change own role', function () {
    $propietario = propietarioParaCambiarRol();

    expect(
        fn () => app(
            CambiarRolUsuario::class
        )->ejecutar(
            $propietario,
            $propietario,
            'cliente',
            'Intento de degradación.'
        )
    )->toThrow(
        AuthorizationException::class,
        'El propietario no puede cambiar su propio rol.'
    );

    expect(
        $propietario->fresh()
            ->hasRole('administrador')
    )->toBeTrue();
});

test('another owner account cannot be modified', function () {
    $propietario = propietarioParaCambiarRol();

    $otroPropietario = User::factory()->create([
        'es_propietario' => true,
    ]);

    $otroPropietario->assignRole(
        'administrador'
    );

    expect(
        fn () => app(
            CambiarRolUsuario::class
        )->ejecutar(
            $propietario,
            $otroPropietario,
            'supervisor',
            'Cambio no permitido.'
        )
    )->toThrow(
        AuthorizationException::class,
        'No se puede modificar el rol del propietario.'
    );
});

test('role change requires a reason', function () {
    $propietario = propietarioParaCambiarRol();

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CambiarRolUsuario::class
        )->ejecutar(
            $propietario,
            $empleado,
            'supervisor',
            '   '
        )
    )->toThrow(
        ValidationException::class
    );

    expect($empleado->fresh()->hasRole('empleado'))
        ->toBeTrue();
});

test('unrecognized role cannot be assigned', function () {
    $propietario = propietarioParaCambiarRol();

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CambiarRolUsuario::class
        )->ejecutar(
            $propietario,
            $empleado,
            'propietario',
            'Intento de asignar un rol reservado.'
        )
    )->toThrow(
        ValidationException::class
    );

    expect($empleado->fresh()->hasRole('empleado'))
        ->toBeTrue();
});
