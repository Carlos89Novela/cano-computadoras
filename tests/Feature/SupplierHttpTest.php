<?php

use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
// USUARIO PROPIETARIO
// Crea una cuenta propietaria con acceso global concedido
// mediante Gate::before().
// ---------------------------------------------------------

function propietarioParaProveedorHttp(): User
{
    $propietario = User::factory()->create([
        'es_propietario' => true,
        'email_verified_at' => now(),
    ]);

    $propietario->assignRole('administrador');

    return $propietario;
}

// ---------------------------------------------------------
// EMPRESA DE PRUEBA
// Crea una empresa activa asociada con el propietario.
// ---------------------------------------------------------

function empresaParaProveedorHttp(
    User $propietario,
    string $nombre = 'Cano Computadoras'
): Empresa {
    return Empresa::query()->create([
        'nombre' => $nombre,
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);
}

// ---------------------------------------------------------
// PRUEBA DEL LISTADO
// Confirma que el propietario puede abrir la pantalla y ver
// los proveedores pertenecientes a la empresa.
// ---------------------------------------------------------

test('owner can view company suppliers', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor del Norte',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $this
        ->actingAs($propietario)
        ->get(
            route(
                'proveedores.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertOk()
        ->assertSee('Proveedores de')
        ->assertSee($empresa->nombre)
        ->assertSee($proveedor->nombre)
        ->assertSee($proveedor->codigo)
        ->assertSee('Nuevo proveedor');
});

// ---------------------------------------------------------
// PRUEBA DEL FORMULARIO DE CREACIÓN
// Confirma que el usuario autorizado puede abrir la pantalla
// de registro de proveedores.
// ---------------------------------------------------------

test('authorized user can view supplier creation form', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    $this
        ->actingAs($propietario)
        ->get(
            route(
                'proveedores.create',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertOk()
        ->assertSee('Nuevo proveedor')
        ->assertSee($empresa->nombre)
        ->assertSee('Nombre comercial')
        ->assertSee('Razón social')
        ->assertSee('RFC')
        ->assertSee('Crear proveedor');
});

// ---------------------------------------------------------
// PRUEBA DE CREACIÓN POR POST
// Comprueba la creación, normalización, redirección y
// auditoría desde el endpoint HTTP.
// ---------------------------------------------------------

test('authorized user can create supplier through endpoint', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    $response = $this
        ->actingAs($propietario)
        ->post(
            route(
                'proveedores.store',
                [
                    'empresa' => $empresa->id,
                ]
            ),
            [
                'codigo' => ' prov-001 ',
                'nombre' => ' Proveedor del Norte ',
                'razon_social' => ' Proveedor del Norte SA de CV ',
                'rfc' => ' abc123456xyz ',
                'contacto' => ' Área de ventas ',
                'telefono' => ' 6621234567 ',
                'correo' => ' VENTAS@PROVEEDOR.TEST ',
                'direccion' => ' Dirección del proveedor ',
                'notas' => ' Entregas programadas. ',
            ]
        );

    $response
        ->assertRedirect(
            route(
                'proveedores.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertSessionHas(
            'success',
            'El proveedor fue creado correctamente.'
        );

    $proveedor = Proveedor::query()
        ->where('empresa_id', $empresa->id)
        ->where('codigo', 'PROV-001')
        ->firstOrFail();

    expect($proveedor->nombre)
        ->toBe('Proveedor del Norte')
        ->and($proveedor->razon_social)
        ->toBe('Proveedor del Norte SA de CV')
        ->and($proveedor->rfc)
        ->toBe('ABC123456XYZ')
        ->and($proveedor->correo)
        ->toBe('ventas@proveedor.test')
        ->and($proveedor->activo)
        ->toBeTrue()
        ->and($proveedor->creado_por_id)
        ->toBe($propietario->id);

    $this->assertDatabaseHas(
        'auditorias',
        [
            'usuario_id' => $propietario->id,
            'accion' => 'proveedor.creado',
            'modulo' => 'proveedores',
            'modelo_id' => $proveedor->id,
        ]
    );

    $auditoria = Auditoria::query()
        ->where('accion', 'proveedor.creado')
        ->firstOrFail();

    expect($auditoria->valores_nuevos)
        ->toMatchArray([
            'empresa_id' => $empresa->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => 'PROV-001',
            'nombre' => 'Proveedor del Norte',
            'activo' => true,
        ]);
});

// ---------------------------------------------------------
// PRUEBA DEL FORMULARIO DE EDICIÓN
// Confirma que el usuario autorizado puede abrir la pantalla
// de edición con los datos del proveedor cargados.
// ---------------------------------------------------------

test('authorized user can view supplier edit form', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor Original',
        'razon_social' => 'Proveedor Original SA de CV',
        'rfc' => 'ABC123456XYZ',
        'contacto' => 'Contacto Original',
        'telefono' => '6621234567',
        'correo' => 'original@proveedor.test',
        'direccion' => 'Dirección original',
        'notas' => 'Notas originales',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $this
        ->actingAs($propietario)
        ->get(
            route(
                'proveedores.edit',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            )
        )
        ->assertOk()
        ->assertSee('Editar proveedor')
        ->assertSee($empresa->nombre)
        ->assertSee($proveedor->nombre)
        ->assertSee($proveedor->codigo)
        ->assertSee('Guardar cambios');
});

// ---------------------------------------------------------
// PRUEBA DE ACTUALIZACIÓN POR PUT
// Valida la modificación de datos, normalización, redirección
// y registro de auditoría.
// ---------------------------------------------------------

test('authorized user can update supplier through endpoint', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor Inicial',
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

    $response = $this
        ->actingAs($propietario)
        ->put(
            route(
                'proveedores.update',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            ),
            [
                'codigo' => ' prov-modificado ',
                'nombre' => ' Proveedor Modificado ',
                'razon_social' => ' Proveedor Modificado SA de CV ',
                'rfc' => ' xyz987654abc ',
                'contacto' => ' Nuevo Contacto ',
                'telefono' => ' 6629876543 ',
                'correo' => ' NUEVO@PROVEEDOR.TEST ',
                'direccion' => ' Nueva dirección ',
                'notas' => ' Nuevas notas ',
            ]
        );

    $response
        ->assertRedirect(
            route(
                'proveedores.edit',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            )
        )
        ->assertSessionHas(
            'success',
            'El proveedor fue actualizado correctamente.'
        );

    $proveedor->refresh();

    expect($proveedor->codigo)
        ->toBe('PROV-MODIFICADO')
        ->and($proveedor->nombre)
        ->toBe('Proveedor Modificado')
        ->and($proveedor->rfc)
        ->toBe('XYZ987654ABC')
        ->and($proveedor->correo)
        ->toBe('nuevo@proveedor.test')
        ->and($proveedor->actualizado_por_id)
        ->toBe($propietario->id);

    $this->assertDatabaseHas(
        'auditorias',
        [
            'usuario_id' => $propietario->id,
            'accion' => 'proveedor.actualizado',
            'modulo' => 'proveedores',
            'modelo_id' => $proveedor->id,
        ]
    );
});

// ---------------------------------------------------------
// PRUEBA DE CAMBIO DE ESTADO POR PATCH
// Valida la desactivación y reactivación auditadas con motivo.
// ---------------------------------------------------------

test('authorized user can deactivate and reactivate supplier through endpoint', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor Activo',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    // 1. Desactivación
    $responseDesactivar = $this
        ->actingAs($propietario)
        ->patch(
            route(
                'proveedores.estado.update',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            ),
            [
                'activar' => '0',
                'motivo' => 'Proveedor temporalmente inoperativo.',
            ]
        );

    $responseDesactivar
        ->assertRedirect(
            route(
                'proveedores.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertSessionHas(
            'success',
            'El proveedor fue desactivado correctamente.'
        );

    $proveedor->refresh();

    expect($proveedor->activo)
        ->toBeFalse()
        ->and($proveedor->motivo_desactivacion)
        ->toBe('Proveedor temporalmente inoperativo.')
        ->and($proveedor->desactivado_por_id)
        ->toBe($propietario->id);

    // 2. Reactivación
    $responseReactivar = $this
        ->actingAs($propietario)
        ->patch(
            route(
                'proveedores.estado.update',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            ),
            [
                'activar' => '1',
                'motivo' => 'Proveedor reanudó operaciones y crédito.',
            ]
        );

    $responseReactivar
        ->assertRedirect(
            route(
                'proveedores.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertSessionHas(
            'success',
            'El proveedor fue reactivado correctamente.'
        );

    $proveedor->refresh();

    expect($proveedor->activo)
        ->toBeTrue()
        ->and($proveedor->motivo_desactivacion)
        ->toBeNull()
        ->and($proveedor->desactivado_at)
        ->toBeNull()
        ->and($proveedor->desactivado_por_id)
        ->toBeNull();
});

// ---------------------------------------------------------
// PRUEBA DE AISLAMIENTO MULTI-EMPRESA
// Comprueba que no se pueda acceder ni modificar un proveedor
// a través de una empresa ajena (404 Not Found).
// ---------------------------------------------------------

test('supplier of another company cannot be accessed or updated', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresaUno = empresaParaProveedorHttp(
        $propietario,
        'Empresa Uno'
    );

    $empresaDos = empresaParaProveedorHttp(
        $propietario,
        'Empresa Dos'
    );

    $proveedorEmpresaUno = Proveedor::query()->create([
        'empresa_id' => $empresaUno->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor de Empresa Uno',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    // Intento de edición cruzada -> 404
    $this
        ->actingAs($propietario)
        ->get(
            route(
                'proveedores.edit',
                [
                    'empresa' => $empresaDos->id,
                    'proveedor' => $proveedorEmpresaUno->id,
                ]
            )
        )
        ->assertNotFound();

    // Intento de actualización cruzada -> 403 o 404
    $this
        ->actingAs($propietario)
        ->put(
            route(
                'proveedores.update',
                [
                    'empresa' => $empresaDos->id,
                    'proveedor' => $proveedorEmpresaUno->id,
                ]
            ),
            [
                'codigo' => 'PROV-001',
                'nombre' => 'Intento de hackeo multi-tenant',
            ]
        )
        ->assertForbidden();
});

// ---------------------------------------------------------
// CÓDIGO DUPLICADO
// Impide registrar dos proveedores con el mismo código
// dentro de una misma empresa, incluso con otro uso de
// mayúsculas y minúsculas.
// ---------------------------------------------------------

test('supplier code must be unique through endpoint', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor existente',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $formulario = route(
        'proveedores.create',
        [
            'empresa' => $empresa->id,
        ]
    );

    $this
        ->actingAs($propietario)
        ->from($formulario)
        ->post(
            route(
                'proveedores.store',
                [
                    'empresa' => $empresa->id,
                ]
            ),
            [
                'codigo' => 'prov-001',
                'nombre' => 'Proveedor duplicado',
            ]
        )
        ->assertRedirect($formulario)
        ->assertSessionHasErrors([
            'codigo',
        ]);

    expect(
        Proveedor::query()
            ->where(
                'empresa_id',
                $empresa->id
            )
            ->count()
    )->toBe(1);

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'proveedor.creado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// PROVEEDOR DE OTRA EMPRESA
// Evita editar un proveedor usando en la URL una empresa
// diferente a la empresa propietaria del proveedor.
// ---------------------------------------------------------

test('supplier cannot be edited through another company', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresaUno = empresaParaProveedorHttp(
        $propietario
    );

    $empresaDos = empresaParaProveedorHttp(
        $propietario,
        'Empresa secundaria'
    );

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresaUno->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor empresa uno',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $this
        ->actingAs($propietario)
        ->get(
            route(
                'proveedores.edit',
                [
                    'empresa' => $empresaDos->id,
                    'proveedor' => $proveedor->id,
                ]
            )
        )
        ->assertNotFound();

    expect($proveedor->fresh()?->empresa_id)
        ->toBe($empresaUno->id);
});

// ---------------------------------------------------------
// USUARIO SIN PERMISOS
// Confirma que un empleado sin permisos de productos no
// pueda consultar, crear, editar ni cambiar proveedores.
// ---------------------------------------------------------

test('user without permissions cannot manage suppliers', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor protegido',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $empleado = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $empleado->assignRole('empleado');

    $this
        ->actingAs($empleado)
        ->get(
            route(
                'proveedores.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertForbidden();

    $this
        ->actingAs($empleado)
        ->get(
            route(
                'proveedores.create',
                [
                    'empresa' => $empresa->id,
                ]
            )
        )
        ->assertForbidden();

    $this
        ->actingAs($empleado)
        ->get(
            route(
                'proveedores.edit',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            )
        )
        ->assertForbidden();

    $this
        ->actingAs($empleado)
        ->patch(
            route(
                'proveedores.estado.update',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            ),
            [
                'activar' => '0',
                'motivo' => 'Cambio no autorizado.',
            ]
        )
        ->assertForbidden();

    expect($proveedor->fresh()?->activo)
        ->toBeTrue();

    expect(
        Auditoria::query()
            ->whereIn(
                'accion',
                [
                    'proveedor.creado',
                    'proveedor.actualizado',
                    'proveedor.desactivado',
                    'proveedor.reactivado',
                ]
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// MOTIVO OBLIGATORIO
// Impide cambiar el estado si no se registra una razón.
// ---------------------------------------------------------

test('supplier status change requires a reason through endpoint', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor de prueba',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $listado = route(
        'proveedores.index',
        [
            'empresa' => $empresa->id,
        ]
    );

    $this
        ->actingAs($propietario)
        ->from($listado)
        ->patch(
            route(
                'proveedores.estado.update',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            ),
            [
                'activar' => '0',
                'motivo' => '   ',
            ]
        )
        ->assertRedirect($listado)
        ->assertSessionHasErrors([
            'motivo',
        ]);

    expect($proveedor->fresh()?->activo)
        ->toBeTrue()
        ->and($proveedor->fresh()?->desactivado_at)
        ->toBeNull();

    expect(
        Auditoria::query()
            ->where(
                'accion',
                'proveedor.desactivado'
            )
            ->count()
    )->toBe(0);
});

// ---------------------------------------------------------
// CAMPOS PROTEGIDOS
// Comprueba que el formulario general de actualización no
// pueda cambiar empresa, estado ni datos de auditoría.
// ---------------------------------------------------------

test('supplier update ignores protected fields', function () {
    $propietario = propietarioParaProveedorHttp();

    $empresa = empresaParaProveedorHttp(
        $propietario
    );

    $otraEmpresa = empresaParaProveedorHttp(
        $propietario,
        'Empresa secundaria'
    );

    $otroUsuario = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $proveedor = Proveedor::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => 'PROV-001',
        'nombre' => 'Proveedor inicial',
        'activo' => true,
        'creado_por_id' => $propietario->id,
    ]);

    $this
        ->actingAs($propietario)
        ->put(
            route(
                'proveedores.update',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            ),
            [
                'codigo' => 'PROV-001',
                'nombre' => 'Proveedor actualizado',
                'razon_social' => null,
                'rfc' => null,
                'contacto' => null,
                'telefono' => null,
                'correo' => null,
                'direccion' => null,
                'notas' => null,

                // Campos que no pertenecen al Request.
                'empresa_id' => $otraEmpresa->id,
                'activo' => false,
                'creado_por_id' => $otroUsuario->id,
                'actualizado_por_id' => $otroUsuario->id,
                'desactivado_por_id' => $otroUsuario->id,
                'desactivado_at' => now()->toDateTimeString(),
                'motivo_desactivacion' => 'Intento de modificación no permitido.',
            ]
        )
        ->assertRedirect(
            route(
                'proveedores.edit',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            )
        );

    $proveedor->refresh();

    expect($proveedor->nombre)
        ->toBe('Proveedor actualizado')
        ->and($proveedor->empresa_id)
        ->toBe($empresa->id)
        ->and($proveedor->activo)
        ->toBeTrue()
        ->and($proveedor->creado_por_id)
        ->toBe($propietario->id)
        ->and($proveedor->actualizado_por_id)
        ->toBe($propietario->id)
        ->and($proveedor->desactivado_por_id)
        ->toBeNull()
        ->and($proveedor->desactivado_at)
        ->toBeNull()
        ->and($proveedor->motivo_desactivacion)
        ->toBeNull();
});
