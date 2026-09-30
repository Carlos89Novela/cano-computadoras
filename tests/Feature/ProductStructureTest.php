<?php

use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ---------------------------------------------------------
// PREPARACIÓN DE LA EMPRESA
// Crea una empresa activa para las pruebas del catálogo.
// ---------------------------------------------------------

function empresaParaProducto(
    User $usuario,
    string $nombre = 'Cano Computadoras'
): Empresa {
    return Empresa::query()->create([
        'nombre' => $nombre,
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);
}

// ---------------------------------------------------------
// PREPARACIÓN DE LA CATEGORÍA
// Crea una categoría asociada con la empresa indicada.
// ---------------------------------------------------------

function categoriaParaProducto(
    Empresa $empresa,
    User $usuario,
    string $nombre = 'Almacenamiento'
): CategoriaProducto {
    return CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => $nombre,
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);
}

// ---------------------------------------------------------
// PREPARACIÓN DEL PROVEEDOR
// Crea un proveedor principal opcional para el producto.
// ---------------------------------------------------------

function proveedorParaProducto(
    Empresa $empresa,
    User $usuario,
    string $codigo = 'PROV-001'
): Proveedor {
    return Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => $codigo,
        'nombre' => 'Proveedor de componentes',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);
}

// ---------------------------------------------------------
// COLUMNAS DE LA TABLA
// Confirma que productos contiene los campos del catálogo.
// También comprueba que no almacene existencias directamente.
// ---------------------------------------------------------

test('product table has required catalog columns', function () {
    expect(Schema::hasTable('productos'))
        ->toBeTrue();

    expect(
        Schema::hasColumns(
            'productos',
            [
                'id',
                'empresa_id',
                'categoria_producto_id',
                'proveedor_principal_id',
                'sku',
                'codigo_barras',
                'nombre',
                'descripcion',
                'marca',
                'modelo',
                'unidad_medida',
                'costo_referencia',
                'precio_base',
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

    expect(
        Schema::hasColumn(
            'productos',
            'stock_actual'
        )
    )->toBeFalse();

    expect(
        Schema::hasColumn(
            'productos',
            'stock_reservado'
        )
    )->toBeFalse();

    expect(
        Schema::hasColumn(
            'productos',
            'stock_minimo'
        )
    )->toBeFalse();
});

// ---------------------------------------------------------
// RELACIONES PRINCIPALES
// Comprueba empresa, categoría, proveedor y usuarios.
// ---------------------------------------------------------

test('product relationships work correctly', function () {
    $usuario = User::factory()->create();

    $empresa = empresaParaProducto(
        $usuario
    );

    $categoria = categoriaParaProducto(
        $empresa,
        $usuario
    );

    $proveedor = proveedorParaProducto(
        $empresa,
        $usuario
    );

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => $proveedor->id,
        'sku' => 'SSD-1TB-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD 1 TB',
        'descripcion' => 'Unidad de almacenamiento de estado sólido.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-1000',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 950.50,
        'precio_base' => 1299.99,
        'activo' => true,
        'creado_por_id' => $usuario->id,
        'actualizado_por_id' => $usuario->id,
    ]);

    expect($producto->empresa->id)
        ->toBe($empresa->id)
        ->and($producto->categoria->id)
        ->toBe($categoria->id)
        ->and($producto->proveedorPrincipal?->id)
        ->toBe($proveedor->id)
        ->and($producto->creadoPor?->id)
        ->toBe($usuario->id)
        ->and($producto->actualizadoPor?->id)
        ->toBe($usuario->id);

    expect(
        $empresa->productos()->count()
    )->toBe(1);

    expect(
        $categoria->productos()->count()
    )->toBe(1);

    expect(
        $proveedor
            ->productosPrincipales()
            ->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// PROVEEDOR OPCIONAL
// Comprueba que un producto pueda existir sin proveedor.
// ---------------------------------------------------------

test('product can exist without main supplier', function () {
    $usuario = User::factory()->create();

    $empresa = empresaParaProducto(
        $usuario
    );

    $categoria = categoriaParaProducto(
        $empresa,
        $usuario
    );

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => null,
        'sku' => 'RAM-16GB-001',
        'nombre' => 'Memoria RAM 16 GB',
        'unidad_medida' => 'pieza',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    expect($producto->proveedor_principal_id)
        ->toBeNull()
        ->and($producto->proveedorPrincipal)
        ->toBeNull();
});

// ---------------------------------------------------------
// SKU ÚNICO POR EMPRESA
// Impide repetir el identificador interno en la empresa.
// ---------------------------------------------------------

test('product sku must be unique inside company', function () {
    $usuario = User::factory()->create();

    $empresa = empresaParaProducto(
        $usuario
    );

    $categoria = categoriaParaProducto(
        $empresa,
        $usuario
    );

    Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'sku' => 'SSD-001',
        'nombre' => 'Primer SSD',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    expect(
        fn () => Producto::query()->create([
            'empresa_id' => $empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'SSD-001',
            'nombre' => 'Segundo SSD',
            'activo' => true,
            'creado_por_id' => $usuario->id,
        ])
    )->toThrow(QueryException::class);
});

// ---------------------------------------------------------
// CÓDIGO DE BARRAS ÚNICO POR EMPRESA
// Evita registrar dos productos con el mismo código.
// ---------------------------------------------------------

test('barcode must be unique inside company', function () {
    $usuario = User::factory()->create();

    $empresa = empresaParaProducto(
        $usuario
    );

    $categoria = categoriaParaProducto(
        $empresa,
        $usuario
    );

    Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'sku' => 'PROD-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Primer producto',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    expect(
        fn () => Producto::query()->create([
            'empresa_id' => $empresa->id,
            'categoria_producto_id' => $categoria->id,
            'sku' => 'PROD-002',
            'codigo_barras' => '7501234567890',
            'nombre' => 'Segundo producto',
            'activo' => true,
            'creado_por_id' => $usuario->id,
        ])
    )->toThrow(QueryException::class);
});

// ---------------------------------------------------------
// IDENTIFICADORES ENTRE EMPRESAS
// Permite reutilizar SKU y código de barras cuando los
// productos pertenecen a empresas diferentes.
// ---------------------------------------------------------

test('sku and barcode can repeat in different companies', function () {
    $usuario = User::factory()->create();

    $empresaUno = empresaParaProducto(
        $usuario
    );

    $empresaDos = empresaParaProducto(
        $usuario,
        'Empresa secundaria'
    );

    $categoriaUno = categoriaParaProducto(
        $empresaUno,
        $usuario
    );

    $categoriaDos = categoriaParaProducto(
        $empresaDos,
        $usuario
    );

    Producto::query()->create([
        'empresa_id' => $empresaUno->id,
        'categoria_producto_id' => $categoriaUno->id,
        'sku' => 'PROD-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Producto empresa uno',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    Producto::query()->create([
        'empresa_id' => $empresaDos->id,
        'categoria_producto_id' => $categoriaDos->id,
        'sku' => 'PROD-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Producto empresa dos',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    expect(
        Producto::query()->count()
    )->toBe(2);
});

// ---------------------------------------------------------
// CASTS DEL MODELO
// Confirma precios decimales, estado booleano y fecha de
// desactivación convertida correctamente por Eloquent.
// ---------------------------------------------------------

test('product casts prices status and deactivation date', function () {
    $usuario = User::factory()->create();

    $empresa = empresaParaProducto(
        $usuario
    );

    $categoria = categoriaParaProducto(
        $empresa,
        $usuario
    );

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'sku' => 'PROD-INACTIVO',
        'nombre' => 'Producto inactivo',
        'costo_referencia' => 100.50,
        'precio_base' => 149.99,
        'activo' => false,
        'creado_por_id' => $usuario->id,
        'desactivado_por_id' => $usuario->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Producto temporalmente fuera del catálogo.',
    ]);

    expect($producto->costo_referencia)
        ->toBe('100.50')
        ->and($producto->precio_base)
        ->toBe('149.99')
        ->and($producto->activo)
        ->toBeFalse()
        ->and($producto->desactivado_at)
        ->not->toBeNull()
        ->and($producto->desactivadoPor?->id)
        ->toBe($usuario->id);
});
