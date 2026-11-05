<?php

use App\Actions\Proveedores\ActualizarProveedor;
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
 * Pruebas de integración para la Action ActualizarProveedor.
 *
 * Cubre:
 * - Actualización exitosa con sanitización y auditoría comparativa (antes vs después).
 * - Autorización vía roles o permisos directos delegables.
 * - Validación de pertenencia multi-empresa (prevención de fugas entre tenants).
 * - Conservación de código propio y rechazo de códigos duplicados ajenos.
 * - Detección de cambios obligatorios (no-op rejection).
 * - Manejo de empresas inactivas y conversión de opcionales vacíos a null.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(
        RolesAndPermissionsSeeder::class
    );
});

/**
 * Prepara una empresa y un proveedor base para las pruebas de actualización.
 *
 * @return array{0: User, 1: Empresa, 2: Proveedor}
 */
function prepararProveedorParaActualizar(
    bool $empresaActiva = true
): array {
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    $empresa = Empresa::query()->create([
        'nombre' => $empresaActiva
            ? 'Cano Computadoras'
            : 'Empresa inactiva',
        'activo' => $empresaActiva,
        'creado_por_id' => $propietario->id,
    ]);

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor inicial',
        'razon_social' => 'Proveedor Inicial SA de CV',
        'rfc' => 'ABC123456XYZ',
        'contacto' => 'Contacto inicial',
        'telefono' => '6621234567',
        'correo' => 'inicial@proveedor.test',
        'direccion' => 'Dirección inicial',
        'notas' => 'Notas iniciales',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    return [
        $propietario,
        $empresa,
        $proveedor,
    ];
}

// 1. Caso de éxito: Actualización completa con normalización y auditoría comparativa
test('authorized user can update supplier', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    $request = Request::create(
        '/operacion/empresas/'
            .$empresa->id
            .'/proveedores/'
            .$proveedor->id,
        'PUT',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '192.168.1.150',
            'HTTP_USER_AGENT' => 'Navegador de prueba',
        ]
    );

    $actualizado = app(
        ActualizarProveedor::class
    )->ejecutar(
        empresa: $empresa,
        proveedor: $proveedor,
        actor: $propietario,
        datos: [
            'codigo' => ' prov-actualizado ',
            'nombre' => ' Proveedor actualizado ',
            'razon_social' => ' Proveedor Actualizado SA de CV ',
            'rfc' => ' xyz987654abc ',
            'contacto' => ' Nuevo contacto ',
            'telefono' => ' 6629876543 ',
            'correo' => ' VENTAS@ACTUALIZADO.TEST ',
            'direccion' => ' Nueva dirección ',
            'notas' => ' Nuevas notas ',
        ],
        request: $request
    );

    // Comprueba valores normalizados en base de datos
    expect($actualizado->codigo)
        ->toBe('PROV-ACTUALIZADO')
        ->and($actualizado->nombre)
        ->toBe('Proveedor actualizado')
        ->and($actualizado->rfc)
        ->toBe('XYZ987654ABC')
        ->and($actualizado->correo)
        ->toBe('ventas@actualizado.test')
        ->and($actualizado->actualizado_por_id)
        ->toBe($propietario->id)
        ->and($actualizado->activo)
        ->toBeTrue();

    // Comprueba el registro de auditoría con valores anteriores vs nuevos
    $auditoria = Auditoria::query()
        ->where(
            'accion',
            'proveedor.actualizado'
        )
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
        ->toBe('192.168.1.150')
        ->and($auditoria->valores_anteriores)
        ->toMatchArray([
            'codigo' => 'PROV-001',
            'nombre' => 'Proveedor inicial',
            'correo' => 'inicial@proveedor.test',
        ])
        ->and($auditoria->valores_nuevos)
        ->toMatchArray([
            'codigo' => 'PROV-ACTUALIZADO',
            'nombre' => 'Proveedor actualizado',
            'correo' => 'ventas@actualizado.test',
        ]);
});

// 2. Permiso directo: Usuario supervisor con permiso 'productos.actualizar'
test('user with direct permission can update supplier', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    $supervisor = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $supervisor->assignRole('supervisor');

    $supervisor->givePermissionTo(
        'productos.actualizar'
    );

    $actualizado = app(
        ActualizarProveedor::class
    )->ejecutar(
        empresa: $empresa,
        proveedor: $proveedor,
        actor: $supervisor,
        datos: [
            'codigo' => 'PROV-001',
            'nombre' => 'Proveedor modificado',
            'razon_social' => 'Proveedor Inicial SA de CV',
            'rfc' => 'ABC123456XYZ',
            'contacto' => 'Contacto inicial',
            'telefono' => '6621234567',
            'correo' => 'inicial@proveedor.test',
            'direccion' => 'Dirección inicial',
            'notas' => 'Notas iniciales',
        ]
    );

    expect($actualizado->nombre)
        ->toBe('Proveedor modificado')
        ->and($actualizado->actualizado_por_id)
        ->toBe($supervisor->id);
});

// 3. Control de acceso: Usuario sin permiso no puede editar
test('user without permission cannot update supplier', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    $empleado = User::factory()->create();

    $empleado->assignRole('empleado');

    expect(
        fn () => app(
            ActualizarProveedor::class
        )->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $empleado,
            datos: [
                'codigo' => 'PROV-001',
                'nombre' => 'Cambio no autorizado',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'No tienes permiso para actualizar proveedores.'
    );

    expect($proveedor->fresh()?->nombre)
        ->toBe('Proveedor inicial');

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'proveedor.actualizado'
            )
            ->count()
    )->toBe(0);
});

// 4. Seguridad multi-tenant: Impide que una empresa manipule proveedores de otra empresa
test('supplier cannot be updated through another company', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    $otraEmpresa = Empresa::query()->create([
        'nombre' => 'Empresa secundaria',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            ActualizarProveedor::class
        )->ejecutar(
            empresa: $otraEmpresa,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'codigo' => 'PROV-001',
                'nombre' => 'Proveedor trasladado',
            ]
        )
    )->toThrow(
        AuthorizationException::class,
        'El proveedor no pertenece a la empresa indicada.'
    );

    expect($proveedor->fresh()?->empresa_id)
        ->toBe($empresa->id);
});

// 5. Unicidad de clave: Impide cambiar el código al de OTRO proveedor existente en la empresa
test('updated supplier code must be unique inside company', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-002',
        'nombre' => 'Segundo proveedor',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    expect(
        fn () => app(
            ActualizarProveedor::class
        )->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'codigo' => 'prov-002',
                'nombre' => 'Proveedor inicial',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect($proveedor->fresh()?->codigo)
        ->toBe('PROV-001');
});

// 6. Conservación de clave: El proveedor puede mantener su propio código mientras edita otros campos
test('supplier can keep same code when updating other attributes', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    $actualizado = app(
        ActualizarProveedor::class
    )->ejecutar(
        empresa: $empresa,
        proveedor: $proveedor,
        actor: $propietario,
        datos: [
            'codigo' => 'prov-001',
            'nombre' => 'Nombre cambiado manteniendo codigo',
            'razon_social' => $proveedor->razon_social,
            'rfc' => $proveedor->rfc,
            'contacto' => $proveedor->contacto,
            'telefono' => $proveedor->telefono,
            'correo' => $proveedor->correo,
            'direccion' => $proveedor->direccion,
            'notas' => $proveedor->notas,
        ]
    );

    expect($actualizado->codigo)
        ->toBe('PROV-001')
        ->and($actualizado->nombre)
        ->toBe('Nombre cambiado manteniendo codigo');
});

// 7. Detección de no-cambios: Enviar exactamente los mismos datos lanza excepción y no audita
test('supplier update requires an actual change', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    expect(
        fn () => app(
            ActualizarProveedor::class
        )->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'codigo' => 'PROV-001',
                'nombre' => 'Proveedor inicial',
                'razon_social' => 'Proveedor Inicial SA de CV',
                'rfc' => 'ABC123456XYZ',
                'contacto' => 'Contacto inicial',
                'telefono' => '6621234567',
                'correo' => 'inicial@proveedor.test',
                'direccion' => 'Dirección inicial',
                'notas' => 'Notas iniciales',
            ]
        )
    )->toThrow(
        ValidationException::class
    );

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'proveedor.actualizado'
            )
            ->count()
    )->toBe(0);
});

// 8. Integridad de empresa: No se permite editar proveedores de empresas inactivas
test('supplier cannot be updated for inactive company', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar(empresaActiva: false);

    expect(
        fn () => app(
            ActualizarProveedor::class
        )->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'codigo' => 'PROV-001',
                'nombre' => 'Nuevo nombre en empresa inactiva',
            ]
        )
    )->toThrow(
        ValidationException::class
    );
});

// 9. Validación de correo
test('supplier email must be valid on update', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    expect(
        fn () => app(
            ActualizarProveedor::class
        )->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'codigo' => 'PROV-001',
                'nombre' => 'Proveedor con correo invalido',
                'correo' => 'correo-no-valido',
            ]
        )
    )->toThrow(
        ValidationException::class
    );
});

// 10. Validación de sintaxis de código (no caracteres especiales no permitidos)
test('supplier code format is validated on update', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    expect(
        fn () => app(
            ActualizarProveedor::class
        )->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $propietario,
            datos: [
                'codigo' => 'PROV#001!*',
                'nombre' => 'Proveedor codigo invalido',
            ]
        )
    )->toThrow(
        ValidationException::class
    );
});

// 11. Limpieza de opcionales: Cadenas vacías o espacios se persisten como null
test('empty optional supplier fields are stored as null on update', function () {
    [
        $propietario,
        $empresa,
        $proveedor,
    ] = prepararProveedorParaActualizar();

    $actualizado = app(
        ActualizarProveedor::class
    )->ejecutar(
        empresa: $empresa,
        proveedor: $proveedor,
        actor: $propietario,
        datos: [
            'codigo' => 'PROV-001',
            'nombre' => 'Proveedor con opcionales vacios',
            'razon_social' => '   ',
            'rfc' => '   ',
            'contacto' => '   ',
            'telefono' => '   ',
            'correo' => '   ',
            'direccion' => '   ',
            'notas' => '   ',
        ]
    );

    expect($actualizado->razon_social)
        ->toBeNull()
        ->and($actualizado->rfc)
        ->toBeNull()
        ->and($actualizado->contacto)
        ->toBeNull()
        ->and($actualizado->telefono)
        ->toBeNull()
        ->and($actualizado->correo)
        ->toBeNull()
        ->and($actualizado->direccion)
        ->toBeNull()
        ->and($actualizado->notas)
        ->toBeNull();
});
