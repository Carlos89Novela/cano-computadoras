# Cano Computadoras

Aplicación web para gestionar equipos, reparaciones y servicios técnicos de una tienda de computadoras.

## Descripción general

Este proyecto está construido con Laravel y usa Tailwind CSS, permisos por roles y un flujo de administración para servicios, equipos y órdenes de reparación.

## Funcionalidades principales

- Registro y administración de equipos por usuario.
- Solicitud de reparación por parte del cliente.
- Seguimiento público por folio.
- Panel administrativo para revisar y actualizar órdenes.
- Gestión de servicios y precios.
- Control de permisos por rol (`administrador`).
- Notificaciones de actualización de reparación para el cliente.
- Exportación de órdenes en PDF.

## Estado actual

La aplicación ya cuenta con una base funcional para:

- login y autenticación
- dashboard del cliente
- administración de reparaciones
- creación y actualización de servicios
- gestión de órdenes por folio
- acceso controlado por roles
- validación de propiedad por usuario
- historial y notificaciones del ciclo de vida de cada reparación

## Pruebas implementadas

La suite principal de pruebas funcionales está en `tests/Feature/AdminOrdersTest.php`.

### Cobertura final verificada

- Acceso del administrador al panel de órdenes.
- Bloqueo del panel administrativo para usuarios normales.
- Acceso autenticado al dashboard.
- Creación de servicios por administrador.
- Actualización de servicios por administrador.
- Cambio de estado de una orden por administrador.
- Creación de una reparación por parte del cliente para su propio equipo.
- Seguimiento público por folio.
- Restringir acceso a órdenes ajenas.
- Restringir autorizar órdenes ajenas.
- Restringir rutas de cliente a usuarios invitados.
- Listado de órdenes solo del usuario autenticado.
- Bloqueo de creación de órdenes para equipos ajenos.
- Control de gestión de equipos por propietario.
- Listado de equipos solo del usuario autenticado.
- Acceso a notificaciones solo por usuario propietario.
- Lectura masiva de notificaciones propias.
- Generación de historial de reparación y notificación al cliente al actualizar estado.
- Autorización del cliente solo cuando la orden está en espera de autorización.
- Rechazo de estados inválidos por parte del administrador.
- Bloqueo de PDF de órdenes ajenas.
- Acceso al detalle de la propia orden.
- Filtros avanzados por estado en el panel administrativo.
- Exportación CSV y PDF con filtro activo desde el dashboard.
- Vista de resumen ejecutivo y totales en el PDF de reporte.
- Rediseño del landing principal con identidad gráfica tipo logotipo premium.

### Comando de validación

```bash
php artisan test tests/Feature/AdminOrdersTest.php
```

Resultado verificado: 29 pruebas pasadas, 97 assertions.

## Historial de trabajo y cambios

> Comentarios cronológicos del progreso del proyecto.

### 2026-09-01 - Etapa de validación funcional y seguridad

- Se crea la rama `feature/testing-core-flows` para guardar la etapa de validación del core del negocio.
- Se implementa la suite inicial de pruebas funcionales para admin, dashboard, creación de órdenes, seguimiento público y seguridad.
- Se corrige el uso del campo `comentarios` en historial de reparaciones para alinear con el esquema real del proyecto.
- Se ajusta la vista administrativa de órdenes para usar DataTables con estilo Tailwind y AJAX seguro.
- Se centraliza la carga de jQuery/DataTables y estilos compartidos.
- Se corrige la lógica de propiedad para impedir acceso a recursos ajenos.
- Se añaden pruebas para PDF, detalle de órdenes, equipos, notificaciones y autorización del cliente.
- Se documenta la cobertura final en este README.

### 2026-09-01 - Mejoras operativas del panel administrativo

- Se mejora la UX del panel de órdenes con filtros por estado, búsqueda, ordenamiento y mejores badges visuales.
- Se integra la columna de costo para análisis operativo directo desde la tabla.
- Se agregan botones para ver detalle y administrar cada orden desde la vista de gestión.
- Se mejora la calidad visual del dashboard para mantener coherencia con la identidad de la marca.

### 2026-09-01 - Exportación profesional de reportes

- Se implementa la exportación de órdenes en CSV y PDF desde el backend con filtro activo.
- El PDF incluye cabecera, resumen ejecutivo, total de ingresos y desglose por estado.
- Se genera un nombre de archivo profesional según fecha y estado filtrado.
- Se corrige la entrega del contenido para mantener un flujo de reportes confiable y reproducible.

### 2026-09-01 - Rediseño del branding del landing principal

- Se aplica una nueva identidad visual con un logotipo tipo marca premium para Cano Computadoras.
- Se adapta el hero principal con un estilo oscuro, violeta y técnico.
- Se mantiene la navegación y llamadas a la acción funcional para login, registro y servicios.

### 2026-09-01 - Cierre de QA del flujo principal

- Se valida el flujo completo de reparación: ingreso, diagnóstico, autorización, actualización, historial y notificación.
- Se valida el acceso por rol y por propietario para equipos, órdenes y notificaciones.
- Se deja la rama lista para entrega con la cobertura de calidad más reciente.

## Roadmap sugerido

1. Mejorar filtros y búsquedas del panel administrativo.
2. Añadir más pruebas E2E para UX de cliente y notificaciones visuales.
3. Evaluar la conversión de la tabla de servicios a DataTables si el volumen aumenta.
4. Continuar con pruebas de rendimiento y validación de PDF.
5. Revisar mejoras de UX en el flujo de autorización y entrega.

## Tecnologías principales

- Laravel
- PHP
- Tailwind CSS
- Livewire
- Spatie Permission
- Pest PHP
- jQuery DataTables

## Licencia

Este proyecto se mantiene como desarrollo interno de la aplicación y sigue el estándar de Laravel con control de versión en GitHub.
