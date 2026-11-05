<?php

use App\Actions\Almacenes\CrearAlmacen;
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
// Crea propietario, empresa y sucursal activa con su
// almacén principal previamente aprovisionado.
// ---------------------------------------------------------

/**
 * @return array{
 *     0: User,
 *     1: Empresa,
 *     2: Sucursal,
 *     3: Almacen
 * }
 */
function prepararEntornoParaCrearAlmacen(): array
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

    return [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ];
}

// ---------------------------------------------------------
// CREACIÓN CORRECTA
// Un administrador autorizado puede crear un almacén
// secundario en una sucursal activa de su empresa.
// ---------------------------------------------------------

test('authorized user can create secondary warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    $almacen = app(
        CrearAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        sucursal: $sucursal,
        actor: $propietario,
        datos: [
            'codigo' => ' secundario-01 ',
            'nombre' => ' Almacén de resguardo ',
            'tipo' => ' SECUNDARIO ',
            'permite_existencias' => true,
            'motivo' => ' Ampliación de capacidad operativa. ',
        ]
    );

    expect($almacen->empresa_id)
        ->toBe($empresa->id)
        ->and($almacen->sucursal_id)
        ->toBe($sucursal->id)
        ->and($almacen->codigo)
        ->toBe('SECUNDARIO-01')
        ->and($almacen->nombre)
        ->toBe('Almacén de resguardo')
        ->and($almacen->tipo)
        ->toBe(Almacen::TIPO_SECUNDARIO)
        ->and($almacen->es_virtual)
        ->toBeFalse()
        ->and($almacen->permite_existencias)
        ->toBeTrue()
        ->and($almacen->activo)
        ->toBeTrue()
        ->and($almacen->creado_por_id)
        ->toBe($propietario->id);

    expect($sucursal->almacenes()->count())
        ->toBe(2)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// AUDITORÍA
// La creación del almacén debe registrar la operación,
// los valores normalizados y el motivo sin espacios.
// ---------------------------------------------------------

test('warehouse creation stores audit information', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    $almacen = app(
        CrearAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        sucursal: $sucursal,
        actor: $propietario,
        datos: [
            'codigo' => ' devoluciones-01 ',
            'nombre' => ' Almacén de devoluciones ',
            'tipo' => ' DEVOLUCIONES ',
            'permite_existencias' => true,
            'motivo' => ' Control de productos devueltos. ',
        ]
    );

    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'almacen.creado'
        )
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Almacen::class)
        ->and($auditoria->modelo_id)
        ->toBe($almacen->id)
        ->and($auditoria->modulo)
        ->toBe('almacenes')
        ->and($auditoria->motivo)
        ->toBe('Control de productos devueltos.')
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'codigo' => 'DEVOLUCIONES-01',
            'nombre' => 'Almacén de devoluciones',
            'tipo' => Almacen::TIPO_DEVOLUCIONES,
            'es_virtual' => false,
            'permite_existencias' => true,
        ]);
});

// ---------------------------------------------------------
// PERMISO DIRECTO
// Un supervisor puede crear almacenes cuando recibe
// almacenes.crear de manera individual.
// ---------------------------------------------------------

test('user with direct permission can create warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'almacenes.crear'
    );

    $almacen = app(
        CrearAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        sucursal: $sucursal,
        actor: $supervisor,
        datos: [
            'codigo' => 'MERMA-01',
            'nombre' => 'Almacén de merma',
            'tipo' => Almacen::TIPO_MERMA,
            'permite_existencias' => true,
            'motivo' => 'Separación de producto dañado.',
        ]
    );

    expect($almacen->tipo)
        ->toBe(Almacen::TIPO_MERMA)
        ->and($almacen->creado_por_id)
        ->toBe($supervisor->id);
});

// ---------------------------------------------------------
// CONTROL DE ACCESO
// Un usuario sin permiso no puede crear almacenes.
// ---------------------------------------------------------

test('user without permission cannot create warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    $empleado = User::factory()->create();

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $empleado,
            datos: [
                'codigo' => 'ALM-02',
                'nombre' => 'Almacén no autorizado',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'motivo' => 'Intento sin permiso',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para crear almacenes.'
    );
});

// ---------------------------------------------------------
// AISLAMIENTO MULTI-TENANT
// No se puede crear un almacén si la sucursal es de otra empresa.
// ---------------------------------------------------------

test('branch must belong to company', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Otra Empresa',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $otraEmpresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'ALM-02',
                'nombre' => 'Almacén ajeno',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'motivo' => 'Intento de empresa cruzada',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'La sucursal no pertenece a la empresa indicada.'
    );
});

// ---------------------------------------------------------
// UNICIDAD DE CÓDIGO
// El código de almacén no puede repetirse en la misma empresa.
// ---------------------------------------------------------

test('warehouse code must be unique inside company', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'ALM-MATRIZ',
                'nombre' => 'Almacén duplicado',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'motivo' => 'Intento de código repetido',
            ]
        )
    )->toThrow(
        ValidationException::class
    );
});

// ---------------------------------------------------------
// SUCURSAL INACTIVA
// No se pueden agregar almacenes a una sucursal inactiva.
// ---------------------------------------------------------

test('warehouse cannot be created in inactive branch', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    $sucursal->update(['activo' => false]);

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'ALM-INACTIVA',
                'nombre' => 'Almacén sucursal inactiva',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'motivo' => 'Intento en sucursal inactiva',
            ]
        )
    )->toThrow(
        ValidationException::class
    );
});

// ---------------------------------------------------------
// ALMACÉN PRINCIPAL ÚNICO
// No se puede crear un segundo almacén principal en una sucursal.
// ---------------------------------------------------------

test('branch cannot have multiple principal warehouses', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'ALM-PRINCIPAL-2',
                'nombre' => 'Segundo principal',
                'tipo' => Almacen::TIPO_PRINCIPAL,
                'motivo' => 'Intento de duplicar almacén principal',
            ]
        )
    )->toThrow(
        ValidationException::class
    );
});

// ---------------------------------------------------------
// JUSTIFICACIÓN OBLIGATORIA
// La creación del almacén requiere motivo no vacío.
// ---------------------------------------------------------

test('warehouse creation requires a reason', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'ALM-SIN-MOTIVO',
                'nombre' => 'Almacén sin motivo',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'motivo' => '   ',
            ]
        )
    )->toThrow(
        ValidationException::class
    );
});

// ---------------------------------------------------------
// TIPO INVÁLIDO
// Solo pueden crearse almacenes con alguno de los tipos
// físicos permitidos por el dominio.
// ---------------------------------------------------------

test('warehouse creation requires a valid type', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'CONSIGNACION-01',
                'nombre' => 'Almacén de consignación',
                'tipo' => 'consignacion',
                'permite_existencias' => true,
                'motivo' => 'Creación con tipo no permitido.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Almacen::query()->count())
        ->toBe(1)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.creado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN PRINCIPAL DUPLICADO
// Cada sucursal solamente puede tener un almacén marcado
// con el tipo principal.
// ---------------------------------------------------------

test('branch cannot have more than one main warehouse', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'PRINCIPAL-02',
                'nombre' => 'Segundo almacén principal',
                'tipo' => Almacen::TIPO_PRINCIPAL,
                'permite_existencias' => true,
                'motivo' => 'Intento de segundo almacén principal.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

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

    expect($almacenPrincipal->fresh()?->tipo)
        ->toBe(Almacen::TIPO_PRINCIPAL);

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.creado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// ALMACÉN DE TRÁNSITO PROTEGIDO
// Los almacenes virtuales de tránsito son administrados
// automáticamente por la apertura de sucursales.
// ---------------------------------------------------------

test('transit warehouse cannot be created manually', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'TRANSITO-02',
                'nombre' => 'Segundo almacén de tránsito',
                'tipo' => Almacen::TIPO_TRANSITO,
                'permite_existencias' => true,
                'motivo' => 'Intento de creación manual.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Almacen::query()
            ->where(
                'tipo',
                Almacen::TIPO_TRANSITO
            )
            ->count()
    )->toBe(0);

    expect(Almacen::query()->count())
        ->toBe(1)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// FORMATO DEL CÓDIGO
// El código solamente admite letras, números y guiones.
// También debe respetar el máximo de 40 caracteres.
// ---------------------------------------------------------

test('warehouse creation requires a valid code', function (
    string $codigo
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => $codigo,
                'nombre' => 'Almacén secundario',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Validación del código.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Almacen::query()->count())
        ->toBe(1)
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
// NOMBRE OBLIGATORIO
// Todo almacén necesita un nombre descriptivo válido.
// ---------------------------------------------------------

test('warehouse creation requires a name', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => '   ',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Validación de nombre obligatorio.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Almacen::query()->count())
        ->toBe(1)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// NOMBRE DEMASIADO LARGO
// El nombre debe respetar el límite de 150 caracteres de
// la columna definida en la base de datos.
// ---------------------------------------------------------

test('warehouse name cannot exceed maximum length', function () {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => str_repeat('A', 151),
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => 'Validación de longitud del nombre.',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Almacen::query()->count())
        ->toBe(1)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();
});

// ---------------------------------------------------------
// MOTIVO OBLIGATORIO Y LONGITUD MÁXIMA
// Toda creación debe incluir una justificación válida de
// hasta 1000 caracteres para la auditoría.
// ---------------------------------------------------------

test('warehouse creation requires a valid audit reason', function (
    string $motivo
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    expect(
        fn () => app(
            CrearAlmacen::class
        )->ejecutar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $propietario,
            datos: [
                'codigo' => 'SECUNDARIO-01',
                'nombre' => 'Almacén secundario',
                'tipo' => Almacen::TIPO_SECUNDARIO,
                'permite_existencias' => true,
                'motivo' => $motivo,
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Almacen::query()->count())
        ->toBe(1)
        ->and($almacenPrincipal->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'almacen.creado'
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
// CONVERSIÓN DE PERMITE EXISTENCIAS
// Convierte representaciones booleanas recibidas desde un
// formulario sin guardar cadenas como valores incorrectos.
// ---------------------------------------------------------

test('warehouse stock flag is normalized correctly', function (
    mixed $valor,
    bool $esperado
) {
    [
        $propietario,
        $empresa,
        $sucursal,
        $almacenPrincipal,
    ] = prepararEntornoParaCrearAlmacen();

    $almacen = app(
        CrearAlmacen::class
    )->ejecutar(
        empresa: $empresa,
        sucursal: $sucursal,
        actor: $propietario,
        datos: [
            'codigo' => 'SECUNDARIO-01',
            'nombre' => 'Almacén secundario',
            'tipo' => Almacen::TIPO_SECUNDARIO,
            'permite_existencias' => $valor,
            'motivo' => 'Validación del indicador de existencias.',
        ]
    );

    expect($almacen->permite_existencias)
        ->toBe($esperado)
        ->and($almacen->es_virtual)
        ->toBeFalse()
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
