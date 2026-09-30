<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sembrador de Roles y Permisos Granulares del Sistema (RBAC).
 *
 * Configura la infraestructura de autorización basada en Spatie Laravel-Permission:
 * 1. Limpieza de memoria caché de permisos previa para evitar inconsistencias en pruebas o despliegues.
 * 2. Registro exhaustivo de permisos agrupados por dominios de negocio:
 *    - Auditoría y accesos (telemetría de seguridad).
 *    - Gestión de usuarios y control de acceso.
 *    - Catálogo de servicios técnicos.
 *    - Ciclo de vida de órdenes de servicio en taller (diagnóstico, cotización, pruebas, entrega).
 *    - Historial y bitácora técnica / cliente.
 *    - Informes y reportes de gestión.
 *    - Catálogo de productos e inventario físico.
 * 3. Creación y asignación de permisos según la jerarquía de roles:
 *    - Administrador: Todos los permisos operativos excepto los reservados a nivel de configuración.
 *    - Supervisor: Gestión de asignaciones, revisión y aprobación de cotizaciones, entrega y reportes.
 *    - Empleado: Registro de diagnósticos, avance en mesa de trabajo y envío a revisión de cotizaciones.
 *    - Cliente: Creación de órdenes sobre equipos propios, autorización de presupuestos y comprobantes PDF.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Ejecuta la inicialización de roles y sincronización de permisos.
     */
    public function run(): void
    {
        // Limpia cualquier residuo en la memoria caché interna del registrar de Spatie
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        // ---------------------------------------------------------------------
        // 1. Definición del Catálogo Completo de Permisos Granulares
        // ---------------------------------------------------------------------
        $permisos = [
            // Auditoría y Telemetría de Seguridad
            'auditoria.ver',
            'auditoria.ver_accesos',
            'auditoria.ver_permisos',
            'auditoria.ver_usuarios',
            'auditoria.ver_ordenes',
            'auditoria.ver_servicios',
            'auditoria.ver_productos',
            'auditoria.ver_inventario',
            'auditoria.exportar',
            'auditoria.exportar_completa',

            // Administración de Cuentas y Accesos
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.actualizar',
            'usuarios.desactivar',
            'usuarios.asignar_roles',
            'usuarios.asignar_permisos',
            'usuarios.modificar_propietario',

            // Catálogo de Servicios de Taller
            'servicios.actualizar_precios',
            'servicios.ver',
            'servicios.crear',
            'servicios.actualizar',
            'servicios.cambiar_estado',
            'servicios.eliminar',

            // Ciclo Operativo de Órdenes de Reparación
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
            'ordenes.entregar',

            // Bitácora e Historial de Reparaciones
            'historial.ver_interno',
            'historial.ver_cliente',
            'historial.registrar_comentario_interno',

            // Reportes Gerenciales y Operativos
            'reportes.ver_operativos',
            'reportes.ver_financieros',
            'reportes.exportar',

            // Parámetros y Configuración Global
            'configuracion.ver',
            'configuracion.actualizar',

            // Catálogo Comercial de Productos
            'productos.ver',
            'productos.crear',
            'productos.actualizar',
            'productos.actualizar_precios',
            'productos.cambiar_estado',
            'productos.eliminar',

            // Control de Almacenes e Inventarios
            'inventario.ver',
            'inventario.registrar_entrada',
            'inventario.registrar_salida',
            'inventario.ajustar',
            'inventario.consumir',
            'inventario.devolver',
            'inventario.ver_movimientos',
            'inventario.exportar',
        ];

        // Garantiza que cada permiso exista en la base de datos bajo el guard 'web'
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        // ---------------------------------------------------------------------
        // 2. Creación de Roles Base
        // ---------------------------------------------------------------------
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

        // ---------------------------------------------------------------------
        // 3. Asignación y Sincronización de Matrices de Permisos por Rol
        // ---------------------------------------------------------------------

        // Obtiene permisos reservados que no pueden otorgarse al rol de administrador general
        $permisosReservados = config(
            'access_control.permisos_reservados',
            []
        );

        $permisosAdministrador = collect($permisos)
            ->reject(
                fn (string $permiso): bool => is_array($permisosReservados)
                    && in_array(
                        $permiso,
                        $permisosReservados,
                        true
                    )
            )
            ->values()
            ->all();

        // El administrador recibe todos los permisos operativos no reservados
        $administrador->syncPermissions(
            $permisosAdministrador
        );

        // El supervisor supervisa la mesa de trabajo, asigna y autoriza cotizaciones
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

        // El empleado técnico se enfoca en sus órdenes asignadas y el avance de diagnósticos
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

        // El cliente solo tiene acceso a sus órdenes propias y a la respuesta a presupuestos
        $cliente->syncPermissions([
            'ordenes.ver_propias',
            'ordenes.crear',
            'ordenes.autorizar_presupuesto',
            'ordenes.descargar_pdf',

            'historial.ver_cliente',
        ]);

        // Invalida la caché nuevamente para que las asignaciones surtan efecto inmediato
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}

