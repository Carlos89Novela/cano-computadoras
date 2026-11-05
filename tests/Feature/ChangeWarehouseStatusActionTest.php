<?php

use App\Actions\Almacenes\CambiarEstadoAlmacen;
use App\Models\Almacen;
use App\Models\Auditoria;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\ExistenciaAlmacen;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
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
// Crea propietario, empresa, sucursal, almacén principal
// y almacén secundario para cambiar su estado.
// ---------------------------------------------------------

/**
 * @return array{
 *     0: User,
 *     1: Empresa,
 *     2: Sucursal,
 *     3: Almacen,
 *     4: Almacen
 * }
 */
function prepararEntornoParaCambiarEstadoAlmacen(
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

    $sucursal = Sucursal::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'MATRIZ',
        'nombre' => 'Sucursal principal',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $almacenPrincipal = Almacen::query()->create([
        'empresa_id' => $empresa->id,
        'sucursal_id' => $sucursal->id,
        'codigo' => 'ALM-MATRIZ',
        'nombre' => 'Almacén principal',
        'tipo' => Almacen::TIPO_PRINCIPAL,
        'es_virtual' => false,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $almacenSecundario = Almacen::query()->create([
        'empresa_id' => $empresa->id,
        'sucursal_id' => $sucursal->id,
        'codigo' => 'SECUNDARIO-01',
        'nombre' => 'Almacén secundario',
        'tipo' => Almacen::TIPO_SECUNDARIO,
        'es_virtual' => false,
        'permite_existencias' => true,
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
            : 'Almacén desactivado inicialmente.',
    ]);

    return [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ];
}

// ---------------------------------------------------------
// EXISTENCIA PARA CAMBIO DE ESTADO
// Crea una categoría, un producto y su registro de
// existencia dentro del almacén indicado.
// ---------------------------------------------------------

function crearExistenciaParaCambioEstadoAlmacen(
    User $usuario,
    Empresa $empresa,
    Almacen $almacen,
    string $cantidad,
    string $cantidadReservada
): ExistenciaAlmacen {
    $categoria = CategoriaProducto::query()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Almacenamiento',
        'descripcion' => 'Productos utilizados para validar existencias.',
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    $producto = Producto::query()->create([
        'empresa_id' => $empresa->id,
        'categoria_producto_id' => $categoria->id,
        'proveedor_principal_id' => null,
        'sku' => 'SSD-ESTADO-001',
        'codigo_barras' => '7501234567890',
        'nombre' => 'Unidad SSD para estado',
        'descripcion' => 'Producto utilizado para validar el almacén.',
        'marca' => 'Marca de prueba',
        'modelo' => 'SSD-ESTADO',
        'unidad_medida' => 'pieza',
        'costo_referencia' => 900,
        'precio_base' => 1200,
        'activo' => true,
        'creado_por_id' => $usuario->id,
    ]);

    return ExistenciaAlmacen::query()->create([
        'empresa_id' => $empresa->id,
        'almacen_id' => $almacen->id,
        'producto_id' => $producto->id,
        'cantidad' => $cantidad,
        'cantidad_reservada' => $cantidadReservada,
        'costo_promedio' => '900.0000',
        'actualizado_por_id' => $usuario->id,
    ]);
}

// ---------------------------------------------------------
// DESACTIVACIÓN CORRECTA
// Un administrador autorizado puede desactivar un almacén
// secundario sin eliminar su registro ni sus relaciones.
// ---------------------------------------------------------

test('authorized user can deactivate secondary warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $actualizado = app(
        CambiarEstadoAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $propietario,
        activar: false,
        motivo: ' Almacén temporalmente fuera de operación. '
    );

    expect($actualizado->activo)
        ->toBeFalse()
        ->and($actualizado->desactivado_por_id)
        ->toBe($propietario->id)
        ->and($actualizado->desactivado_at)
        ->not->toBeNull()
        ->and($actualizado->motivo_desactivacion)
        ->toBe(
            'Almacén temporalmente fuera de operación.'
        )
        ->and($actualizado->empresa_id)
        ->toBe($empresa->id)
        ->and($actualizado->sucursal_id)
        ->toBe($sucursal->id)
        ->and($actualizado->tipo)
        ->toBe(Almacen::TIPO_SECUNDARIO)
        ->and($actualizado->es_virtual)
        ->toBeFalse();

    expect($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// AUDITORÍA DE DESACTIVACIÓN
// Registra el actor, almacén, valores de estado y motivo
// normalizado de la desactivación.
// ---------------------------------------------------------

test('warehouse deactivation stores audit information', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $actualizado = app(
        CambiarEstadoAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $propietario,
        activar: false,
        motivo: ' Cierre temporal por reorganización. '
    );

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'almacen.desactivado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Almacen::class)
        ->and($auditoria->modelo_id)
        ->toBe($actualizado->id)
        ->and($auditoria->modulo)
        ->toBe('almacenes')
        ->and($auditoria->motivo)
        ->toBe('Cierre temporal por reorganización.')
        ->and($auditoria->valores_anteriores)
        ->toMatchArray([
            'activo' => true,
            'desactivado_por_id' => null,
            'desactivado_at' => null,
            'motivo_desactivacion' => null,
        ])
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'activo' => false,
            'desactivado_por_id' => $propietario->id,
            'motivo_desactivacion' => 'Cierre temporal por reorganización.',
        ]);

    expect($actualizado->activo)
        ->toBeFalse()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// REACTIVACIÓN CORRECTA
// Un almacén secundario desactivado puede volver a estar
// operativo y limpia sus datos de desactivación.
// ---------------------------------------------------------

test('authorized user can reactivate secondary warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen(false);

    $actualizado = app(
        CambiarEstadoAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $propietario,
        activar: true,
        motivo: ' Reanudación de operaciones del almacén. '
    );

    expect($actualizado->activo)
        ->toBeTrue()
        ->and($actualizado->desactivado_por_id)
        ->toBeNull()
        ->and($actualizado->desactivado_at)
        ->toBeNull()
        ->and($actualizado->motivo_desactivacion)
        ->toBeNull()
        ->and($actualizado->empresa_id)
        ->toBe($empresa->id)
        ->and($actualizado->sucursal_id)
        ->toBe($sucursal->id)
        ->and($actualizado->tipo)
        ->toBe(Almacen::TIPO_SECUNDARIO)
        ->and($actualizado->es_virtual)
        ->toBeFalse();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'almacen.reactivado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Almacen::class)
        ->and($auditoria->modelo_id)
        ->toBe($actualizado->id)
        ->and($auditoria->modulo)
        ->toBe('almacenes')
        ->and($auditoria->motivo)
        ->toBe('Reanudación de operaciones del almacén.')
        ->and($auditoria->valores_anteriores)
        ->toMatchArray([
            'activo' => false,
            'desactivado_por_id' => $propietario->id,
            'motivo_desactivacion' => 'Almacén desactivado inicialmente.',
        ])
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'activo' => true,
            'desactivado_por_id' => null,
            'desactivado_at' => null,
            'motivo_desactivacion' => null,
        ]);

    expect($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor puede cambiar el estado del almacén cuando
// recibe almacenes.cambiar_estado individualmente.
// ---------------------------------------------------------

test('user with direct permission can change warehouse status', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'almacenes.cambiar_estado'
    );

    $actualizado = app(
        CambiarEstadoAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $supervisor,
        activar: false,
        motivo: 'Desactivación autorizada al supervisor.'
    );

    expect($actualizado->activo)
        ->toBeFalse()
        ->and($actualizado->desactivado_por_id)
        ->toBe($supervisor->id)
        ->and($actualizado->motivo_desactivacion)
        ->toBe(
            'Desactivación autorizada al supervisor.'
        )
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide cambiar el estado de almacenes cuando el actor no
// tiene almacenes.cambiar_estado.
// ---------------------------------------------------------

test('user without permission cannot change warehouse status', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $empleado,
            activar: false,
            motivo: 'Cambio no autorizado.'
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para cambiar el estado de almacenes.'
    );

    $almacenSecundario->refresh();

    expect($almacenSecundario->activo)
        ->toBeTrue()
        ->and($almacenSecundario->desactivado_por_id)
        ->toBeNull()
        ->and($almacenSecundario->desactivado_at)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'almacen.desactivado',
                    'almacen.reactivado',
                ]
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN DE OTRA EMPRESA
// Evita cambiar el estado de un almacén utilizando una
// empresa que no es propietaria del registro.
// ---------------------------------------------------------

test('warehouse status cannot be changed through another company', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $otraEmpresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            activar: false,
            motivo: 'Intento con empresa incorrecta.'
        )
    )->toThrow(
        AuthorizationException::class,
        'El almacén no pertenece a la empresa indicada.'
    );

    $almacenSecundario->refresh();

    expect($almacenSecundario->empresa_id)
        ->toBe($empresa->id)
        ->and($almacenSecundario->activo)
        ->toBeTrue()
        ->and($almacenSecundario->desactivado_por_id)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'almacen.desactivado',
                    'almacen.reactivado',
                ]
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// Una empresa desactivada no puede modificar el estado de
// sus almacenes.
// ---------------------------------------------------------

test('warehouse status cannot change inside inactive company', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $empresa->update([
        'activo' => false,
        'desactivado_por_id' => $propietario->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Empresa temporalmente suspendida.',
    ]);

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa->fresh(),
            almacen: $almacenSecundario,
            actor: $propietario,
            activar: false,
            motivo: 'Cambio durante la suspensión.'
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenSecundario->refresh();

    expect($almacenSecundario->activo)
        ->toBeTrue()
        ->and($almacenSecundario->desactivado_por_id)
        ->toBeNull()
        ->and($almacenSecundario->desactivado_at)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'almacen.desactivado',
                    'almacen.reactivado',
                ]
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ESTADO SIN CAMBIOS
// Impide activar un almacén activo o desactivar uno que ya
// está desactivado.
// ---------------------------------------------------------

test('warehouse cannot be changed to current status', function (
    bool $estadoInicial,
    bool $estadoSolicitado
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen(
        $estadoInicial
    );

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            activar: $estadoSolicitado,
            motivo: 'Intento de conservar el mismo estado.'
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->activo)
        ->toBe($estadoInicial)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'almacen.desactivado',
                    'almacen.reactivado',
                ]
            )
            ->count()
    )->toBe(0);
})->with([
    'activar almacén ya activo' => [
        true,
        true,
    ],
    'desactivar almacén ya inactivo' => [
        false,
        false,
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Todo cambio de estado necesita una justificación válida
// de hasta 1000 caracteres.
// ---------------------------------------------------------

test('warehouse status change requires a valid reason', function (
    string $motivo
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            activar: false,
            motivo: $motivo
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenSecundario->refresh();

    expect($almacenSecundario->activo)
        ->toBeTrue()
        ->and($almacenSecundario->desactivado_por_id)
        ->toBeNull()
        ->and($almacenSecundario->desactivado_at)
        ->toBeNull()
        ->and($almacenSecundario->motivo_desactivacion)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'almacen.desactivado',
                    'almacen.reactivado',
                ]
            )
            ->count()
    )->toBe(0);
})->with([
    'motivo vacío' => [
        '   ',
    ],
    'motivo mayor de mil caracteres' => [
        str_repeat('A', 1001),
    ],
]);

// ---------------------------------------------------------
// ALMACÉN PRINCIPAL PROTEGIDO
// El almacén principal de una sucursal no puede ser
// desactivado mientras la sucursal permanezca operativa.
// ---------------------------------------------------------

test('main warehouse cannot be deactivated manually', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenPrincipal,
            actor: $propietario,
            activar: false,
            motivo: 'Intento de desactivar el almacén principal.'
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenPrincipal->refresh();

    expect($almacenPrincipal->activo)
        ->toBeTrue()
        ->and($almacenPrincipal->desactivado_por_id)
        ->toBeNull()
        ->and($almacenPrincipal->desactivado_at)
        ->toBeNull()
        ->and($almacenSecundario->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.desactivado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN DE TRÁNSITO PROTEGIDO
// El almacén virtual de tránsito es administrado por el
// sistema y no puede desactivarse manualmente.
// ---------------------------------------------------------

test('transit warehouse status cannot be changed manually', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $almacenTransito = Almacen::query()->create([
        'empresa_id' => $empresa->id,
        'sucursal_id' => null,
        'codigo' => 'TRANSITO',
        'nombre' => 'Almacén virtual de tránsito',
        'tipo' => Almacen::TIPO_TRANSITO,
        'es_virtual' => true,
        'permite_existencias' => true,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenTransito,
            actor: $propietario,
            activar: false,
            motivo: 'Intento de desactivar el tránsito.'
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenTransito->refresh();

    expect($almacenTransito->activo)
        ->toBeTrue()
        ->and($almacenTransito->tipo)
        ->toBe(Almacen::TIPO_TRANSITO)
        ->and($almacenTransito->es_virtual)
        ->toBeTrue()
        ->and($almacenTransito->desactivado_por_id)
        ->toBeNull()
        ->and($almacenTransito->desactivado_at)
        ->toBeNull()
        ->and($almacenTransito->motivo_desactivacion)
        ->toBeNull();
});

// ---------------------------------------------------------
// SUCURSAL INACTIVA
// Impide cambiar el estado de un almacén físico mientras
// su sucursal se encuentre fuera de operación.
// ---------------------------------------------------------

test('warehouse status cannot change inside inactive branch', function (
    bool $estadoInicial,
    bool $estadoSolicitado
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen(
        $estadoInicial
    );

    $sucursal->update([
        'activo' => false,
        'desactivado_por_id' => $propietario->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Sucursal temporalmente fuera de operación.',
    ]);

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            activar: $estadoSolicitado,
            motivo: 'Intento durante el cierre de la sucursal.'
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenSecundario->refresh();

    expect($almacenSecundario->activo)
        ->toBe($estadoInicial)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'almacen.desactivado',
                    'almacen.reactivado',
                ]
            )
            ->count()
    )->toBe(0);
})->with([
    'desactivar dentro de sucursal inactiva' => [
        true,
        false,
    ],
    'reactivar dentro de sucursal inactiva' => [
        false,
        true,
    ],
]);

// ---------------------------------------------------------
// ALMACÉN SIN SUCURSAL
// Un almacén físico debe permanecer asociado a una
// sucursal válida de la empresa.
// ---------------------------------------------------------

test('physical warehouse requires a valid branch', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $almacenSecundario->update([
        'sucursal_id' => null,
    ]);

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario->fresh(),
            actor: $propietario,
            activar: false,
            motivo: 'Intento con almacén físico sin sucursal.'
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenSecundario->refresh();

    expect($almacenSecundario->activo)
        ->toBeTrue()
        ->and($almacenSecundario->desactivado_por_id)
        ->toBeNull()
        ->and($almacenSecundario->desactivado_at)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'almacen.desactivado',
                    'almacen.reactivado',
                ]
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// SUCURSAL DE OTRA EMPRESA
// Protege contra inconsistencias donde el almacén apunta a
// una sucursal que pertenece a una empresa diferente.
// ---------------------------------------------------------

test('physical warehouse branch must belong to company', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $sucursalExterna = Sucursal::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'codigo' => 'EXTERNA',
        'nombre' => 'Sucursal externa',
        'es_principal' => true,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $almacenSecundario->update([
        'sucursal_id' => $sucursalExterna->id,
    ]);

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario->fresh(),
            actor: $propietario,
            activar: false,
            motivo: 'Intento con sucursal de otra empresa.'
        )
    )->toThrow(
        AuthorizationException::class
    );

    $almacenSecundario->refresh();

    expect($almacenSecundario->activo)
        ->toBeTrue()
        ->and($almacenSecundario->desactivado_por_id)
        ->toBeNull()
        ->and($almacenSecundario->desactivado_at)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'almacen.desactivado',
                    'almacen.reactivado',
                ]
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// REACTIVACIÓN DEL ALMACÉN PRINCIPAL
// Permite recuperar el almacén principal cuando la
// sucursal ya se encuentra nuevamente operativa.
// ---------------------------------------------------------

test('main warehouse can be reactivated inside active branch', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $almacenPrincipal->update([
        'activo' => false,
        'desactivado_por_id' => $propietario->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Desactivación previa durante cierre de sucursal.',
    ]);

    $actualizado = app(
        CambiarEstadoAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenPrincipal->fresh(),
        actor: $propietario,
        activar: true,
        motivo: 'Reapertura operativa de la sucursal.'
    );

    expect($actualizado->activo)
        ->toBeTrue()
        ->and($actualizado->tipo)
        ->toBe(Almacen::TIPO_PRINCIPAL)
        ->and($actualizado->desactivado_por_id)
        ->toBeNull()
        ->and($actualizado->desactivado_at)
        ->toBeNull()
        ->and($actualizado->motivo_desactivacion)
        ->toBeNull()
        ->and($actualizado->empresa_id)
        ->toBe($empresa->id)
        ->and($actualizado->sucursal_id)
        ->toBe($sucursal->id)
        ->and($almacenSecundario->fresh()?->activo)
        ->toBeTrue();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'almacen.reactivado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Almacen::class)
        ->and($auditoria->modelo_id)
        ->toBe($actualizado->id)
        ->and($auditoria->motivo)
        ->toBe(
            'Reapertura operativa de la sucursal.'
        );
});

// ---------------------------------------------------------
// EXISTENCIA FÍSICA PENDIENTE
// Impide desactivar un almacén mientras conserve unidades
// físicas, aunque ninguna de ellas esté reservada.
// ---------------------------------------------------------

test('warehouse with physical stock cannot be deactivated', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $existencia = crearExistenciaParaCambioEstadoAlmacen(
        usuario: $propietario,
        empresa: $empresa,
        almacen: $almacenSecundario,
        cantidad: '5.0000',
        cantidadReservada: '0.0000'
    );

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            activar: false,
            motivo: 'Intento de cierre con existencia física.'
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenSecundario->refresh();
    $existencia->refresh();

    expect($almacenSecundario->activo)
        ->toBeTrue()
        ->and($almacenSecundario->desactivado_por_id)
        ->toBeNull()
        ->and($almacenSecundario->desactivado_at)
        ->toBeNull()
        ->and($existencia->cantidad)
        ->toBe('5.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('0.0000')
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.desactivado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// EXISTENCIA RESERVADA PENDIENTE
// Impide desactivar un almacén mientras conserve unidades
// comprometidas mediante una reserva activa.
// ---------------------------------------------------------

test('warehouse with reserved stock cannot be deactivated', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $existencia = crearExistenciaParaCambioEstadoAlmacen(
        usuario: $propietario,
        empresa: $empresa,
        almacen: $almacenSecundario,
        cantidad: '3.0000',
        cantidadReservada: '3.0000'
    );

    expect(
        fn () => app(
            CambiarEstadoAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            activar: false,
            motivo: 'Intento de cierre con existencia reservada.'
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenSecundario->refresh();
    $existencia->refresh();

    expect($almacenSecundario->activo)
        ->toBeTrue()
        ->and($almacenSecundario->desactivado_por_id)
        ->toBeNull()
        ->and($existencia->cantidad)
        ->toBe('3.0000')
        ->and($existencia->cantidad_reservada)
        ->toBe('3.0000')
        ->and($existencia->cantidadDisponible())
        ->toBe('0.0000')
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.desactivado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// EXISTENCIA EN CERO
// Permite desactivar un almacén cuando todos sus saldos
// físicos y reservados se encuentran completamente en cero.
// ---------------------------------------------------------

test('warehouse with zero stock can be deactivated', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen();

    $existencia = crearExistenciaParaCambioEstadoAlmacen(
        usuario: $propietario,
        empresa: $empresa,
        almacen: $almacenSecundario,
        cantidad: '0.0000',
        cantidadReservada: '0.0000'
    );

    $actualizado = app(
        CambiarEstadoAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $propietario,
        activar: false,
        motivo: 'Cierre autorizado con inventario en cero.'
    );

    expect($actualizado->activo)
        ->toBeFalse()
        ->and($actualizado->desactivado_por_id)
        ->toBe($propietario->id)
        ->and($actualizado->desactivado_at)
        ->not->toBeNull()
        ->and($actualizado->motivo_desactivacion)
        ->toBe(
            'Cierre autorizado con inventario en cero.'
        )
        ->and($existencia->fresh()?->cantidad)
        ->toBe('0.0000')
        ->and(
            $existencia
                ->fresh()
                ?->cantidad_reservada
        )
        ->toBe('0.0000')
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'almacen.desactivado'
        )
        ->firstOrFail();

    expect($auditoria->modelo_tipo)
        ->toBe(Almacen::class)
        ->and($auditoria->modelo_id)
        ->toBe($almacenSecundario->id)
        ->and($auditoria->usuario_id)
        ->toBe($propietario->id);
});

// ---------------------------------------------------------
// REACTIVACIÓN CON EXISTENCIA HISTÓRICA
// La protección aplica únicamente a la desactivación. Un
// almacén inactivo puede reactivarse aunque conserve saldo.
// ---------------------------------------------------------

test('warehouse with stock can be reactivated', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaCambiarEstadoAlmacen(false);

    $existencia = crearExistenciaParaCambioEstadoAlmacen(
        usuario: $propietario,
        empresa: $empresa,
        almacen: $almacenSecundario,
        cantidad: '5.0000',
        cantidadReservada: '1.0000'
    );

    $actualizado = app(
        CambiarEstadoAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $propietario,
        activar: true,
        motivo: 'Reapertura del almacén con saldo histórico.'
    );

    expect($actualizado->activo)
        ->toBeTrue()
        ->and($actualizado->desactivado_por_id)
        ->toBeNull()
        ->and($actualizado->desactivado_at)
        ->toBeNull()
        ->and($actualizado->motivo_desactivacion)
        ->toBeNull()
        ->and($existencia->fresh()?->cantidad)
        ->toBe('5.0000')
        ->and(
            $existencia
                ->fresh()
                ?->cantidad_reservada
        )
        ->toBe('1.0000')
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.reactivado'
            )
            ->count()
    )->toBe(1);
});
