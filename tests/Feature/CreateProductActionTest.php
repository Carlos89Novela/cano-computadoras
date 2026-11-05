<?php

use App\Actions\Productos\CrearProducto;
use App\Models\Auditoria;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------
// PREPARACIÓN GENERAL
// Carga roles y permisos antes de ejecutar cada prueba.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE PARA PRODUCTOS
// Crea propietario, empresa, categoría y proveedor activos.
// ---------------------------------------------------------

/**
 * @return array{
 *     0: User,
 *     1: Empresa,
 *     2: CategoriaProducto,
 *     3: Proveedor
 * }
 */
function prepararEntornoParaCrearProducto(): array
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

    $categoria = CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento',
        'descripcion' => 'Discos y unidades SSD.',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor de componentes',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    return [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ];
}

// ---------------------------------------------------------
// CREACIÓN COMPLETA
// Valida normalización, relaciones, precios y auditoría.
// ---------------------------------------------------------

test('authorized user can create product', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    $request = Request::create(
        '/operacion/empresas/'
            .$empresa->id
            .'/productos',
        'POST',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '192.168.1.130',
            'HTTP_USER_AGENT' => 'Navegador de prueba',
        ]
    );

    $producto = app(
        CrearProducto::class
    )->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        proveedor: $proveedor,
        actor: $propietario,
        datos: [
            'sku' => ' ssd-1tb-001 ',
            'codigo_barras' => ' 7501234567890 ',
            'nombre' => ' Unidad SSD 1 TB ',
            'descripcion' => ' Unidad de almacenamiento de estado sólido. ',
            'marca' => ' Marca de prueba ',
            'modelo' => ' SSD-1000 ',
            'unidad_medida' => ' PIEZA ',
            'costo_referencia' => '950.50',
            'precio_base' => '1299.99',
        ],
        request: $request
    );

    expect($producto->sku)
        ->toBe('SSD-1TB-001')
        ->and($producto->codigo_barras)
        ->toBe('7501234567890')
        ->and($producto->nombre)
        ->toBe('Unidad SSD 1 TB')
        ->and($producto->descripcion)
        ->toBe(
            'Unidad de almacenamiento de estado sólido.'
        )
        ->and($producto->marca)
        ->toBe('Marca de prueba')
        ->and($producto->modelo)
        ->toBe('SSD-1000')
        ->and($producto->unidad_medida)
        ->toBe('pieza')
        ->and($producto->costo_referencia)
        ->toBe('950.50')
        ->and($producto->precio_base)
        ->toBe('1299.99')
        ->and($producto->activo)
        ->toBeTrue();

    expect($producto->empresa_id)
        ->toBe($empresa->id)
        ->and($producto->categoria_producto_id)
        ->toBe($categoria->id)
        ->and($producto->proveedor_principal_id)
        ->toBe($proveedor->id)
        ->and($producto->creado_por_id)
        ->toBe($propietario->id);

    $auditoria = Auditoria::query()
        ->where('accion', 'producto.creado')
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_id)
        ->toBe($producto->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Producto::class)
        ->and($auditoria->modulo)
        ->toBe('productos')
        ->and($auditoria->direccion_ip)
        ->toBe('192.168.1.130')
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'categoria_producto_id' => $categoria->id,
            'proveedor_principal_id' => $proveedor->id,
            'sku' => 'SSD-1TB-001',
            'codigo_barras' => '7501234567890',
            'nombre' => 'Unidad SSD 1 TB',
            'activo' => true,
        ]);
});

// ---------------------------------------------------------
// PRODUCTO SIN PROVEEDOR PRINCIPAL
// El proveedor es opcional durante el registro.
// ---------------------------------------------------------

test('product can be created without main supplier', function () {
    [
        $propietario,
        $empresa,
        $categoria,
    ] = prepararEntornoParaCrearProducto();

    $producto = app(
        CrearProducto::class
    )->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        proveedor: null,
        actor: $propietario,
        datos: [
            'sku' => 'RAM-16GB-001',
            'codigo_barras' => '',
            'nombre' => 'Memoria RAM 16 GB',
            'descripcion' => '',
            'marca' => '',
            'modelo' => '',
            'unidad_medida' => 'pieza',
            'costo_referencia' => '',
            'precio_base' => '',
        ]
    );

    expect($producto->sku)
        ->toBe('RAM-16GB-001')
        ->and($producto->proveedor_principal_id)
        ->toBeNull()
        ->and($producto->codigo_barras)
        ->toBeNull()
        ->and($producto->descripcion)
        ->toBeNull()
        ->and($producto->marca)
        ->toBeNull()
        ->and($producto->modelo)
        ->toBeNull()
        ->and($producto->costo_referencia)
        ->toBeNull()
        ->and($producto->precio_base)
        ->toBeNull();
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor autorizado puede crear productos sin recibir
// acceso administrativo completo.
// ---------------------------------------------------------

test('user with direct permission can create product', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'productos.crear'
    );

    $producto = app(
        CrearProducto::class
    )->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        proveedor: $proveedor,
        actor: $supervisor,
        datos: [
            'sku' => 'CARGADOR-001',
            'nombre' => 'Cargador universal',
            'unidad_medida' => 'pieza',
            'precio_base' => 599.90,
        ]
    );

    expect($producto->sku)
        ->toBe('CARGADOR-001')
        ->and($producto->creado_por_id)
        ->toBe($supervisor->id)
        ->and($producto->precio_base)
        ->toBe('599.90');
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide crear productos a usuarios que no tengan
// productos.crear.
// ---------------------------------------------------------

test('user without permission cannot create product', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $empleado,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto no autorizado',
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para crear productos.'
    );

    expect(Producto::query()->count())
        ->toBe(0);

    expect(
        Auditoria::query()
            ->where('accion', 'producto.creado')
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// No se permiten nuevos productos para una empresa que se
// encuentre fuera de operación.
// ---------------------------------------------------------

test('product cannot be created for inactive company', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    $empresa->update([
        'activo' => false,
    ]);

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa->fresh(),
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto inválido',
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Producto::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// CATEGORÍA DE OTRA EMPRESA
// Impide relacionar un producto con una categoría que no
// pertenezca a la empresa del producto.
// ---------------------------------------------------------

test('product category must belong to company', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $otraCategoria = CategoriaProducto::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'nombre' => 'Categoría externa',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $otraCategoria,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto inválido',
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'La categoría no pertenece a la empresa indicada.'
    );

    expect(Producto::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// CATEGORÍA INACTIVA
// Una categoría fuera del catálogo no puede utilizarse para
// registrar productos nuevos.
// ---------------------------------------------------------

test('product cannot use inactive category', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    $categoria->update([
        'activo' => false,
        'desactivado_por_id' => $propietario->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Categoría fuera de uso.',
    ]);

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria->fresh(),
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto inválido',
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Producto::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// PROVEEDOR DE OTRA EMPRESA
// Impide asignar como proveedor principal un registro que
// pertenezca a otra empresa.
// ---------------------------------------------------------

test('main supplier must belong to company', function () {
    [
        $propietario,
        $empresa,
        $categoria,
    ] = prepararEntornoParaCrearProducto();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $proveedorExterno = Proveedor::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'codigo' => 'PROV-EXT',
        'nombre' => 'Proveedor externo',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedorExterno,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto inválido',
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El proveedor no pertenece a la empresa indicada.'
    );

    expect(Producto::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// PROVEEDOR INACTIVO
// Un proveedor desactivado no puede asignarse como proveedor
// principal de un producto nuevo.
// ---------------------------------------------------------

test('product cannot use inactive supplier', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    $proveedor->update([
        'activo' => false,
        'desactivado_por_id' => $propietario->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Proveedor suspendido.',
    ]);

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor->fresh(),
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto inválido',
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Producto::query()->count())
        ->toBe(0);
});

// ---------------------------------------------------------
// SKU DUPLICADO
// Impide registrar dos productos con el mismo SKU dentro
// de la misma empresa, sin distinguir mayúsculas.
// ---------------------------------------------------------

test('product sku must be unique ignoring case', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    $accion = app(CrearProducto::class);

    $accion->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        proveedor: $proveedor,
        actor: $propietario,
        datos: [
            'sku' => 'SSD-001',
            'nombre' => 'Primer producto',
            'unidad_medida' => 'pieza',
        ]
    );

    expect(
        fn () => $accion->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'ssd-001',
                'nombre' => 'Producto duplicado',
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Producto::query()->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// CÓDIGO DE BARRAS DUPLICADO
// Impide reutilizar el mismo código de barras dentro de
// una empresa.
// ---------------------------------------------------------

test('product barcode must be unique inside company', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    $accion = app(CrearProducto::class);

    $accion->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        proveedor: $proveedor,
        actor: $propietario,
        datos: [
            'sku' => 'PROD-001',
            'codigo_barras' => '7501234567890',
            'nombre' => 'Primer producto',
            'unidad_medida' => 'pieza',
        ]
    );

    expect(
        fn () => $accion->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-002',
                'codigo_barras' => '7501234567890',
                'nombre' => 'Segundo producto',
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Producto::query()->count()
    )->toBe(1);
});

// ---------------------------------------------------------
// UNIDAD DE MEDIDA INVÁLIDA
// Solo se aceptan unidades registradas en la configuración
// access_control.unidades_medida_productos.
// ---------------------------------------------------------

test('product requires a valid measurement unit', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto inválido',
                'unidad_medida' => 'contenedor',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Producto::query()->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// COSTO NEGATIVO
// El costo de referencia debe ser igual o mayor que cero.
// ---------------------------------------------------------

test('product reference cost cannot be negative', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto inválido',
                'unidad_medida' => 'pieza',
                'costo_referencia' => -1,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Producto::query()->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// PRECIO NEGATIVO
// El precio base debe ser igual o mayor que cero.
// ---------------------------------------------------------

test('product base price cannot be negative', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto inválido',
                'unidad_medida' => 'pieza',
                'precio_base' => -0.01,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Producto::query()->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// NOMBRE OBLIGATORIO
// Impide crear registros sin un nombre identificable.
// ---------------------------------------------------------

test('product name is required', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => '   ',
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Producto::query()->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// LONGITUD DE CAMPOS OPCIONALES
// Evita almacenar textos mayores que los límites definidos
// por el catálogo.
// ---------------------------------------------------------

test('product optional text fields respect length limits', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
    ] = prepararEntornoParaCrearProducto();

    expect(
        fn () => app(
            CrearProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'sku' => 'PROD-001',
                'nombre' => 'Producto inválido',
                'descripcion' => str_repeat('A', 4001),
                'unidad_medida' => 'pieza',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Producto::query()->count()
    )->toBe(0);
});
