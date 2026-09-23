<?php

use App\Actions\Sucursales\AbrirSucursal;
use App\Models\Almacen;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Sucursal;
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

function propietarioParaSucursal(): User
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    return $propietario;
}

function empresaParaSucursal(
    User $propietario
): Empresa {
    return Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);
}

test('owner can open first branch with warehouse and manager', function () {
    $propietario = propietarioParaSucursal();
    $empresa = empresaParaSucursal($propietario);

    $gerente = User::factory()->create();
    $gerente->assignRole('supervisor');

    $sucursal = app(
        AbrirSucursal::class
    )->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        gerente: $gerente,
        datos: [
            'codigo' => ' matriz ',
            'nombre' => 'Sucursal Principal',
            'telefono' => '6621234567',
            'correo' => 'matriz@cano.test',
            'direccion' => 'Dirección principal',
            'ciudad' => 'Hermosillo',
            'estado' => 'Sonora',
            'codigo_postal' => '83000',
            'motivo' => 'Apertura inicial de operaciones.',
        ]
    );

    expect($sucursal->codigo)
        ->toBe('MATRIZ')
        ->and($sucursal->es_principal)
        ->toBeTrue()
        ->and($sucursal->activo)
        ->toBeTrue()
        ->and($sucursal->almacenes)
        ->toHaveCount(1)
        ->and($sucursal->usuarios)
        ->toHaveCount(1);

    $almacenPrincipal = $sucursal
        ->almacenes
        ->firstOrFail();

    expect($almacenPrincipal->tipo)
        ->toBe(Almacen::TIPO_PRINCIPAL)
        ->and($almacenPrincipal->es_virtual)
        ->toBeFalse()
        ->and($almacenPrincipal->codigo)
        ->toBe('ALM-MATRIZ');

    $this->assertDatabaseHas(
        'sucursal_usuario',
        [
            'sucursal_id' => $sucursal->id,
            'user_id' => $gerente->id,
            'es_principal' => true,
            'es_gerente' => true,
            'activo' => true,
        ]
    );

    $this->assertDatabaseHas(
        'almacenes',
        [
            'empresa_id' => $empresa->id,
            'sucursal_id' => null,
            'codigo' => 'TRANSITO',
            'tipo' => Almacen::TIPO_TRANSITO,
            'es_virtual' => true,
        ]
    );

    $this->assertDatabaseHas(
        'auditorias',
        [
            'usuario_id' => $propietario->id,
            'usuario_afectado_id' => $gerente->id,
            'accion' => 'sucursal.abierta',
            'modulo' => 'sucursales',
            'motivo' => 'Apertura inicial de operaciones.',
        ]
    );
});

test('second branch reuses company transit warehouse', function () {
    $propietario = propietarioParaSucursal();
    $empresa = empresaParaSucursal($propietario);

    $gerenteUno = User::factory()->create();
    $gerenteUno->assignRole('supervisor');

    $gerenteDos = User::factory()->create();
    $gerenteDos->assignRole('supervisor');

    $accion = app(AbrirSucursal::class);

    $accion->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        gerente: $gerenteUno,
        datos: [
            'codigo' => 'MATRIZ',
            'nombre' => 'Sucursal Principal',
            'motivo' => 'Apertura inicial.',
        ]
    );

    $segunda = $accion->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        gerente: $gerenteDos,
        datos: [
            'codigo' => 'NORTE',
            'nombre' => 'Sucursal Norte',
            'motivo' => 'Expansión de operaciones.',
        ]
    );

    expect($segunda->es_principal)
        ->toBeFalse();

    expect(
        Almacen::query()
            ->where('empresa_id', $empresa->id)
            ->where(
                'tipo',
                Almacen::TIPO_TRANSITO
            )
            ->count()
    )->toBe(1);

    expect(
        Sucursal::query()
            ->where('empresa_id', $empresa->id)
            ->count()
    )->toBe(2);
});

test('delegated administrator cannot open branch', function () {
    $propietario = propietarioParaSucursal();
    $empresa = empresaParaSucursal($propietario);

    $administrador = User::factory()->create([
        'es_propietario' => false,
    ]);

    $administrador->assignRole('administrador');

    $gerente = User::factory()->create();
    $gerente->assignRole('supervisor');

    expect(
        fn () => app(
            AbrirSucursal::class
        )->ejecutar(
            empresa: $empresa,
            actor: $administrador,
            gerente: $gerente,
            datos: [
                'codigo' => 'SUR',
                'nombre' => 'Sucursal Sur',
                'motivo' => 'Cambio no autorizado.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'Solo el propietario puede abrir sucursales.'
    );

    expect(Sucursal::query()->count())
        ->toBe(0);

    expect(Almacen::query()->count())
        ->toBe(0);

    expect(
        Auditoria::query()
            ->where('accion', 'sucursal.abierta')
            ->count()
    )->toBe(0);
});

test('branch manager must have appropriate role', function () {
    $propietario = propietarioParaSucursal();
    $empresa = empresaParaSucursal($propietario);

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            AbrirSucursal::class
        )->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            gerente: $empleado,
            datos: [
                'codigo' => 'CENTRO',
                'nombre' => 'Sucursal Centro',
                'motivo' => 'Apertura de sucursal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Sucursal::query()->count())
        ->toBe(0);

    expect(Almacen::query()->count())
        ->toBe(0);
});

test('branch code must be unique within company', function () {
    $propietario = propietarioParaSucursal();
    $empresa = empresaParaSucursal($propietario);

    $gerenteUno = User::factory()->create();
    $gerenteUno->assignRole('supervisor');

    $gerenteDos = User::factory()->create();
    $gerenteDos->assignRole('supervisor');

    $accion = app(AbrirSucursal::class);

    $accion->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        gerente: $gerenteUno,
        datos: [
            'codigo' => 'CENTRO',
            'nombre' => 'Sucursal Centro',
            'motivo' => 'Primera apertura.',
        ]
    );

    expect(
        fn () => $accion->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            gerente: $gerenteDos,
            datos: [
                'codigo' => 'centro',
                'nombre' => 'Otra sucursal',
                'motivo' => 'Código duplicado.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Sucursal::query()->count())
        ->toBe(1);

    expect(
        Almacen::query()
            ->where(
                'tipo',
                Almacen::TIPO_TRANSITO
            )
            ->count()
    )->toBe(1);
});

test('inactive company cannot receive new branch', function () {
    $propietario = propietarioParaSucursal();

    $empresa = Empresa::query()->create([
        'nombre' => 'Empresa inactiva',
        'activo' => false,
        'creado_por_id' => $propietario->id,
    ]);

    $gerente = User::factory()->create();
    $gerente->assignRole('supervisor');

    expect(
        fn () => app(
            AbrirSucursal::class
        )->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            gerente: $gerente,
            datos: [
                'codigo' => 'INACTIVA',
                'nombre' => 'Sucursal no válida',
                'motivo' => 'Intento inválido.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Sucursal::query()->count())
        ->toBe(0);

    expect(Almacen::query()->count())
        ->toBe(0);
});

test('branch opening requires an audit reason', function () {
    $propietario = propietarioParaSucursal();
    $empresa = empresaParaSucursal($propietario);

    $gerente = User::factory()->create();
    $gerente->assignRole('supervisor');

    expect(
        fn () => app(
            AbrirSucursal::class
        )->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            gerente: $gerente,
            datos: [
                'codigo' => 'NORTE',
                'nombre' => 'Sucursal Norte',
                'motivo' => '   ',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Auditoria::query()->count())
        ->toBe(0);

    expect(Sucursal::query()->count())
        ->toBe(0);

    expect(Almacen::query()->count())
        ->toBe(0);
});
