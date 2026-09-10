<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    app(PermissionRegistrar::class)
        ->forgetCachedPermissions();
});

test('the system creates the four operational roles', function () {
    expect(
        Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->all()
    )->toBe([
        'administrador',
        'cliente',
        'empleado',
        'supervisor',
    ]);
});

test('the administrator receives every registered permission', function () {
    $administrador = Role::findByName(
        'administrador',
        'web'
    );

    expect($administrador->permissions()->count())
        ->toBe(Permission::query()->count())
        ->and($administrador->permissions()->count())
        ->toBe(38);
});

test('the supervisor can coordinate work without administering services', function () {
    $supervisor = Role::findByName(
        'supervisor',
        'web'
    );

    expect($supervisor->hasPermissionTo('servicios.ver'))
        ->toBeTrue()
        ->and($supervisor->hasPermissionTo('ordenes.ver_todas'))
        ->toBeTrue()
        ->and($supervisor->hasPermissionTo('ordenes.asignar'))
        ->toBeTrue()
        ->and($supervisor->hasPermissionTo('ordenes.reasignar'))
        ->toBeTrue()
        ->and($supervisor->hasPermissionTo('ordenes.aprobar_cotizacion'))
        ->toBeTrue()
        ->and($supervisor->hasPermissionTo('ordenes.rechazar_cotizacion'))
        ->toBeTrue()
        ->and($supervisor->hasPermissionTo('ordenes.aprobar_cierre'))
        ->toBeTrue()
        ->and($supervisor->hasPermissionTo('ordenes.rechazar_cierre'))
        ->toBeTrue()
        ->and($supervisor->hasPermissionTo('servicios.crear'))
        ->toBeFalse()
        ->and($supervisor->hasPermissionTo('servicios.actualizar'))
        ->toBeFalse()
        ->and($supervisor->hasPermissionTo('servicios.cambiar_estado'))
        ->toBeFalse()
        ->and($supervisor->hasPermissionTo('servicios.eliminar'))
        ->toBeFalse()
        ->and($supervisor->hasPermissionTo('usuarios.asignar_roles'))
        ->toBeFalse();
});

test('the employee has technical permissions without approval permissions', function () {
    $empleado = Role::findByName(
        'empleado',
        'web'
    );

    expect($empleado->hasPermissionTo('servicios.ver'))
        ->toBeTrue()
        ->and($empleado->hasPermissionTo('ordenes.ver_asignadas'))
        ->toBeTrue()
        ->and($empleado->hasPermissionTo('ordenes.registrar_diagnostico'))
        ->toBeTrue()
        ->and($empleado->hasPermissionTo('ordenes.registrar_avance'))
        ->toBeTrue()
        ->and($empleado->hasPermissionTo('ordenes.actualizar_costos'))
        ->toBeTrue()
        ->and($empleado->hasPermissionTo('ordenes.solicitar_revision_cotizacion'))
        ->toBeTrue()
        ->and($empleado->hasPermissionTo('ordenes.solicitar_cierre'))
        ->toBeTrue()
        ->and($empleado->hasPermissionTo('ordenes.ver_todas'))
        ->toBeFalse()
        ->and($empleado->hasPermissionTo('ordenes.asignar'))
        ->toBeFalse()
        ->and($empleado->hasPermissionTo('ordenes.reasignar'))
        ->toBeFalse()
        ->and($empleado->hasPermissionTo('ordenes.aprobar_cotizacion'))
        ->toBeFalse()
        ->and($empleado->hasPermissionTo('ordenes.aprobar_cierre'))
        ->toBeFalse()
        ->and($empleado->hasPermissionTo('ordenes.marcar_entregada'))
        ->toBeFalse()
        ->and($empleado->hasPermissionTo('servicios.crear'))
        ->toBeFalse();
});

test('the client keeps only customer permissions', function () {
    $cliente = Role::findByName(
        'cliente',
        'web'
    );

    expect($cliente->hasPermissionTo('ordenes.ver_propias'))
        ->toBeTrue()
        ->and($cliente->hasPermissionTo('ordenes.crear'))
        ->toBeTrue()
        ->and($cliente->hasPermissionTo('ordenes.autorizar_presupuesto'))
        ->toBeTrue()
        ->and($cliente->hasPermissionTo('ordenes.descargar_pdf'))
        ->toBeTrue()
        ->and($cliente->hasPermissionTo('historial.ver_cliente'))
        ->toBeTrue()
        ->and($cliente->hasPermissionTo('ordenes.ver_todas'))
        ->toBeFalse()
        ->and($cliente->hasPermissionTo('ordenes.ver_asignadas'))
        ->toBeFalse()
        ->and($cliente->hasPermissionTo('ordenes.asignar'))
        ->toBeFalse()
        ->and($cliente->hasPermissionTo('ordenes.aprobar_cierre'))
        ->toBeFalse()
        ->and($cliente->hasPermissionTo('historial.ver_interno'))
        ->toBeFalse()
        ->and($cliente->hasPermissionTo('servicios.crear'))
        ->toBeFalse();
});

test('running the roles seeder repeatedly does not create duplicates', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    app(PermissionRegistrar::class)
        ->forgetCachedPermissions();

    expect(Role::query()->count())
        ->toBe(4)
        ->and(Permission::query()->count())
        ->toBe(38)
        ->and(
            Role::query()
                ->where('name', 'administrador')
                ->count()
        )
        ->toBe(1)
        ->and(
            Role::query()
                ->where('name', 'supervisor')
                ->count()
        )
        ->toBe(1)
        ->and(
            Role::query()
                ->where('name', 'empleado')
                ->count()
        )
        ->toBe(1)
        ->and(
            Role::query()
                ->where('name', 'cliente')
                ->count()
        )
        ->toBe(1);
});
