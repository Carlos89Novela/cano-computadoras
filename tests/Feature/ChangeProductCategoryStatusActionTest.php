<?php

use App\Actions\Productos\CambiarEstadoCategoriaProducto;
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

function prepararCategoriaParaEstado(
    bool $activa = true
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
        'descripcion' => 'Discos y unidades SSD.',
        'activo' => $activa,
        'creado_por_id' => $propietario->id,
        'desactivado_por_id' => $activa
            ? null
            : $propietario->id,
        'desactivado_at' => $activa
            ? null
            : now(),
        'motivo_desactivacion' => $activa
            ? null
            : 'Desactivación inicial.',
    ]);

    return [
        $propietario,
        $empresa,
        $categoria,
    ];
}

test('authorized user can deactivate product category', function () {
    [
        $propietario,
        $empresa,
        $categoria,
    ] = prepararCategoriaParaEstado();

    $actualizada = app(
        CambiarEstadoCategoriaProducto::class
    )->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        actor: $propietario,
        activar: false,
        motivo: 'Categoría temporalmente fuera de uso.'
    );

    expect($actualizada->activo)
        ->toBeFalse()
        ->and($actualizada->desactivado_por_id)
        ->toBe($propietario->id)
        ->and($actualizada->desactivado_at)
        ->not->toBeNull()
        ->and($actualizada->motivo_desactivacion)
        ->toBe(
            'Categoría temporalmente fuera de uso.'
        );

    $this->assertDatabaseHas('auditorias', [
        'usuario_id' => $propietario->id,
        'accion' => 'categoria_producto.desactivada',
        'modelo_id' => $categoria->id,
        'motivo' => 'Categoría temporalmente fuera de uso.',
    ]);
});

test('authorized user can reactivate product category', function () {
    [
        $propietario,
        $empresa,
        $categoria,
    ] = prepararCategoriaParaEstado(false);

    $actualizada = app(
        CambiarEstadoCategoriaProducto::class
    )->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        actor: $propietario,
        activar: true,
        motivo: 'La categoría vuelve a utilizarse.'
    );

    expect($actualizada->activo)
        ->toBeTrue()
        ->and($actualizada->desactivado_por_id)
        ->toBeNull()
        ->and($actualizada->desactivado_at)
        ->toBeNull()
        ->and($actualizada->motivo_desactivacion)
        ->toBeNull();

    $this->assertDatabaseHas('auditorias', [
        'usuario_id' => $propietario->id,
        'accion' => 'categoria_producto.reactivada',
        'modelo_id' => $categoria->id,
        'motivo' => 'La categoría vuelve a utilizarse.',
    ]);
});

test('user with direct permission can change category status', function () {
    [
        $propietario,
        $empresa,
        $categoria,
    ] = prepararCategoriaParaEstado();

    $supervisor = User::factory()->create();

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'productos.cambiar_estado'
    );

    $actualizada = app(
        CambiarEstadoCategoriaProducto::class
    )->ejecutar(
        empresa: $empresa,
        categoria: $categoria,
        actor: $supervisor,
        activar: false,
        motivo: 'Categoría retirada del catálogo.'
    );

    expect($actualizada->activo)
        ->toBeFalse()
        ->and($actualizada->desactivado_por_id)
        ->toBe($supervisor->id);
});

test('user without permission cannot change category status', function () {
    [
        $propietario,
        $empresa,
        $categoria,
    ] = prepararCategoriaParaEstado();

    $empleado = User::factory()->create();

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CambiarEstadoCategoriaProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            actor: $empleado,
            activar: false,
            motivo: 'Cambio no autorizado.'
        )
    )->toThrow(
        AuthorizationException::class
    );

    expect($categoria->fresh()?->activo)
        ->toBeTrue();
});

test('status change requires a reason', function () {
    [
        $propietario,
        $empresa,
        $categoria,
    ] = prepararCategoriaParaEstado();

    expect(
        fn () => app(
            CambiarEstadoCategoriaProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            actor: $propietario,
            activar: false,
            motivo: '   '
        )
    )->toThrow(
        ValidationException::class
    );

    expect($categoria->fresh()?->activo)
        ->toBeTrue();
});

test('category cannot be changed to current status', function () {
    [
        $propietario,
        $empresa,
        $categoria,
    ] = prepararCategoriaParaEstado();

    expect(
        fn () => app(
            CambiarEstadoCategoriaProducto::class
        )->ejecutar(
            empresa: $empresa,
            categoria: $categoria,
            actor: $propietario,
            activar: true,
            motivo: 'Estado sin cambios.'
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'categoria_producto.desactivada',
                    'categoria_producto.reactivada',
                ]
            )
            ->count()
    )->toBe(0);
});
