<?php

use App\Actions\Proveedores\CrearProveedor;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Pruebas de integración para la Action CrearProveedor.
 *
 * Valida los flujos de éxito y casos límite:
 * - Creación autorizada por roles y permisos.
 * - Normalización de datos (mayúsculas, minúsculas, espacios).
 * - Generación de registros de auditoría inmutables.
 * - Rechazo por falta de permisos, códigos duplicados o empresa inactiva.
 * - Almacenamiento de campos opcionales vacíos como null.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // Siembra la matriz completa de roles y permisos del sistema
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

/**
 * Prepara una empresa activa o inactiva con un usuario propietario administrador.
 *
 * @return array{0: User, 1: Empresa}
 */
function prepararEmpresaParaProveedor(
    bool $activa = true
): array {
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    $empresa = Empresa::query()->create([
        'nombre' => $activa
            ? 'Cano Computadoras'
            : 'Empresa inactiva',
        'activo' => $activa,
        'creado_por_id' => $propietario->id,
    ]);

    return [
        $propietario,
        $empresa,
    ];
}

// 1. Caso exitoso: Usuario autorizado crea proveedor, se normalizan datos y se genera auditoría con IP
test('authorized user can create supplier', function () {
    [
        $propietario,
        $empresa,
    ] = prepararEmpresaParaProveedor();

    // Simula una petición HTTP con IP y User-Agent para verificar su captura en la auditoría
    $request = Request::create(
        '/operacion/empresas/'
            .$empresa->id
            .'/proveedores',
        'POST',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '192.168.1.120',
            'HTTP_USER_AGENT' => 'Navegador de prueba',
        ]
    );

    $proveedor = app(
        CrearProveedor::class
    )->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        datos: [
            'codigo' => ' prov-001 ',
            'nombre' => ' Proveedor del Norte ',
            'razon_social' => ' Proveedor Norte SA de CV ',
            'rfc' => ' abc123456xyz ',
            'contacto' => ' Compras ',
            'telefono' => ' 6621234567 ',
            'correo' => ' VENTAS@PROVEEDOR.COM ',
            'direccion' => ' Dirección del proveedor ',
            'notas' => ' Entrega los días lunes. ',
        ],
        request: $request
    );

    // Verifica la correcta sanitización y almacenamiento de datos
    expect($proveedor->codigo)
        ->toBe('PROV-001')
        ->and($proveedor->nombre)
        ->toBe('Proveedor del Norte')
        ->and($proveedor->rfc)
        ->toBe('ABC123456XYZ')
        ->and($proveedor->correo)
        ->toBe('ventas@proveedor.com')
        ->and($proveedor->activo)
        ->toBeTrue()
        ->and($proveedor->creado_por_id)
        ->toBe($propietario->id);

    // Verifica que se haya insertado el log inmutable en la tabla 'auditorias'
    $auditoria = Auditoria::query()
        ->where('accion', 'proveedor.creado')
        ->firstOrFail();

    expect($auditoria->usuario_id)
        ->toBe($propietario->id)
        ->and($auditoria->modelo_id)
        ->toBe($proveedor->id)
        ->and($auditoria->modelo_tipo)
        ->toBe(Proveedor::class)
        ->and($auditoria->modulo)
        ->toBe('proveedores')
        ->and($auditoria->direccion_ip)
        ->toBe('192.168.1.120')
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'codigo' => 'PROV-001',
            'nombre' => 'Proveedor del Norte',
            'activo' => true,
        ]);
});

// 2. Permiso directo: Usuario sin rol de administrador pero con permiso Spatie 'productos.crear'
test('user with direct permission can create supplier', function () {
    [
        $propietario,
        $empresa,
    ] = prepararEmpresaParaProveedor();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    // Concede permiso directo delegable
    $supervisor->givePermissionTo(
        'productos.crear'
    );

    $proveedor = app(
        CrearProveedor::class
    )->ejecutar(
        empresa: $empresa,
        actor: $supervisor,
        datos: [
            'codigo' => 'PROV-002',
            'nombre' => 'Proveedor Centro',
        ]
    );

    expect($proveedor->codigo)
        ->toBe('PROV-002')
        ->and($proveedor->creado_por_id)
        ->toBe($supervisor->id);
});

// 3. Control de acceso: Usuario sin permiso recibe AuthorizationException y no se crean registros
test('user without permission cannot create supplier', function () {
    [
        $propietario,
        $empresa,
    ] = prepararEmpresaParaProveedor();

    $empleado = User::factory()->create();

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            CrearProveedor::class
        )->ejecutar(
            empresa: $empresa,
            actor: $empleado,
            datos: [
                'codigo' => 'PROV-003',
                'nombre' => 'Proveedor no autorizado',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para crear proveedores.'
    );

    expect(Proveedor::query()->count())
        ->toBe(0);

    expect(
        Auditoria::query()
            ->where('accion', 'proveedor.creado')
            ->count()
    )->toBe(0);
});

// 4. Unicidad insensible a mayúsculas/minúsculas: 'prov-001' colisiona con 'PROV-001'
test('supplier code must be unique ignoring case', function () {
    [
        $propietario,
        $empresa,
    ] = prepararEmpresaParaProveedor();

    $accion = app(CrearProveedor::class);

    $accion->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        datos: [
            'codigo' => 'PROV-001',
            'nombre' => 'Primer proveedor',
        ]
    );

    expect(
        fn () => $accion->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            datos: [
                'codigo' => 'prov-001',
                'nombre' => 'Proveedor duplicado',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Proveedor::query()->count())
        ->toBe(1);
});

// 5. Integridad de empresa: No se permite crear proveedores si la empresa está suspendida/inactiva
test('supplier cannot be created for inactive company', function () {
    [
        $propietario,
        $empresa,
    ] = prepararEmpresaParaProveedor(false);

    expect(
        fn () => app(
            CrearProveedor::class
        )->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            datos: [
                'codigo' => 'PROV-001',
                'nombre' => 'Proveedor inválido',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Proveedor::query()->count())
        ->toBe(0);
});

// 6. Validación de formato de correo
test('supplier email must be valid', function () {
    [
        $propietario,
        $empresa,
    ] = prepararEmpresaParaProveedor();

    expect(
        fn () => app(
            CrearProveedor::class
        )->ejecutar(
            empresa: $empresa,
            actor: $propietario,
            datos: [
                'codigo' => 'PROV-001',
                'nombre' => 'Proveedor inválido',
                'correo' => 'correo-no-valido',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(Proveedor::query()->count())
        ->toBe(0);
});

// 7. Conversión a null: Cadenas con solo espacios en blanco en campos opcionales se guardan como null
test('empty optional supplier fields are stored as null', function () {
    [
        $propietario,
        $empresa,
    ] = prepararEmpresaParaProveedor();

    $proveedor = app(
        CrearProveedor::class
    )->ejecutar(
        empresa: $empresa,
        actor: $propietario,
        datos: [
            'codigo' => 'PROV-001',
            'nombre' => 'Proveedor básico',
            'razon_social' => '   ',
            'rfc' => '   ',
            'contacto' => '   ',
            'telefono' => '   ',
            'correo' => '   ',
            'direccion' => '   ',
            'notas' => '   ',
        ]
    );

    expect($proveedor->razon_social)
        ->toBeNull()
        ->and($proveedor->rfc)
        ->toBeNull()
        ->and($proveedor->contacto)
        ->toBeNull()
        ->and($proveedor->telefono)
        ->toBeNull()
        ->and($proveedor->correo)
        ->toBeNull()
        ->and($proveedor->direccion)
        ->toBeNull()
        ->and($proveedor->notas)
        ->toBeNull();
});
