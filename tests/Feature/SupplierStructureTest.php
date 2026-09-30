<?php

use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pruebas estructurales y de base de datos para el modelo Proveedor.
 *
 * Valida:
 * 1. La existencia de la tabla 'proveedores' y todas sus columnas requeridas.
 * 2. La relación de pertenencia uno a muchos (Empresa -> Proveedores).
 * 3. Las restricciones de unicidad de código por empresa a nivel de base de datos.
 * 4. La independencia de códigos entre diferentes empresas.
 * 5. El casteo de tipos de atributos (activo como booleano, desactivado_at como fecha) y relaciones.
 */
uses(RefreshDatabase::class);

/**
 * Función auxiliar para crear una empresa activa con un usuario propietario.
 */
function crearEmpresaParaProveedor(
    User $usuario,
    string $nombre = 'Cano Computadoras'
): Empresa {
    return Empresa::query()->create([
        'nombre' => $nombre,
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);
}

// 1. Verifica que la migración haya creado la tabla y todas las columnas esperadas
test('supplier table has required columns', function () {
    expect(Schema::hasTable('proveedores'))
        ->toBeTrue();

    expect(
        Schema::hasColumns(
            'proveedores',
            [
                'id',
                'empresa_id',
                'codigo',
                'nombre',
                'razon_social',
                'rfc',
                'contacto',
                'telefono',
                'correo',
                'direccion',
                'notas',
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

// 2. Comprueba que una empresa pueda asociar múltiples proveedores sin conflicto
test('company can have multiple suppliers', function () {
    $usuario = User::factory()->create();

    $empresa = crearEmpresaParaProveedor(
        $usuario
    );

    Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor Norte',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-002',
        'nombre' => 'Proveedor Centro',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    expect(
        $empresa->proveedores()->count()
    )->toBe(2);
});

// 3. Comprueba que la base de datos rechace códigos duplicados dentro de la misma empresa (Unique Constraint)
test('supplier code must be unique inside company', function () {
    $usuario = User::factory()->create();

    $empresa = crearEmpresaParaProveedor(
        $usuario
    );

    Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Primer proveedor',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    expect(
        fn () => Proveedor::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 'PROV-001',
            'nombre' => 'Proveedor duplicado',
            'activo' => true,
            'creado_por_id' => $usuario->id,
        ])
    )->toThrow(QueryException::class);
});

// 4. Comprueba el aislamiento multi-empresa: el mismo código PROV-001 puede coexistir en dos empresas diferentes
test('same supplier code can exist in different companies', function () {
    $usuario = User::factory()->create();

    $empresaUno = crearEmpresaParaProveedor(
        $usuario
    );

    $empresaDos = crearEmpresaParaProveedor(
        $usuario,
        'Empresa secundaria'
    );

    Proveedor::query()->create([
        'empresa_id' => $empresaUno->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor empresa uno',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    Proveedor::query()->create([
        'empresa_id' => $empresaDos->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor empresa dos',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    expect(
        Proveedor::query()->count()
    )->toBe(2);
});

// 5. Valida el casteo de tipos boolean/datetime y las relaciones Eloquent del modelo
test('supplier relationships and casts work correctly', function () {
    $usuario = User::factory()->create();

    $empresa = crearEmpresaParaProveedor(
        $usuario
    );

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor de prueba',
        'activo' => false,
        'creado_por_id' => $usuario->id,
        'actualizado_por_id' => $usuario->id,
        'desactivado_por_id' => $usuario->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Proveedor temporalmente inactivo.',
    ]);

    expect($proveedor->activo)
        ->toBeFalse()
        ->and($proveedor->desactivado_at)
        ->not->toBeNull()
        ->and($proveedor->empresa->id)
        ->toBe($empresa->id)
        ->and($proveedor->creadoPor?->id)
        ->toBe($usuario->id)
        ->and($proveedor->actualizadoPor?->id)
        ->toBe($usuario->id)
        ->and($proveedor->desactivadoPor?->id)
        ->toBe($usuario->id);
});
