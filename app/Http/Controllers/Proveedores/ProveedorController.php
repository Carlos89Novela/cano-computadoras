<?php

namespace App\Http\Controllers\Proveedores;

use App\Actions\Proveedores\ActualizarProveedor;
use App\Actions\Proveedores\CambiarEstadoProveedor;
use App\Actions\Proveedores\CrearProveedor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proveedores\ActualizarProveedorRequest;
use App\Http\Requests\Proveedores\CambiarEstadoProveedorRequest;
use App\Http\Requests\Proveedores\CrearProveedorRequest;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Controlador para la gestión de proveedores en el contexto de una empresa.
 *
 * Sigue el patrón "Thin Controller": este controlador únicamente se encarga de
 * recibir las peticiones HTTP, verificar la autenticación del usuario, coordinar
 * los datos con las clases de acción (Actions) y devolver las vistas o redirecciones.
 * Toda la lógica de negocio pesada (transacciones, auditorías y validaciones de
 * concurrencia) está delegada a las Actions correspondientes.
 */
class ProveedorController extends Controller
{
    /**
     * Muestra la lista paginada de proveedores pertenecientes a una empresa.
     *
     * @param  Empresa  $empresa  Modelo de la empresa inyectado automáticamente mediante Route Model Binding.
     * @return View Vista Blade con la tabla de proveedores y datos de la empresa.
     */
    public function index(
        Empresa $empresa
    ): View {
        // 1. Consulta los proveedores vinculados a la empresa mediante su relación Eloquent.
        // Se aplica Eager Loading (with) con proyección de columnas específicas (id, name)
        // para evitar el problema de rendimiento N+1 al mostrar los usuarios relacionados.
        // Se ordenan primero los proveedores activos y luego alfabéticamente por nombre.
        // Se pagina a 20 registros por página, manteniendo los parámetros de búsqueda en la URL.
        $proveedores = $empresa
            ->proveedores()
            ->with([
                'creadoPor:id,name',
                'actualizadoPor:id,name',
                'desactivadoPor:id,name',
            ])
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        // Anotación para Larastan/PHPStan: garantiza que el string corresponde a una vista válida de Blade.
        /** @var view-string $vista */
        $vista = 'proveedores.index';

        // 2. Renderiza la vista del listado enviando los datos necesarios.
        return view(
            $vista,
            compact(
                'empresa',
                'proveedores',
            )
        );
    }

    /**
     * Muestra el formulario para registrar un nuevo proveedor.
     *
     * @param  Empresa  $empresa  Empresa a la cual pertenecerá el nuevo proveedor.
     * @return View Vista Blade con el formulario de creación.
     */
    public function create(
        Empresa $empresa
    ): View {
        // Anotación de tipo para análisis estático (Larastan).
        /** @var view-string $vista */
        $vista = 'proveedores.create';

        // Retorna la vista pasando la empresa para contextualizar las rutas y acciones del formulario.
        return view(
            $vista,
            compact('empresa')
        );
    }

    /**
     * Procesa la creación de un nuevo proveedor y lo almacena en la base de datos.
     *
     * @param  CrearProveedorRequest  $request  FormRequest que valida permisos (productos.crear) y reglas de datos.
     * @param  Empresa  $empresa  Empresa propietaria del proveedor.
     * @param  CrearProveedor  $crearProveedor  Action encargada de la lógica de negocio, transacción y auditoría.
     * @return RedirectResponse Redirección al listado con mensaje de confirmación en sesión flash.
     */
    public function store(
        CrearProveedorRequest $request,
        Empresa $empresa,
        CrearProveedor $crearProveedor
    ): RedirectResponse {
        // 1. Obtiene el usuario autenticado que realiza la solicitud.
        $actor = $request->user();

        // Comprobación de seguridad y tipado estricto: asegura que el usuario sea una instancia de User.
        // Si no está autenticado o es inválido, aborta con código HTTP 403 (Prohibido).
        abort_unless(
            $actor instanceof User,
            403
        );

        // 2. Extrae los datos previamente normalizados y validados por CrearProveedorRequest.
        $datos = $request->validated();

        // 3. Ejecuta la Action de negocio. Esta se encarga de:
        //    - Abrir una transacción de base de datos con bloqueo pesimista (lockForUpdate).
        //    - Validar duplicidad de código insensible a mayúsculas/minúsculas dentro de la empresa.
        //    - Insertar el registro en la tabla 'proveedores'.
        //    - Generar el registro inmutable en la tabla 'auditorias' (con IP, agente y datos JSON).
        $crearProveedor->ejecutar(
            empresa: $empresa,
            actor: $actor,
            datos: $datos,
            request: $request
        );

        // 4. Redirige a la lista de proveedores de la empresa con un mensaje de éxito en la sesión flash.
        return redirect()
            ->route(
                'proveedores.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
            ->with(
                'success',
                'El proveedor fue creado correctamente.'
            );
    }

    // ---------------------------------------------------------
    // FORMULARIO DE EDICIÓN
    // Comprueba que el proveedor pertenezca a la empresa y
    // carga los datos de auditoría que mostrará la vista.
    // ---------------------------------------------------------

    public function edit(
        Empresa $empresa,
        Proveedor $proveedor
    ): View {
        abort_unless(
            (int) $proveedor->empresa_id
            === (int) $empresa->id,
            404
        );

        $proveedor->load([
            'creadoPor:id,name',
            'actualizadoPor:id,name',
            'desactivadoPor:id,name',
        ]);

        /** @var view-string $vista */
        $vista = 'proveedores.edit';

        return view(
            $vista,
            compact(
                'empresa',
                'proveedor',
            )
        );
    }

    // ---------------------------------------------------------
    // ACTUALIZACIÓN DEL PROVEEDOR
    // Recibe datos validados y delega la operación a la acción
    // transaccional ActualizarProveedor.
    // ---------------------------------------------------------

    public function update(
        ActualizarProveedorRequest $request,
        Empresa $empresa,
        Proveedor $proveedor,
        ActualizarProveedor $actualizarProveedor
    ): RedirectResponse {
        // -----------------------------------------------------
        // ACTOR AUTENTICADO
        // El Form Request ya autorizó el permiso, pero esta
        // comprobación garantiza un objeto User válido.
        // -----------------------------------------------------

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        // -----------------------------------------------------
        // DATOS VALIDADOS
        // Solo contiene los campos permitidos por el Request.
        // No incluye estado, empresa ni datos de auditoría.
        // -----------------------------------------------------

        $datos = $request->validated();

        // -----------------------------------------------------
        // EJECUCIÓN TRANSACCIONAL
        // Normaliza los datos, evita códigos duplicados y crea
        // el registro de auditoría.
        // -----------------------------------------------------

        $actualizarProveedor->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $actor,
            datos: $datos,
            request: $request
        );

        // -----------------------------------------------------
        // REDIRECCIÓN
        // Regresa al formulario del proveedor actualizado.
        // -----------------------------------------------------

        return redirect()
            ->route(
                'proveedores.edit',
                [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]
            )
            ->with(
                'success',
                'El proveedor fue actualizado correctamente.'
            );
    }

    // ---------------------------------------------------------
    // CAMBIO DE ESTADO
    // Desactiva o reactiva un proveedor sin eliminarlo.
    // El motivo es obligatorio y queda registrado en auditoría.
    // ---------------------------------------------------------

    public function updateStatus(
        CambiarEstadoProveedorRequest $request,
        Empresa $empresa,
        Proveedor $proveedor,
        CambiarEstadoProveedor $cambiarEstado
    ): RedirectResponse {
        // -----------------------------------------------------
        // ACTOR AUTENTICADO
        // Confirma que exista un usuario válido en la sesión.
        // -----------------------------------------------------

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        // -----------------------------------------------------
        // DATOS VALIDADOS
        // Este endpoint solo acepta "activar" y "motivo".
        // -----------------------------------------------------

        $datos = $request->validated();

        $activar = $datos['activar'] ?? null;
        $motivo = $datos['motivo'] ?? null;

        abort_unless(
            is_bool($activar),
            422
        );

        abort_unless(
            is_string($motivo),
            422
        );

        // -----------------------------------------------------
        // EJECUCIÓN TRANSACCIONAL
        // Actualiza el estado y registra valores anteriores,
        // nuevos, actor, motivo, IP y sesión.
        // -----------------------------------------------------

        $cambiarEstado->ejecutar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $actor,
            activar: $activar,
            motivo: $motivo,
            request: $request
        );

        // -----------------------------------------------------
        // MENSAJE DINÁMICO
        // Cambia según se haya reactivado o desactivado.
        // -----------------------------------------------------

        $mensaje = $activar
            ? 'El proveedor fue reactivado correctamente.'
            : 'El proveedor fue desactivado correctamente.';

        return redirect()
            ->route(
                'proveedores.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
            ->with(
                'success',
                $mensaje
            );
    }
}
