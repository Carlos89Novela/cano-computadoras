<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Permisos reservados al propietario
    |--------------------------------------------------------------------------
    |
    | Estos permisos nunca deben aparecer como responsabilidades delegables.
    |
    */

    'permisos_reservados' => [
        'usuarios.asignar_roles',
        'usuarios.asignar_permisos',
        'usuarios.modificar_propietario',
        'auditoria.exportar_completa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles que puede asignar el propietario
    |--------------------------------------------------------------------------
    */

    'roles_asignables' => [
        'cliente',
        'empleado',
        'supervisor',
        'administrador',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles operativos
    |--------------------------------------------------------------------------
    */

    'roles_operativos' => [
        'empleado',
        'supervisor',
        'administrador',
    ],

    'permisos_delegables' => [
        'ordenes' => [
            'titulo' => 'Órdenes y reparaciones',
            'permisos' => [
                'ordenes.ver_todas' => 'Ver todas las órdenes',
                'ordenes.asignar' => 'Asignar órdenes',
                'ordenes.reasignar' => 'Reasignar órdenes',
                'ordenes.actualizar_costos' => 'Actualizar costos',
                'ordenes.enviar_mensaje_cliente' => 'Enviar mensajes al cliente',
                'ordenes.aprobar_cotizacion' => 'Aprobar cotizaciones',
                'ordenes.rechazar_cotizacion' => 'Rechazar cotizaciones',
                'ordenes.entregar' => 'Confirmar entregas',
            ],
        ],

        'servicios' => [
            'titulo' => 'Servicios y precios',
            'permisos' => [
                'servicios.ver' => 'Ver servicios',
                'servicios.actualizar_precios' => 'Actualizar precios',
                'servicios.crear' => 'Crear servicios',
                'servicios.actualizar' => 'Editar servicios',
                'servicios.cambiar_estado' => 'Activar o desactivar servicios',
                'servicios.eliminar' => 'Eliminar servicios',
            ],
        ],

        'productos' => [
            'titulo' => 'Productos',
            'permisos' => [
                'productos.ver' => 'Ver productos',
                'productos.crear' => 'Crear productos',
                'productos.actualizar' => 'Editar productos',
                'productos.actualizar_precios' => 'Actualizar precios de productos',
                'productos.cambiar_estado' => 'Activar o desactivar productos',
                'productos.eliminar' => 'Eliminar productos',
            ],
        ],

        'inventario' => [
            'titulo' => 'Inventario',
            'permisos' => [
                'inventario.ver' => 'Ver inventario',
                'inventario.registrar_entrada' => 'Registrar entradas',
                'inventario.registrar_salida' => 'Registrar salidas',
                'inventario.ajustar' => 'Ajustar existencias',
                'inventario.consumir' => 'Consumir productos en reparaciones',
                'inventario.devolver' => 'Registrar devoluciones',
                'inventario.ver_movimientos' => 'Ver movimientos',
                'inventario.exportar' => 'Exportar inventario',
            ],
        ],

        'auditoria' => [
            'titulo' => 'Auditoría',
            'permisos' => [
                'auditoria.ver' => 'Ver auditoría',
                'auditoria.ver_accesos' => 'Ver registros de acceso',
                'auditoria.ver_permisos' => 'Ver cambios de permisos',
                'auditoria.ver_usuarios' => 'Ver auditoría de usuarios',
                'auditoria.ver_ordenes' => 'Ver auditoría de órdenes',
                'auditoria.ver_servicios' => 'Ver auditoría de servicios',
                'auditoria.ver_productos' => 'Ver auditoría de productos',
                'auditoria.ver_inventario' => 'Ver auditoría de inventario',
                'auditoria.exportar' => 'Exportar auditoría autorizada',
            ],
        ],
    ],

];
