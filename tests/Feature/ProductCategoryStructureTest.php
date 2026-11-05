<?php

use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('product category table has required columns', function () {
    expect(
        Schema::hasTable(
            'categorias_producto'
        )
    )->toBeTrue();

    expect(
        Schema::hasColumns(
            'categorias_producto',
            [
                'id',
                'empresa_id',
                'nombre',
                'descripcion',
                'activo',
                'creado_por_id',
                'actualizado_por_id',
                'desactivado_por_id',
                'desactivado_at',
                'motivo_desactivacion',
                'created_at',
                'updated_at',
            ]
        )
    )->toBeTrue();
});

test('company can have multiple product categories', function () {
    $propietario = User::factory()->create();

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento',
        'descripcion' => 'Discos duros y unidades de estado sólido.',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Memoria RAM',
        'descripcion' => 'Módulos de memoria para equipos.',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        $empresa
            ->categoriasProducto()
            ->count()
    )->toBe(2);

    expect(
        $empresa
            ->categoriasProducto()
            ->where('activo', true)
            ->count()
    )->toBe(2);
});

test('category name must be unique inside same company', function () {
    $propietario = User::factory()->create();

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => CategoriaProducto::query()
            ->create([
                'empresa_id' => $empresa->id,
                'nombre' => 'Almacenamiento',
                'activo' => true,
                'creado_por_id' => $propietario->id,
            ])
    )->toThrow(QueryException::class);
});

test('same category name can exist in different companies', function () {
    $propietario = User::factory()->create();

    $empresaUno = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $empresaDos = Empresa::query()->create([
        'nombre' => 'Empresa Secundaria',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    CategoriaProducto::query()->create([
        'empresa_id' => $empresaUno->id,
        'nombre' => 'Almacenamiento',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    CategoriaProducto::query()->create([
        'empresa_id' => $empresaDos->id,
        'nombre' => 'Almacenamiento',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        CategoriaProducto::query()->count()
    )->toBe(2);
});

test('product category casts active state and deactivation date', function () {
    $propietario = User::factory()->create();

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $categoria = CategoriaProducto::query()
        ->create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Consumibles',
            'activo' => false,
            'creado_por_id' => $propietario->id,
            'desactivado_por_id' => $propietario->id,
            'desactivado_at' => now(),
            'motivo_desactivacion' => 'Categoría temporalmente deshabilitada.',
        ]);

    expect($categoria->activo)
        ->toBeFalse()
        ->and($categoria->desactivado_at)
        ->not->toBeNull()
        ->and($categoria->empresa->id)
        ->toBe($empresa->id)
        ->and($categoria->creadoPor?->id)
        ->toBe($propietario->id)
        ->and($categoria->desactivadoPor?->id)
        ->toBe($propietario->id);
});
