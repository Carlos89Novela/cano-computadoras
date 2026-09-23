<?php

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('multi branch inventory tables exist', function () {
    expect(Schema::hasTable('empresas'))
        ->toBeTrue()
        ->and(Schema::hasTable('sucursales'))
        ->toBeTrue()
        ->and(Schema::hasTable('almacenes'))
        ->toBeTrue()
        ->and(Schema::hasTable('sucursal_usuario'))
        ->toBeTrue();
});

test('company can have multiple branches and warehouses', function () {
    $propietario = User::factory()->create();

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $principal = Sucursal::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'MATRIZ',
        'nombre' => 'Sucursal principal',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $norte = Sucursal::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'NORTE',
        'nombre' => 'Sucursal Norte',
        'es_principal' => false,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    Almacen::query()->create([
        'empresa_id' => $empresa->id,
        'sucursal_id' => $principal->id,
        'codigo' => 'ALM-MATRIZ',
        'nombre' => 'Almacén principal',
        'tipo' => Almacen::TIPO_PRINCIPAL,
        'es_virtual' => false,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    Almacen::query()->create([
        'empresa_id' => $empresa->id,
        'sucursal_id' => $norte->id,
        'codigo' => 'ALM-NORTE',
        'nombre' => 'Almacén Norte',
        'tipo' => Almacen::TIPO_PRINCIPAL,
        'es_virtual' => false,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    Almacen::query()->create([
        'empresa_id' => $empresa->id,
        'sucursal_id' => null,
        'codigo' => 'TRANSITO',
        'nombre' => 'Almacén virtual de tránsito',
        'tipo' => Almacen::TIPO_TRANSITO,
        'es_virtual' => true,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect($empresa->sucursales()->count())
        ->toBe(2)
        ->and($empresa->almacenes()->count())
        ->toBe(3)
        ->and(
            $empresa->almacenes()
                ->where('tipo', Almacen::TIPO_TRANSITO)
                ->firstOrFail()
                ->esTransito()
        )
        ->toBeTrue();
});

test('user can be assigned as branch manager', function () {
    $propietario = User::factory()->create();
    $gerente = User::factory()->create();

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $sucursal = Sucursal::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'CENTRO',
        'nombre' => 'Sucursal Centro',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $sucursal->usuarios()->attach(
        $gerente->id,
        [
            'es_principal' => true,
            'es_gerente' => true,
            'activo' => true,
            'asignado_por_id' => $propietario->id,
            'asignado_at' => now(),
        ]
    );

    expect(
        $gerente->perteneceASucursal(
            $sucursal
        )
    )->toBeTrue();

    $asignacion = $sucursal
        ->usuarios()
        ->whereKey($gerente->id)
        ->firstOrFail();

    expect((bool) $asignacion->pivot->es_gerente)
        ->toBeTrue();
});
