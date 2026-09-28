<?php

use App\Actions\Productos\CrearCategoriaProducto;
use App\Models\Auditoria;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
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

function propietarioParaCategoria(): User
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    return $propietario;
}

function empresaParaCategoria(
    User $propietario,
    bool $activa = true
): Empresa {
    return Empresa::query()->create([
        'nombre' => $activa
            ? 'Cano Computadoras'
            : 'Empresa inactiva',
        'activo' => $activa,
        'creado_por_id' => $propietario->id,
    ]);
}

test('authorized user can create product category', function () {
    $propietario = propietarioParaCategoria();

    $empresa = empresaParaCategoria(
        $propietario
    );

    $request = Request::create(
        '/admin/empresas/'.$empresa->id.'/categorias-producto',
        'POST',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_USER_AGENT' => 'Navegador de prueba',
        ]
    );

    $categoria = app(
        CrearCategoriaProducto::class
    )->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        nombre: '  Almacenamiento  ',
        descripcion: '  Discos duros y unidades de estado sólido.  ',
        request: $request
    );

    expect($categoria->nombre)
        ->toBe('Almacenamiento')
        ->and($categoria->descripcion)
        ->toBe(
            'Discos duros y unidades de estado sólido.'
        )
        ->and($categoria->activo)
        ->toBeTrue()
        ->and($categoria->empresa_id)
        ->toBe($empresa->id)
        ->and($categoria->creado_por_id)
        ->toBe($propietario->id);

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'categoria_producto.creada'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_id)
        ->toBe($categoria->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(CategoriaProducto::class)
        ->and($auditoria->modulo)
        ->toBe('productos')
        ->and($auditoria->direccion_ip)
        ->toBe('192.168.1.100')
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'empresa_id' => $empresa->id,
            'nombre' => 'Almacenamiento',
            'activo' => true,
        ]);
});

test('user with direct permission can create category', function () {
    $propietario = propietarioParaCategoria();

    $empresa = empresaParaCategoria(
        $propietario
    );

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'productos.crear'
    );

    $categoria = app(
        CrearCategoriaProducto::class
    )->ejecutar(
        empresa: $empresa,
        actor: $supervisor,
        nombre: 'Memoria RAM'
    );

    expect($categoria->nombre)
        ->toBe('Memoria RAM')
        ->and($categoria->creado_por_id)
        ->toBe($supervisor->id);
});

test('user without permission cannot create category', function () {
    $propietario = propietarioParaCategoria();

    $empresa = empresaParaCategoria(
        $propietario
    );

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CrearCategoriaProducto::class
        )->ejecutar(
            empresa: $empresa,
            actor: $empleado,
            nombre: 'Memoria RAM'
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para crear categorías de productos.'
    );

    expect(
        CategoriaProducto::query()->count()
    )->toBe(0);

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'categoria_producto.creada'
            )
            ->count()
    )->toBe(0);
});

test('category name is required', function () {
    $propietario = propietarioParaCategoria();

    $empresa = empresaParaCategoria(
        $propietario
    );

    expect(
        fn () => app(
            CrearCategoriaProducto::class
        )->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            nombre: '   '
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        CategoriaProducto::query()->count()
    )->toBe(0);
});

test('category name must be unique ignoring case', function () {
    $propietario = propietarioParaCategoria();

    $empresa = empresaParaCategoria(
        $propietario
    );

    $accion = app(
        CrearCategoriaProducto::class
    );

    $accion->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        nombre: 'Almacenamiento'
    );

    expect(
        fn () => $accion->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            nombre: 'almacenamiento'
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        CategoriaProducto::query()->count()
    )->toBe(1);
});

test('same category name can be created in different companies', function () {
    $propietario = propietarioParaCategoria();

    $empresaUno = empresaParaCategoria(
        $propietario
    );

    $empresaDos = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $accion = app(
        CrearCategoriaProducto::class
    );

    $accion->ejecutar(
        empresa: $empresaUno,
        actor: $propietario,
        nombre: 'Almacenamiento'
    );

    $accion->ejecutar(
        empresa: $empresaDos,
        actor: $propietario,
        nombre: 'Almacenamiento'
    );

    expect(
        CategoriaProducto::query()->count()
    )->toBe(2);
});

test('category cannot be created for inactive company', function () {
    $propietario = propietarioParaCategoria();

    $empresa = empresaParaCategoria(
        $propietario,
        false
    );

    expect(
        fn () => app(
            CrearCategoriaProducto::class
        )->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            nombre: 'Consumibles'
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        CategoriaProducto::query()->count()
    )->toBe(0);
});

test('empty description is stored as null', function () {
    $propietario = propietarioParaCategoria();

    $empresa = empresaParaCategoria(
        $propietario
    );

    $categoria = app(
        CrearCategoriaProducto::class
    )->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        nombre: 'Consumibles',
        descripcion: '   '
    );

    expect($categoria->descripcion)
        ->toBeNull();
});
