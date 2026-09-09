<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $permisos = [
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.actualizar',
            'usuarios.desactivar',
            'usuarios.asignar_roles',

            'servicios.ver',
            'servicios.crear',
            'servicios.actualizar',
            'servicios.cambiar_estado',
            'servicios.eliminar',

            'ordenes.ver_todas',
            'ordenes.ver_asignadas',
            'ordenes.ver_propias',
            'ordenes.crear',
            'ordenes.actualizar',
            'ordenes.asignar',
            'ordenes.reasignar',
            'ordenes.registrar_diagnostico',
            'ordenes.registrar_avance',
            'ordenes.actualizar_costos',
            'ordenes.enviar_mensaje_cliente',
            'ordenes.solicitar_revision_cotizacion',
            'ordenes.aprobar_cotizacion',
            'ordenes.rechazar_cotizacion',
            'ordenes.autorizar_presupuesto',
            'ordenes.solicitar_cierre',
            'ordenes.aprobar_cierre',
            'ordenes.rechazar_cierre',
            'ordenes.marcar_entregada',
            'ordenes.descargar_pdf',

            'historial.ver_interno',
            'historial.ver_cliente',
            'historial.registrar_comentario_interno',

            'reportes.ver_operativos',
            'reportes.ver_financieros',
            'reportes.exportar',

            'configuracion.ver',
            'configuracion.actualizar',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $administrador = Role::findOrCreate(
            'administrador',
            'web'
        );

        $supervisor = Role::findOrCreate(
            'supervisor',
            'web'
        );

        $empleado = Role::findOrCreate(
            'empleado',
            'web'
        );

        $cliente = Role::findOrCreate(
            'cliente',
            'web'
        );

        $administrador->syncPermissions($permisos);

        $supervisor->syncPermissions([
            'servicios.ver',

            'ordenes.ver_todas',
            'ordenes.ver_asignadas',
            'ordenes.actualizar',
            'ordenes.asignar',
            'ordenes.reasignar',
            'ordenes.registrar_diagnostico',
            'ordenes.registrar_avance',
            'ordenes.actualizar_costos',
            'ordenes.enviar_mensaje_cliente',
            'ordenes.aprobar_cotizacion',
            'ordenes.rechazar_cotizacion',
            'ordenes.aprobar_cierre',
            'ordenes.rechazar_cierre',
            'ordenes.marcar_entregada',
            'ordenes.descargar_pdf',

            'historial.ver_interno',
            'historial.registrar_comentario_interno',

            'reportes.ver_operativos',
        ]);

        $empleado->syncPermissions([
            'servicios.ver',

            'ordenes.ver_asignadas',
            'ordenes.registrar_diagnostico',
            'ordenes.registrar_avance',
            'ordenes.actualizar_costos',
            'ordenes.solicitar_revision_cotizacion',
            'ordenes.solicitar_cierre',

            'historial.ver_interno',
            'historial.registrar_comentario_interno',
        ]);

        $cliente->syncPermissions([
            'ordenes.ver_propias',
            'ordenes.crear',
            'ordenes.autorizar_presupuesto',
            'ordenes.descargar_pdf',

            'historial.ver_cliente',
        ]);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
