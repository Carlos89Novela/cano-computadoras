<?php

use App\Actions\Proveedores\CambiarEstadoProveedor;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * Pruebas de integración para la Action CambiarEstadoProveedor.
 *
 * Cubre:
 * - Desactivación lógica (baja) registrando usuario, fecha y motivo de baja con auditoría.
 * - Reactivación limpiando los campos de desactivación con auditoría.
 * - Validación de permisos específicos ('productos.cambiar_estado').
 * - Control de pertenencia a la empresa.
 * - Exigencia del motivo o justificación.
 * - Rechazo de transiciones redundantes (activo a activo, o inactivo a inactivo).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

/**
 * Prepara una empresa y un proveedor con el estado activo indicado.
 *
 * @return array{0: User, 1: Empresa, 2: Proveedor}
 */
function prepararProveedorParaEstado(
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

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor de prueba',
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
        $proveedor,
    ];
}

// 1. Caso de éxito: Desactivación lógica completa con registro de auditoría 'proveedor.desactivado'
test('authorized user can deactivate supplier', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaEstado();

    $actualizado = app(
        CambiarEstadoProveedor::class
    )->ejecutar(
        empresa: $empresa,
        proveedor: $proveedor,
        actor: $propietario,
        activar: false,
        motivo: 'Proveedor temporalmente fuera de uso.'
    );

    expect($actualizado->activo)
        ->toBeFalse()
        ->and($actualizado->desactivado_por_id)
        ->toBe($propietario->id)
        ->and($actualizado->desactivado_at)
        ->not->toBeNull()
        ->and($actualizado->motivo_desactivacion)
        ->toBe(
            'Proveedor temporalmente fuera de uso.'
        );

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'proveedor.desactivado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_id)
        ->toBe($proveedor->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Proveedor::class)
        ->and($auditoria->motivo)
        ->toBe(
            'Proveedor temporalmente fuera de uso.'
        );
});

// 2. Caso de éxito: Reactivación de un proveedor inactivo, limpiando columnas de baja lógica
test('authorized user can reactivate supplier', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaEstado(activo: false);

    $actualizado = app(
        CambiarEstadoProveedor::class
    )->ejecutar(
        empresa: $empresa,
        proveedor: $proveedor,
        actor: $propietario,
        activar: true,
        motivo: 'Proveedor reactivado por renovación de contrato.'
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
            'proveedor.reactivado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->motivo)
        ->toBe(
            'Proveedor reactivado por renovación de contrato.'
        );
});

// 3. Permiso directo: Usuario con permiso Spatie 'productos.cambiar_estado'
test('user with direct permission can change supplier status', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaEstado();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'productos.cambiar_estado'
    );

    $actualizado = app(
        CambiarEstadoProveedor::class
    )->ejecutar(
        empresa: $empresa,
        proveedor: $proveedor,
        actor: $supervisor,
        activar: false,
        motivo: 'Desactivado por supervisor autorizado.'
    );

    expect($actualizado->activo)
        ->toBeFalse()
        ->and($actualizado->desactivado_por_id)
        ->toBe($supervisor->id);
});

// 4. Control de acceso: Usuario sin permiso recibe AuthorizationException
test('user without permission cannot change supplier status', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaEstado();

    $empleado = User::factory()->create();

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CambiarEstadoProveedor::class
        )->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $empleado,
            activar: false,
            motivo: 'Intento no autorizado'
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para cambiar el estado de proveedores.'
    );

    expect($proveedor->fresh()?->activo)
        ->toBeTrue();
});

// 5. Seguridad multi-tenant: Impide cambiar estado a través de otra empresa
test('supplier status cannot be changed through another company', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaEstado();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            CambiarEstadoProveedor::class
        )->ejecutar(
            empresa: $otraEmpresa,
            proveedor: $proveedor,
            actor: $propietario,
            activar: false,
            motivo: 'Intento con empresa incorrecta'
        )
    )->toThrow(
        AuthorizationException::class,
        'El proveedor no pertenece a la empresa indicada.'
    );
});

// 6. Justificación obligatoria: Motivo vacío lanza ValidationException
test('supplier status change requires a reason', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaEstado();

    expect(
        fn () => app(
            CambiarEstadoProveedor::class
        )->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $propietario,
            activar: false,
            motivo: '   '
        )
    )->toThrow(
        ValidationException::class
    );
});

// 7. No-redundancia: No se puede activar un proveedor que ya está activo
test('supplier cannot be changed to current status', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaEstado(activo: true);

    expect(
        fn () => app(
            CambiarEstadoProveedor::class
        )->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $propietario,
            activar: true,
            motivo: 'Reactivación redundante'
        )
    )->toThrow(
        ValidationException::class
    );
});
