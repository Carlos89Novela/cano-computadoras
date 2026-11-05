<?php

use App\Models\Almacen;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

function propietarioParaSucursalHttp(): User
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    return $propietario;
}

function empresaParaSucursalHttp(
    User $propietario
): Empresa {
    return Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);
}

test('owner can view company branches', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $this
        ->actingAs($propietario)
        ->get(
            route(
                'admin.empresas.sucursales.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertOk()
        ->assertSee('Sucursales de')
        ->assertSee('Cano Computadoras')
        ->assertSee('Abrir nueva sucursal');
});

test('owner can view branch opening form', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $gerente = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $gerente->assignRole('supervisor');

    $this
        ->actingAs($propietario)
        ->get(
            route(
                'admin.empresas.sucursales.create',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertOk()
        ->assertSee('Abrir nueva sucursal')
        ->assertSee($empresa->nombre)
        ->assertSee($gerente->name)
        ->assertSee('Primera sucursal');
});

test('owner can open first branch through endpoint', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $gerente = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $gerente->assignRole('supervisor');

    $response = $this
        ->actingAs($propietario)
        ->post(
            route(
                'admin.empresas.sucursales.store',
                [
                    'empresa' => $empresa->id,
                ]
            ),
            [
                'gerente_id' => $gerente->id,
                'codigo' => 'MATRIZ',
                'nombre' => 'Sucursal Principal',
                'telefono' => '6621234567',
                'correo' => 'matriz@cano.test',
                'direccion' => 'Dirección principal',
                'ciudad' => 'Hermosillo',
                'estado' => 'Sonora',
                'codigo_postal' => '83000',
                'es_principal' => true,
                'motivo' => 'Apertura inicial de Cano Computadoras.',
            ]
        );

    $response
        ->assertRedirect(
            route(
                'admin.empresas.sucursales.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertSessionHas(
            'success',
            'La sucursal y su almacén principal fueron creados correctamente.'
        );

    $sucursal = Sucursal::query()
        ->where('empresa_id', $empresa->id)
        ->where('codigo', 'MATRIZ')
        ->firstOrFail();

    expect($sucursal->nombre)
        ->toBe('Sucursal Principal')
        ->and($sucursal->es_principal)
        ->toBeTrue()
        ->and($sucursal->activo)
        ->toBeTrue();

    $this->assertDatabaseHas(
        'almacenes',
        [
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'codigo' => 'ALM-MATRIZ',
            'tipo' => Almacen::TIPO_PRINCIPAL,
            'es_virtual' => false,
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
            'activo' => true,
        ]
    );

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
        'auditorias',
        [
            'usuario_id' => $propietario->id,
            'usuario_afectado_id' => $gerente->id,
            'accion' => 'sucursal.abierta',
            'modulo' => 'sucursales',
            'motivo' => 'Apertura inicial de Cano Computadoras.',
        ]
    );
});

test('second branch does not create another transit warehouse', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $gerenteUno = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $gerenteUno->assignRole('supervisor');

    $gerenteDos = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $gerenteDos->assignRole('supervisor');

    $ruta = route(
        'admin.empresas.sucursales.store',
        [
            'empresa' => $empresa->id,
        ]
    );

    $this
        ->actingAs($propietario)
        ->post(
            $ruta,
            [
                'gerente_id' => $gerenteUno->id,
                'codigo' => 'MATRIZ',
                'nombre' => 'Sucursal Principal',
                'es_principal' => true,
                'motivo' => 'Apertura inicial.',
            ]
        )
        ->assertRedirect();

    $this
        ->actingAs($propietario)
        ->post(
            $ruta,
            [
                'gerente_id' => $gerenteDos->id,
                'codigo' => 'NORTE',
                'nombre' => 'Sucursal Norte',
                'es_principal' => false,
                'motivo' => 'Expansión al norte.',
            ]
        )
        ->assertRedirect();

    expect(
        Sucursal::query()
            ->where('empresa_id', $empresa->id)
            ->count()
    )->toBe(2);

    expect(
        Sucursal::query()
            ->where('empresa_id', $empresa->id)
            ->where('es_principal', true)
            ->count()
    )->toBe(1);

    expect(
        Almacen::query()
            ->where('empresa_id', $empresa->id)
            ->where(
                'tipo',
                Almacen::TIPO_TRANSITO
            )
            ->count()
    )->toBe(1);
});

test('branch opening requires a valid manager', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    $detalle = route(
        'admin.empresas.sucursales.create',
        [
            'empresa' => $empresa->id,
        ]
    );

    $this
        ->actingAs($propietario)
        ->from($detalle)
        ->post(
            route(
                'admin.empresas.sucursales.store',
                [
                    'empresa' => $empresa->id,
                ]
            ),
            [
                'gerente_id' => $empleado->id,
                'codigo' => 'CENTRO',
                'nombre' => 'Sucursal Centro',
                'es_principal' => true,
                'motivo' => 'Apertura de sucursal.',
            ]
        )
        ->assertRedirect($detalle)
        ->assertSessionHasErrors([
            'gerente_id',
        ]);

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

test('branch opening requires a reason', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $gerente = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $gerente->assignRole('supervisor');

    $detalle = route(
        'admin.empresas.sucursales.create',
        [
            'empresa' => $empresa->id,
        ]
    );

    $this
        ->actingAs($propietario)
        ->from($detalle)
        ->post(
            route(
                'admin.empresas.sucursales.store',
                [
                    'empresa' => $empresa->id,
                ]
            ),
            [
                'gerente_id' => $gerente->id,
                'codigo' => 'CENTRO',
                'nombre' => 'Sucursal Centro',
                'es_principal' => true,
                'motivo' => '',
            ]
        )
        ->assertRedirect($detalle)
        ->assertSessionHasErrors([
            'motivo',
        ]);

    expect(Sucursal::query()->count())
        ->toBe(0);
});

test('branch code must be unique inside company through endpoint', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $gerenteUno = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $gerenteUno->assignRole('supervisor');

    $gerenteDos = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $gerenteDos->assignRole('supervisor');

    $ruta = route(
        'admin.empresas.sucursales.store',
        [
            'empresa' => $empresa->id,
        ]
    );

    $this
        ->actingAs($propietario)
        ->post(
            $ruta,
            [
                'gerente_id' => $gerenteUno->id,
                'codigo' => 'CENTRO',
                'nombre' => 'Sucursal Centro',
                'es_principal' => true,
                'motivo' => 'Primera apertura.',
            ]
        )
        ->assertRedirect();

    $detalle = route(
        'admin.empresas.sucursales.create',
        [
            'empresa' => $empresa->id,
        ]
    );

    $this
        ->actingAs($propietario)
        ->from($detalle)
        ->post(
            $ruta,
            [
                'gerente_id' => $gerenteDos->id,
                'codigo' => 'centro',
                'nombre' => 'Sucursal duplicada',
                'es_principal' => false,
                'motivo' => 'Código duplicado.',
            ]
        )
        ->assertRedirect($detalle)
        ->assertSessionHasErrors([
            'codigo',
        ]);

    expect(Sucursal::query()->count())
        ->toBe(1);
});

test('delegated administrator cannot access branch management', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $administrador = User::factory()->create([
        'es_propietario' => false,
        'email_verified_at' => now(),
    ]);

    $administrador->assignRole(
        'administrador'
    );

    $this
        ->actingAs($administrador)
        ->get(
            route(
                'admin.empresas.sucursales.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertForbidden();

    $this
        ->actingAs($administrador)
        ->get(
            route(
                'admin.empresas.sucursales.create',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertForbidden();

    $this
        ->actingAs($administrador)
        ->post(
            route(
                'admin.empresas.sucursales.store',
                [
                    'empresa' => $empresa->id,
                ]
            ),
            [
                'gerente_id' => $administrador->id,
                'codigo' => 'NORTE',
                'nombre' => 'Sucursal Norte',
                'es_principal' => true,
                'motivo' => 'Intento no autorizado.',
            ]
        )
        ->assertForbidden();

    expect(Sucursal::query()->count())
        ->toBe(0)
        ->and(Almacen::query()->count())
        ->toBe(0);

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'sucursal.abierta'
            )
            ->count()
    )->toBe(0);
});

test('supervisor cannot access branch management', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $this
        ->actingAs($supervisor)
        ->get(
            route(
                'admin.empresas.sucursales.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertForbidden();

    $this
        ->actingAs($supervisor)
        ->get(
            route(
                'admin.empresas.sucursales.create',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertForbidden();

    expect(Sucursal::query()->count())
        ->toBe(0);
});

test('guest cannot access branch management', function () {
    $propietario = propietarioParaSucursalHttp();

    $empresa = empresaParaSucursalHttp(
        $propietario
    );

    $this
        ->get(
            route(
                'admin.empresas.sucursales.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertRedirect('/login');

    $this
        ->get(
            route(
                'admin.empresas.sucursales.create',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertRedirect('/login');

    $this
        ->post(
            route(
                'admin.empresas.sucursales.store',
                [
                    'empresa' => $empresa->id,
                ]
            ),
            [
                'gerente_id' => $propietario->id,
                'codigo' => 'CENTRO',
                'nombre' => 'Sucursal Centro',
                'es_principal' => true,
                'motivo' => 'Intento sin autenticación.',
            ]
        )
        ->assertRedirect('/login');

    expect(Sucursal::query()->count())
        ->toBe(0)
        ->and(Almacen::query()->count())
        ->toBe(0);
});
