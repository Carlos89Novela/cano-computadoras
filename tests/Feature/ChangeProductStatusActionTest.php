<?php

use App\Actions\Productos\CambiarEstadoProducto;
use App\Models\Auditoria;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------
// PREPARACIÓN GENERAL
// Carga roles y permisos antes de cada prueba.
// ---------------------------------------------------------

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

// ---------------------------------------------------------
// ENTORNO BASE
// Genera propietario, empresa, categoría, proveedor y
// producto para pruebas de activación y desactivación.
// ---------------------------------------------------------

/**
 * @return array{
 *     0: User,
 *     1: Empresa,
 *     2: CategoriaProducto,
 *     3: Proveedor,
 *     4: Producto
 * }
 */
function prepararProductoParaEstado(
    bool $activo = true
): array {
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
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => $proveedor->id,
        'sku' => 'SSD-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => $activo,
        'creado_por_id' => $propietario->id,
        'desactivado_por_id' => $activo
            ? null
            : $propietario->id,
        'desactivado_at' => $activo
            ? null
            : now(),
        'motivo_desactivacion' => $activo
            ? null
            : 'Desactivación inicial.',
    ]);

    return [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
        $producto,
    ];
}

// ---------------------------------------------------------
// DESACTIVACIÓN
// Un usuario autorizado puede desactivar un producto.
// ---------------------------------------------------------

test('authorized user can deactivate product', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
        $producto,
    ] = prepararProductoParaEstado();

    $actualizado = app(
        CambiarEstadoProducto::class
    )->ejecutar(
        empresa: $empresa,
        producto: $producto,
        actor: $propietario,
        activar: false,
        motivo: 'Producto fuera de catálogo.'
    );

    expect($actualizado->activo)
        ->toBeFalse()
        ->and($actualizado->desactivado_por_id)
        ->toBe($propietario->id)
        ->and($actualizado->desactivado_at)
        ->not->toBeNull()
        ->and($actualizado->motivo_desactivacion)
        ->toBe('Producto fuera de catálogo.');

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'producto.desactivado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_id)
        ->toBe($producto->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Producto::class)
        ->and($auditoria->motivo)
        ->toBe('Producto fuera de catálogo.');
});

// ---------------------------------------------------------
// REACTIVACIÓN
// Un usuario autorizado puede reactivar un producto.
// ---------------------------------------------------------

test('authorized user can reactivate product', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
        $producto,
    ] = prepararProductoParaEstado(false);

    $actualizado = app(
        CambiarEstadoProducto::class
    )->ejecutar(
        empresa: $empresa,
        producto: $producto,
        actor: $propietario,
        activar: true,
        motivo: 'Producto nuevamente disponible.'
    );

    expect($actualizado->activo)
        ->toBeTrue()
        ->and($actualizado->desactivado_por_id)
        ->toBeNull()
        ->and($actualizado->desactivado_at)
        ->toBeNull()
        ->and($actualizado->motivo_desactivacion)
        ->toBeNull();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'producto.reactivado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_id)
        ->toBe($producto->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Producto::class)
        ->and($auditoria->motivo)
        ->toBe('Producto nuevamente disponible.');
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un usuario con permiso directo puede cambiar el estado.
// ---------------------------------------------------------

test('user with direct permission can change product status', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
        $producto,
    ] = prepararProductoParaEstado();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'productos.cambiar_estado'
    );

    $actualizado = app(
        CambiarEstadoProducto::class
    )->ejecutar(
        empresa: $empresa,
        producto: $producto,
        actor: $supervisor,
        activar: false,
        motivo: 'Desactivado por supervisor autorizado.'
    );

    expect($actualizado->activo)
        ->toBeFalse()
        ->and($actualizado->desactivado_por_id)
        ->toBe($supervisor->id);
});

// ---------------------------------------------------------
// CONTROL DE ACCESO
// Un usuario sin permiso no puede cambiar el estado.
// ---------------------------------------------------------

test('user without permission cannot change product status', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
        $producto,
    ] = prepararProductoParaEstado();

    $empleado = User::factory()->create();

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CambiarEstadoProducto::class
        )->ejecutar(
            empresa: $empresa,
            producto: $producto,
            actor: $empleado,
            activar: false,
            motivo: 'Intento no autorizado'
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para cambiar el estado de productos.'
    );

    expect($producto->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// AISLAMIENTO MULTI-TENANT
// Un producto no puede modificarse desde otra empresa.
// ---------------------------------------------------------

test('product status cannot be changed through another company', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
        $producto,
    ] = prepararProductoParaEstado();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Otra Empresa',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            CambiarEstadoProducto::class
        )->ejecutar(
            empresa: $otraEmpresa,
            producto: $producto,
            actor: $propietario,
            activar: false,
            motivo: 'Intento con empresa ajena'
        )
    )->toThrow(
        AuthorizationException::class,
        'El producto no pertenece a la empresa indicada.'
    );
});

// ---------------------------------------------------------
// JUSTIFICACIÓN OBLIGATORIA
// El motivo no puede ser vacío o puros espacios en blanco.
// ---------------------------------------------------------

test('product status change requires a reason', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
        $producto,
    ] = prepararProductoParaEstado();

    expect(
        fn () => app(
            CambiarEstadoProducto::class
        )->ejecutar(
            empresa: $empresa,
            producto: $producto,
            actor: $propietario,
            activar: false,
            motivo: '   '
        )
    )->toThrow(
        ValidationException::class
    );

    expect($producto->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// ESTADO ACTUAL
// No se puede aplicar una transición al estado ya existente.
// ---------------------------------------------------------

test('product cannot be changed to current status', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
        $producto,
    ] = prepararProductoParaEstado(activo: true);

    expect(
        fn () => app(
            CambiarEstadoProducto::class
        )->ejecutar(
            empresa: $empresa,
            producto: $producto,
            actor: $propietario,
            activar: true,
            motivo: 'Reactivación redundante'
        )
    )->toThrow(
        ValidationException::class
    );
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// No se puede cambiar el estado en empresas inactivas.
// ---------------------------------------------------------

test('product status cannot be changed for inactive company', function () {
    [
        $propietario,
        $empresa,
        $categoria,
        $proveedor,
        $producto,
    ] = prepararProductoParaEstado();

    $empresa->update(['activo' => false]);

    expect(
        fn () => app(
            CambiarEstadoProducto::class
        )->ejecutar(
            empresa: $empresa,
            producto: $producto,
            actor: $propietario,
            activar: false,
            motivo: 'Empresa fuera de servicio'
        )
    )->toThrow(
        ValidationException::class
    );
});
