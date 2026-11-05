<?php

use App\Actions\Almacenes\ActualizarAlmacen;
use App\Models\Almacen;
use App\Models\Auditoria;
use App\Models\Empresa;
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
// y almacén secundario que será actualizado.
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
function prepararEntornoParaActualizarAlmacen(): array
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
        'activo' => true,
        'creado_por_id' => $propietario->id,
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
// ACTUALIZACIÓN CORRECTA
// Comprueba la normalización de los atributos editables y
// la conservación de empresa, sucursal y estado.
// ---------------------------------------------------------

test('authorized user can update warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    $actualizado = app(
        ActualizarAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $propietario,
        datos: [
            'codigo' => ' resguardo-01 ',
            'nombre' => ' Almacén de resguardo actualizado ',
            'tipo' => ' DEVOLUCIONES ',
            'permite_existencias' => 'false',
            'motivo' => ' Reorganización operativa del almacén. ',
        ]
    );

    expect($actualizado->codigo)
        ->toBe('RESGUARDO-01')
        ->and($actualizado->nombre)
        ->toBe('Almacén de resguardo actualizado')
        ->and($actualizado->tipo)
        ->toBe(Almacen::TIPO_DEVOLUCIONES)
        ->and($actualizado->permite_existencias)
        ->toBeFalse()
        ->and($actualizado->empresa_id)
        ->toBe($empresa->id)
        ->and($actualizado->sucursal_id)
        ->toBe($sucursal->id)
        ->and($actualizado->es_virtual)
        ->toBeFalse()
        ->and($actualizado->activo)
        ->toBeTrue()
        ->and($actualizado->actualizado_por_id)
        ->toBe($propietario->id);

    expect($almacenPrincipal->fresh()?->tipo)
        ->toBe(Almacen::TIPO_PRINCIPAL);
});

// ---------------------------------------------------------
// AUDITORÍA
// Verifica los valores anteriores, los valores nuevos y
// el motivo normalizado de la actualización.
// ---------------------------------------------------------

test('warehouse update stores audit information', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    $actualizado = app(
        ActualizarAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $propietario,
        datos: [
            'codigo' => 'MERMA-01',
            'nombre' => 'Almacén de merma',
            'tipo' => Almacen::TIPO_MERMA,
            'permite_existencias' => true,
            'motivo' => ' Clasificación de productos dañados. ',
        ]
    );

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'almacen.actualizado'
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
        ->toBe('Clasificación de productos dañados.')
        ->and($auditoria->valores_anteriores)
        ->toMatchArray([
            'codigo' => 'SECUNDARIO-01',
            'nombre' => 'Almacén secundario',
            'tipo' => Almacen::TIPO_SECUNDARIO,
            'permite_existencias' => true,
        ])
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'codigo' => 'MERMA-01',
            'nombre' => 'Almacén de merma',
            'tipo' => Almacen::TIPO_MERMA,
            'permite_existencias' => true,
        ]);

    expect($actualizado->empresa_id)
        ->toBe($empresa->id)
        ->and($actualizado->sucursal_id)
        ->toBe($sucursal->id)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor puede actualizar almacenes cuando recibe
// almacenes.actualizar de forma individual.
// ---------------------------------------------------------

test('user with direct permission can update warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'almacenes.actualizar'
    );

    $actualizado = app(
        ActualizarAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $supervisor,
        datos: [
            'codigo' => 'SECUNDARIO-01',
            'nombre' => 'Almacén secundario supervisado',
            'tipo' => Almacen::TIPO_SECUNDARIO,
            'permite_existencias' => true,
            'motivo' => 'Actualización autorizada al supervisor.',
        ]
    );

    expect($actualizado->nombre)
        ->toBe('Almacén secundario supervisado')
        ->and($actualizado->actualizado_por_id)
        ->toBe($supervisor->id)
        ->and($actualizado->empresa_id)
        ->toBe($empresa->id)
        ->and($actualizado->sucursal_id)
        ->toBe($sucursal->id)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// USUARIO SIN PERMISO
// Impide actualizar almacenes cuando el actor no tiene
// asignado el permiso almacenes.actualizar.
// ---------------------------------------------------------

test('user without permission cannot update warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $empleado,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => 'Cambio no autorizado',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Actualización no autorizada.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para actualizar almacenes.'
    );

    expect($almacenSecundario->fresh()?->nombre)
        ->toBe('Almacén secundario')
        ->and($almacenSecundario->fresh()?->actualizado_por_id)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN DE OTRA EMPRESA
// Evita actualizar un almacén utilizando una empresa que
// no es propietaria del registro.
// ---------------------------------------------------------

test('warehouse cannot be updated through another company', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $otraEmpresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => 'Almacén trasladado',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Intento con empresa incorrecta.',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El almacén no pertenece a la empresa indicada.'
    );

    expect($almacenSecundario->fresh()?->empresa_id)
        ->toBe($empresa->id)
        ->and($almacenSecundario->fresh()?->nombre)
        ->toBe('Almacén secundario')
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// EMPRESA INACTIVA
// No permite modificar almacenes que pertenecen a una
// empresa desactivada.
// ---------------------------------------------------------

test('warehouse of inactive company cannot be updated', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    $empresa->update([
        'activo' => false,
        'desactivado_por_id' => $propietario->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Empresa temporalmente suspendida.',
    ]);

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa->fresh(),
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => 'Almacén modificado',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Actualización durante la suspensión.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->nombre)
        ->toBe('Almacén secundario')
        ->and($almacenSecundario->fresh()?->actualizado_por_id)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN INACTIVO
// Un almacén desactivado debe reactivarse antes de poder
// modificar sus datos operativos.
// ---------------------------------------------------------

test('inactive warehouse cannot be updated', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    $almacenSecundario->update([
        'activo' => false,
        'desactivado_por_id' => $propietario->id,
        'desactivado_at' => now(),
        'motivo_desactivacion' => 'Almacén temporalmente fuera de operación.',
    ]);

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario->fresh(),
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => 'Almacén modificado',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Actualización durante la suspensión.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->nombre)
        ->toBe('Almacén secundario')
        ->and($almacenSecundario->fresh()?->activo)
        ->toBeFalse()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// CÓDIGO DUPLICADO
// Impide utilizar un código que ya pertenece a otro
// almacén de la misma empresa.
// ---------------------------------------------------------

test('updated warehouse code must be unique inside company', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => ' alm-matriz ',
                'nombre' => 'Almacén secundario',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Intento de utilizar un código duplicado.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->codigo)
        ->toBe('SECUNDARIO-01')
        ->and($almacenPrincipal->fresh()?->codigo)
        ->toBe('ALM-MATRIZ');

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN PRINCIPAL DUPLICADO
// Impide convertir un almacén secundario en principal
// cuando la sucursal ya cuenta con uno.
// ---------------------------------------------------------

test('secondary warehouse cannot become duplicate main warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => 'Segundo almacén principal',
                'tipo' => Almacen::TIPO_PRINCIPAL,
                'permite_existencias' => true,
                'motivo' => 'Intento de convertirlo en principal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->tipo)
        ->toBe(Almacen::TIPO_SECUNDARIO)
        ->and($almacenPrincipal->fresh()?->tipo)
        ->toBe(Almacen::TIPO_PRINCIPAL);

    expect(
        Almacen::query()
            ->where(
                'sucursal_id',
                $sucursal->id
            )
            ->where(
                'tipo',
                Almacen::TIPO_PRINCIPAL
            )
            ->count()
    )->toBe(1);

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN VIRTUAL DE TRÁNSITO
// El almacén de tránsito es administrado automáticamente
// por el sistema y no puede actualizarse manualmente.
// ---------------------------------------------------------

test('transit warehouse cannot be updated manually', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

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
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenTransito,
            actor: $propietario,
            datos: [
                'codigo' => 'TRANSITO-ACTUALIZADO',
                'nombre' => 'Tránsito actualizado',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => false,
                'motivo' => 'Intento de modificación manual.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenTransito->refresh();

    expect($almacenTransito->codigo)
        ->toBe('TRANSITO')
        ->and($almacenTransito->nombre)
        ->toBe('Almacén virtual de tránsito')
        ->and($almacenTransito->tipo)
        ->toBe(Almacen::TIPO_TRANSITO)
        ->and($almacenTransito->es_virtual)
        ->toBeTrue()
        ->and($almacenTransito->permite_existencias)
        ->toBeTrue()
        ->and($almacenTransito->actualizado_por_id)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue()
        ->and($almacenSecundario->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// TIPO INVÁLIDO
// Únicamente se admiten los tipos físicos definidos por
// el dominio para almacenes asociados a una sucursal.
// ---------------------------------------------------------

test('warehouse update requires a valid type', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => 'Almacén modificado',
                'tipo' => 'consignacion',
                'permite_existencias' => true,
                'motivo' => 'Intento con tipo no permitido.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->tipo)
        ->toBe(Almacen::TIPO_SECUNDARIO)
        ->and($almacenSecundario->fresh()?->nombre)
        ->toBe('Almacén secundario')
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// CONVERSIÓN MANUAL A TRÁNSITO
// Un almacén físico no puede convertirse manualmente en
// el almacén virtual de tránsito de la empresa.
// ---------------------------------------------------------

test('physical warehouse cannot become transit warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => 'TRANSITO-02',
                'nombre' => 'Almacén de tránsito',
                'tipo' => Almacen::TIPO_TRANSITO,
                'permite_existencias' => true,
                'motivo' => 'Intento de conversión a tránsito.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->tipo)
        ->toBe(Almacen::TIPO_SECUNDARIO)
        ->and($almacenSecundario->fresh()?->es_virtual)
        ->toBeFalse()
        ->and($almacenPrincipal->fresh()?->tipo)
        ->toBe(Almacen::TIPO_PRINCIPAL);

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// FORMATO DEL CÓDIGO
// El código debe contener únicamente letras, números o
// guiones y no puede superar los 40 caracteres.
// ---------------------------------------------------------

test('warehouse update requires a valid code', function (
    string $codigo
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => $codigo,
                'nombre' => 'Almacén modificado',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Validación del código.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->codigo)
        ->toBe('SECUNDARIO-01')
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
})->with([
    'código vacío' => [
        '   ',
    ],
    'código con espacios interiores' => [
        'ALMACEN NORTE',
    ],
    'código con caracteres especiales' => [
        'ALMACEN@NORTE',
    ],
    'código mayor de cuarenta caracteres' => [
        str_repeat('A', 41),
    ],
]);

// ---------------------------------------------------------
// NOMBRE INVÁLIDO
// El nombre es obligatorio y debe respetar el límite de
// 150 caracteres definido en la base de datos.
// ---------------------------------------------------------

test('warehouse update requires a valid name', function (
    string $nombre
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => $nombre,
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Validación del nombre.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->nombre)
        ->toBe('Almacén secundario')
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
})->with([
    'nombre vacío' => [
        '   ',
    ],
    'nombre mayor de ciento cincuenta caracteres' => [
        str_repeat('A', 151),
    ],
]);

// ---------------------------------------------------------
// MOTIVO INVÁLIDO
// Toda actualización necesita una justificación válida de
// hasta 1000 caracteres para la auditoría.
// ---------------------------------------------------------

test('warehouse update requires a valid audit reason', function (
    string $motivo
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => 'Almacén modificado',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => $motivo,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($almacenSecundario->fresh()?->nombre)
        ->toBe('Almacén secundario')
        ->and($almacenSecundario->fresh()?->actualizado_por_id)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
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
// ACTUALIZACIÓN SIN CAMBIOS
// Evita guardar y auditar una operación cuando todos los
// valores normalizados coinciden con los actuales.
// ---------------------------------------------------------

test('warehouse update requires an actual change', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    expect(
        fn () => app(
            ActualizarAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            almacen: $almacenSecundario,
            actor: $propietario,
            datos: [
                'codigo' => ' secundario-01 ',
                'nombre' => ' Almacén secundario ',
                'tipo' => ' SECUNDARIO ',
                'permite_existencias' => 'true',
                'motivo' => 'Intento de guardar sin cambios.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    $almacenSecundario->refresh();

    expect($almacenSecundario->codigo)
        ->toBe('SECUNDARIO-01')
        ->and($almacenSecundario->nombre)
        ->toBe('Almacén secundario')
        ->and($almacenSecundario->tipo)
        ->toBe(Almacen::TIPO_SECUNDARIO)
        ->and($almacenSecundario->permite_existencias)
        ->toBeTrue()
        ->and($almacenSecundario->actualizado_por_id)
        ->toBeNull()
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.actualizado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// CONVERSIÓN DE PERMITE EXISTENCIAS
// Normaliza correctamente los valores booleanos recibidos
// desde formularios antes de guardar la actualización.
// ---------------------------------------------------------

test('warehouse update normalizes stock flag', function (
    mixed $valor,
    bool $esperado
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
        $almacenSecundario,
    ] = prepararEntornoParaActualizarAlmacen();

    $actualizado = app(
        ActualizarAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        almacen: $almacenSecundario,
        actor: $propietario,
        datos: [
            'codigo' => 'SECUNDARIO-01',
            'nombre' => $esperado
                ? 'Almacén con existencias'
                : 'Almacén sin existencias',
            'tipo' => Almacen::TIPO_SECUNDARIO,
            'permite_existencias' => $valor,
            'motivo' => 'Actualización del manejo de existencias.',
        ]
    );

    expect($actualizado->permite_existencias)
        ->toBe($esperado)
        ->and($actualizado->nombre)
        ->toBe(
            $esperado
                ? 'Almacén con existencias'
                : 'Almacén sin existencias'
        )
        ->and($actualizado->es_virtual)
        ->toBeFalse()
        ->and($actualizado->empresa_id)
        ->toBe($empresa->id)
        ->and($actualizado->sucursal_id)
        ->toBe($sucursal->id)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
})->with([
    'cadena verdadera' => [
        'true',
        true,
    ],
    'cadena uno' => [
        '1',
        true,
    ],
    'cadena falsa' => [
        'false',
        false,
    ],
    'cadena cero' => [
        '0',
        false,
    ],
]);
