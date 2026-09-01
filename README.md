# Caño Computadoras

Aplicación web para gestionar equipos, reparaciones y servicios técnicos de una tienda de computadoras.

## Descripción general

Este proyecto está construido con Laravel y usa Livewire, Tailwind CSS, permisos por roles y un flujo de administración para servicios y órdenes de reparación.

## Funcionalidades principales

- Registro y administración de equipos por usuario.
- Solicitud de reparación por parte del cliente.
- Seguimiento público por folio.
- Panel administrativo para revisar y actualizar órdenes.
- Gestión de servicios y precios.
- Control de permisos por rol (`administrador`).

## Estado actual

La aplicación ya cuenta con una base funcional para:

- login y autenticación
- dashboard del cliente
- administración de reparaciones
- creación y actualización de servicios
- gestión de órdenes por folio
- acceso controlado por roles

## Pruebas implementadas

Se creó la suite principal de pruebas funcionales en `tests/Feature/AdminOrdersTest.php`.

### Cobertura actual

- Acceso al panel administrativo por administrador.
- Bloqueo del panel administrativo para usuarios normales.
- Acceso al dashboard autenticado.
- Creación de un servicio por administrador.
- Actualización de un servicio por administrador.
- Cambio de estado de una orden por administrador.
- Creación de una reparación por el cliente.
- Seguimiento público por folio.
- Restricción de acceso a órdenes ajenas.
- Restricción para autorizar órdenes ajenas.
- Bloqueo de rutas administrativas para usuarios normales.

### Comando de validación

```bash
php artisan test tests/Feature/AdminOrdersTest.php
```

Resultado verificado: 11 pruebas pasadas, 26 assertions.

## Historial de trabajo y cambios

> Comentarios cronológicos del progreso del proyecto.

### 2026-09-01

- Se crea la rama `feature/testing-core-flows` para guardar la etapa de validación del core del negocio.
- Se implementan pruebas funcionales para admin, dashboard, creación de órdenes, seguimiento público y seguridad.
- Se corrige el uso del campo `comentarios` en historial de reparaciones para alinear con el esquema real del proyecto.
- Se deja documentada la cobertura de pruebas en este README como parte del proceso de control de calidad.

### 2026-09-01

- Se ajusta la vista administrativa de órdenes para usar DataTables y estilo visual con Tailwind.
- Se centraliza la carga de jQuery/DataTables y estilos compartidos.
- Se valida la ruta AJAX del panel administrativo para ordenes.

### 2026-09-01

- Se revisa y corrige el flujo de creación y seguimiento de órdenes para evitar inconsistencias en historial.
- Se mejora la lógica de permisos para restringir acceso a recursos ajenos.

## Roadmap sugerido

1. Añadir pruebas para el flujo de edición de equipos por usuario.
2. Agregar pruebas para PDF de órdenes y validación de propiedad.
3. Mejorar filtros y búsquedas del panel administrativo.
4. Convertir la tabla de servicios a DataTables si el volumen crece.
5. Continuar con pruebas de UX y seguridad del cliente.

## Tecnologías principales

- Laravel
- PHP
- Tailwind CSS
- Livewire
- Spatie Permission
- Pest PHP

## Licencia

Este proyecto se mantiene como desarrollo interno de la aplicación y sigue el estándar de Laravel con control de versión en GitHub.
