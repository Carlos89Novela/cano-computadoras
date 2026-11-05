<?php

use App\Actions\Productos\ActualizarCategoriaProducto;
use App\Models\Auditoria;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
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

function propietarioParaActualizarCategoria(): User
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    return $propietario;
}

function empresaParaActualizarCategoria(
    User $actor,
    string $nombre = 'Cano Computadoras'
): Empresa {
    return Empresa::query()->create([
        'nombre' => $nombre,
        'activo' => true,
        'creado_por_id' => $actor->id,
    ]);
}

function categoriaParaActualizar(
    Empresa $empresa,
    User $actor,
    string $nombre = 'Almacenamiento'
): CategoriaProducto {
    return CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => $nombre,
        'descripcion' => 'Descripción inicial.',
        'activo' => true,
        'creado_por_id' => $actor->id,
    ]);
}

test('authorized user can update product category', function () {
    $propietario =
        propietarioParaActualizarCategoria();

    $empresa = empresaParaActualizarCategoria(
        $propietario
    );

    $categoria = categoriaParaActualizar(
        $empresa,
        $propietario
    );

    $actualizada = app(
        ActualizarCategoriaProducto::class
    )->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        actor: $propietario,
        nombre: 'Unidades de almacenamiento',
        descripcion: 'SSD, discos duros y almacenamiento externo.'
    );

    expect($actualizada->nombre)
        ->toBe('Unidades de almacenamiento')
        ->and($actualizada->descripcion)
        ->toBe(
            'SSD, discos duros y almacenamiento externo.'
        )
        ->and($actualizada->actualizado_por_id)
        ->toBe($propietario->id)
        ->and($actualizada->activo)
        ->toBeTrue();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'categoria_producto.actualizada'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_id)
        ->toBe($categoria->id)
        ->and($auditoria->valores_anteriores)
        ->toMatchArray([
            'nombre' => 'Almacenamiento',
            'descripcion' => 'Descripción inicial.',
        ])
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'nombre' => 'Unidades de almacenamiento',
            'descripcion' => 'SSD, discos duros y almacenamiento externo.',
        ]);
});

test('user with direct permission can update category', function () {
    $propietario =
        propietarioParaActualizarCategoria();

    $empresa = empresaParaActualizarCategoria(
        $propietario
    );

    $categoria = categoriaParaActualizar(
        $empresa,
        $propietario
    );

    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');
    $supervisor->givePermissionTo(
        'productos.actualizar'
    );

    $actualizada = app(
        ActualizarCategoriaProducto::class
    )->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        actor: $supervisor,
        nombre: 'Almacenamiento interno',
        descripcion: null
    );

    expect($actualizada->nombre)
        ->toBe('Almacenamiento interno')
        ->and($actualizada->descripcion)
        ->toBeNull()
        ->and($actualizada->actualizado_por_id)
        ->toBe($supervisor->id);
});

test('user without permission cannot update category', function () {
    $propietario =
        propietarioParaActualizarCategoria();

    $empresa = empresaParaActualizarCategoria(
        $propietario
    );

    $categoria = categoriaParaActualizar(
        $empresa,
        $propietario
    );

    $empleado = User::factory()->create();
    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            ActualizarCategoriaProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            actor: $empleado,
            nombre: 'Nombre no autorizado'
        )
    )->toThrow(
        AuthorizationException::class
    );

    expect($categoria->fresh()?->nombre)
        ->toBe('Almacenamiento');
});

test('category cannot be moved to another company', function () {
    $propietario =
        propietarioParaActualizarCategoria();

    $empresaUno = empresaParaActualizarCategoria(
        $propietario
    );

    $empresaDos = empresaParaActualizarCategoria(
        $propietario,
        'Empresa secundaria'
    );

    $categoria = categoriaParaActualizar(
        $empresaUno,
        $propietario
    );

    expect(
        fn () => app(
            ActualizarCategoriaProducto::class
        )->ejecutar(
            empresa: $empresaDos,
            categoria: $categoria,
            actor: $propietario,
            nombre: 'Nombre nuevo'
        )
    )->toThrow(
        AuthorizationException::class
    );
});

test('updated name must remain unique inside company', function () {
    $propietario =
        propietarioParaActualizarCategoria();

    $empresa = empresaParaActualizarCategoria(
        $propietario
    );

    $categoriaUno = categoriaParaActualizar(
        $empresa,
        $propietario,
        'Almacenamiento'
    );

    categoriaParaActualizar(
        $empresa,
        $propietario,
        'Memoria RAM'
    );

    expect(
        fn () => app(
            ActualizarCategoriaProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoriaUno,
            actor: $propietario,
            nombre: 'memoria ram'
        )
    )->toThrow(
        ValidationException::class
    );

    expect($categoriaUno->fresh()?->nombre)
        ->toBe('Almacenamiento');
});

test('category update requires an actual change', function () {
    $propietario =
        propietarioParaActualizarCategoria();

    $empresa = empresaParaActualizarCategoria(
        $propietario
    );

    $categoria = categoriaParaActualizar(
        $empresa,
        $propietario
    );

    expect(
        fn () => app(
            ActualizarCategoriaProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            actor: $propietario,
            nombre: 'Almacenamiento',
            descripcion: 'Descripción inicial.'
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'categoria_producto.actualizada'
            )
            ->count()
    )->toBe(0);
});
