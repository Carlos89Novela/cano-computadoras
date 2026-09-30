<?php

use App\Actions\Productos\ActualizarProducto;
use App\Models\Auditoria;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------
// PREPARACIÓN GENERAL
// Carga los roles y permisos antes de cada prueba.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Crea propietario, empresa, dos categorías, proveedor
// principal y producto inicial.
// ---------------------------------------------------------

/**
 * @return array{
 *     0: User,
 *     1: Empresa,
 *     2: CategoriaProducto,
 *     3: CategoriaProducto,
 *     4: Proveedor,
 *     5: Producto
 * }
 */
function prepararEntornoParaActualizarProducto(): array
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    $empresa = Empresa::query()->create([
        'nombre' => 'Cano Computadoras',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $categoriaInicial = CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $categoriaNueva = CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Componentes',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor principal',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoriaInicial->id,
        'proveedor_principal_id' => $proveedor->id,
        'sku' => 'SSD-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD',
        'descripcion' => 'Descripción inicial.',
        'marca' => 'Marca inicial',
        'modelo' => 'Modelo inicial',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    return [
        $propietario,
        $empresa,
        $categoriaInicial,
        $categoriaNueva,
        $proveedor,
        $producto,
    ];
}

// ---------------------------------------------------------
// ACTUALIZACIÓN COMPLETA
// Comprueba normalización, cambio de categoría, valores
// anteriores, valores nuevos y auditoría.
// ---------------------------------------------------------

test('authorized user can update product', function () {
    [
        $propietario,
        $empresa,
        $categoriaInicial,
        $categoriaNueva,
        $proveedor,
        $producto,
    ] = prepararEntornoParaActualizarProducto();

    $actualizado = app(
        ActualizarProducto::class
    )->ejecutar(
        empresa: $empresa,
        producto: $producto,
        categoria: $categoriaNueva,
        proveedor: $proveedor,
        actor: $propietario,
        datos: [
            'sku' => ' ssd-actualizado ',
            'codigo_barras' => ' 7509876543210 ',
            'nombre' => ' Unidad SSD actualizada ',
            'descripcion' => ' Nueva descripción. ',
            'marca' => ' Nueva marca ',
            'modelo' => ' Nuevo modelo ',
            'unidad_medida' => ' PIEZA ',
            'costo_referencia' => '1000.50',
            'precio_base' => '1499.99',
        ]
    );

    expect($actualizado->sku)
        ->toBe('SSD-ACTUALIZADO')
        ->and($actualizado->codigo_barras)
        ->toBe('7509876543210')
        ->and($actualizado->nombre)
        ->toBe('Unidad SSD actualizada')
        ->and($actualizado->descripcion)
        ->toBe('Nueva descripción.')
        ->and($actualizado->marca)
        ->toBe('Nueva marca')
        ->and($actualizado->modelo)
        ->toBe('Nuevo modelo')
        ->and($actualizado->unidad_medida)
        ->toBe('pieza')
        ->and($actualizado->costo_referencia)
        ->toBe('1000.50')
        ->and($actualizado->precio_base)
        ->toBe('1499.99')
        ->and($actualizado->categoria_producto_id)
        ->toBe($categoriaNueva->id)
        ->and($actualizado->actualizado_por_id)
        ->toBe($propietario->id)
        ->and($actualizado->activo)
        ->toBeTrue();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'producto.actualizado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_id)
        ->toBe($producto->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Producto::class)
        ->and($auditoria->valores_anteriores)
        ->toMatchArray([
            'categoria_producto_id' => $categoriaInicial->id,
            'sku' => 'SSD-001',
            'nombre' => 'Unidad SSD',
            'costo_referencia' => '900.00',
            'precio_base' => '1200.00',
        ])
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'categoria_producto_id' => $categoriaNueva->id,
            'sku' => 'SSD-ACTUALIZADO',
            'nombre' => 'Unidad SSD actualizada',
            'costo_referencia' => '1000.50',
            'precio_base' => '1499.99',
        ]);
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor puede actualizar productos si recibió
// productos.actualizar de forma individual.
// ---------------------------------------------------------

test('user with direct permission can update product', function () {
    [
        $propietario,
        $empresa,
        $categoriaInicial,
        $categoriaNueva,
        $proveedor,
        $producto,
    ] = prepararEntornoParaActualizarProducto();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'productos.actualizar'
    );

    $actualizado = app(
        ActualizarProducto::class
    )->ejecutar(
        empresa: $empresa,
        producto: $producto,
        categoria: $categoriaInicial,
        proveedor: $proveedor,
        actor: $supervisor,
        datos: [
            'sku' => 'SSD-001',
            'codigo_barras' => '7501234567890',
            'nombre' => 'Unidad SSD modificada',
            'descripcion' => 'Descripción inicial.',
            'marca' => 'Marca inicial',
            'modelo' => 'Modelo inicial',
            'unidad_medida' => 'pieza',
            'costo_referencia' => 900,
            'precio_base' => 1200,
        ]
    );

    expect($actualizado->nombre)
        ->toBe('Unidad SSD modificada')
        ->and($actualizado->actualizado_por_id)
        ->toBe($supervisor->id);
});

// ---------------------------------------------------------
// RETIRO DEL PROVEEDOR PRINCIPAL
// El proveedor es opcional y puede retirarse durante una
// actualización sin afectar el producto.
// ---------------------------------------------------------

test('main supplier can be removed during product update', function () {
    [
        $propietario,
        $empresa,
        $categoriaInicial,
        $categoriaNueva,
        $proveedor,
        $producto,
    ] = prepararEntornoParaActualizarProducto();

    $actualizado = app(
        ActualizarProducto::class
    )->ejecutar(
        empresa: $empresa,
        producto: $producto,
        categoria: $categoriaInicial,
        proveedor: null,
        actor: $propietario,
        datos: [
            'sku' => 'SSD-001',
            'codigo_barras' => '7501234567890',
            'nombre' => 'Unidad SSD',
            'descripcion' => 'Descripción inicial.',
            'marca' => 'Marca inicial',
            'modelo' => 'Modelo inicial',
            'unidad_medida' => 'pieza',
            'costo_referencia' => 900,
            'precio_base' => 1200,
        ]
    );

    expect($actualizado->proveedor_principal_id)
        ->toBeNull()
        ->and($actualizado->proveedorPrincipal)
        ->toBeNull();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'producto.actualizado'
        )
        ->firstOrFail();

    expect($auditoria->valores_anteriores)
        ->toMatchArray([
            'proveedor_principal_id' => $proveedor->id,
        ])
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'proveedor_principal_id' => null,
        ]);
});

// ---------------------------------------------------------
// SKU DUPLICADO
// Impide asignar al producto un SKU que ya utiliza otro
// producto de la misma empresa.
// ---------------------------------------------------------

test('updated product sku must be unique inside company', function () {
    [
        $propietario,
        $empresa,
        $categoriaInicial,
        $categoriaNueva,
        $proveedor,
        $producto,
    ] = prepararEntornoParaActualizarProducto();

    Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoriaInicial->id,
        'sku' => 'PROD-002',
        'nombre' => 'Segundo producto',
        'unidad_medida' => 'pieza',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            ActualizarProducto::class
        )->ejecutar(
            empresa: $empresa,
            producto: $producto,
            categoria: $categoriaInicial,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'prod-002',
                'codigo_barras' => '7501234567890',
                'nombre' => 'Unidad SSD',
                'descripcion' => 'Descripción inicial.',
                'marca' => 'Marca inicial',
                'modelo' => 'Modelo inicial',
                'unidad_medida' => 'pieza',
                'costo_referencia' => 900,
                'precio_base' => 1200,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($producto->fresh()?->sku)
        ->toBe('SSD-001');
});

// ---------------------------------------------------------
// CÓDIGO DE BARRAS DUPLICADO
// Impide utilizar un código de barras que ya pertenece a
// otro producto dentro de la misma empresa.
// ---------------------------------------------------------

test('updated product barcode must be unique inside company', function () {
    [
        $propietario,
        $empresa,
        $categoriaInicial,
        $categoriaNueva,
        $proveedor,
        $producto,
    ] = prepararEntornoParaActualizarProducto();

    Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoriaInicial->id,
        'sku' => 'PROD-002',
        'codigo_barras' => '7509876543210',
        'nombre' => 'Segundo producto',
        'unidad_medida' => 'pieza',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            ActualizarProducto::class
        )->ejecutar(
            empresa: $empresa,
            producto: $producto,
            categoria: $categoriaInicial,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'SSD-001',
                'codigo_barras' => '7509876543210',
                'nombre' => 'Unidad SSD',
                'descripcion' => 'Descripción inicial.',
                'marca' => 'Marca inicial',
                'modelo' => 'Modelo inicial',
                'unidad_medida' => 'pieza',
                'costo_referencia' => 900,
                'precio_base' => 1200,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($producto->fresh()?->codigo_barras)
        ->toBe('7501234567890');
});

// ---------------------------------------------------------
// ACTUALIZACIÓN SIN CAMBIOS
// Evita guardar y auditar una operación cuando todos los
// valores enviados coinciden con los actuales.
// ---------------------------------------------------------

test('product update requires an actual change', function () {
    [
        $propietario,
        $empresa,
        $categoriaInicial,
        $categoriaNueva,
        $proveedor,
        $producto,
    ] = prepararEntornoParaActualizarProducto();

    expect(
        fn () => app(
            ActualizarProducto::class
        )->ejecutar(
            empresa: $empresa,
            producto: $producto,
            categoria: $categoriaInicial,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'SSD-001',
                'codigo_barras' => '7501234567890',
                'nombre' => 'Unidad SSD',
                'descripcion' => 'Descripción inicial.',
                'marca' => 'Marca inicial',
                'modelo' => 'Modelo inicial',
                'unidad_medida' => 'pieza',
                'costo_referencia' => 900,
                'precio_base' => 1200,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'producto.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// UNIDAD DE MEDIDA INVÁLIDA
// Solo permite valores definidos en la configuración.
// ---------------------------------------------------------

test('product update requires a valid measurement unit', function () {
    [
        $propietario,
        $empresa,
        $categoriaInicial,
        $categoriaNueva,
        $proveedor,
        $producto,
    ] = prepararEntornoParaActualizarProducto();

    expect(
        fn () => app(
            ActualizarProducto::class
        )->ejecutar(
            empresa: $empresa,
            producto: $producto,
            categoria: $categoriaInicial,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'SSD-001',
                'codigo_barras' => '7501234567890',
                'nombre' => 'Unidad SSD modificada',
                'unidad_medida' => 'contenedor',
                'costo_referencia' => 900,
                'precio_base' => 1200,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($producto->fresh()?->unidad_medida)
        ->toBe('pieza');
});

// ---------------------------------------------------------
// IMPORTES NEGATIVOS
// El costo de referencia y el precio base deben ser iguales
// o mayores que cero.
// ---------------------------------------------------------

test('product update rejects negative monetary values', function (
    string $campo,
    float $valor
) {
    [
        $propietario,
        $empresa,
        $categoriaInicial,
        $categoriaNueva,
        $proveedor,
        $producto,
    ] = prepararEntornoParaActualizarProducto();

    $datos = [
        'sku' => 'SSD-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD modificada',
        'descripcion' => 'Descripción inicial.',
        'marca' => 'Marca inicial',
        'modelo' => 'Modelo inicial',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
    ];

    $datos[$campo] = $valor;

    expect(
        fn () => app(
            ActualizarProducto::class
        )->ejecutar(
            empresa: $empresa,
            producto: $producto,
            categoria: $categoriaInicial,
            proveedor: $proveedor,
            actor: $propietario,
            datos: $datos
        )
    )->toThrow(
        ValidationException::class
    );

    $producto->refresh();

    expect($producto->costo_referencia)
        ->toBe('900.00')
        ->and($producto->precio_base)
        ->toBe('1200.00');
})->with([
    'costo de referencia negativo' => [
        'costo_referencia',
        -1,
    ],
    'precio base negativo' => [
        'precio_base',
        -0.01,
    ],
]);
