<?php

namespace App\Http\Controllers\Supervisor;

use App\Actions\Ordenes\AsignarOrden;
use App\Http\Controllers\Controller;
use App\Http\Requests\AsignarOrdenRequest;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Controlador para la gestión de asignaciones de órdenes de servicio.
 *
 * Expone el punto de entrada HTTP para que supervisores o administradores vinculen
 * una orden de servicio a un técnico responsable, permitiendo la asignación inicial
 * o la reasignación en caso de redistribución de cargas de trabajo.
 */
class AsignacionController extends Controller
{
    /**
     * Registra o actualiza la asignación de un empleado técnico a una orden de servicio.
     *
     * @param  AsignarOrdenRequest  $request  Petición HTTP validada con el ID del técnico y observaciones opcionales.
     * @param  OrdenServicio  $orden  Instancia de la orden de servicio inyectada por route model binding.
     * @param  AsignarOrden  $asignarOrden  Acción de dominio encargada de la lógica de asignación e historial.
     * @return RedirectResponse Redirección a la vista previa con mensaje de confirmación de éxito.
     */
    public function store(
        AsignarOrdenRequest $request,
        OrdenServicio $orden,
        AsignarOrden $asignarOrden
    ): RedirectResponse {
        // Recupera los datos validados del formulario
        $datos = $request->validated();

        // Obtiene el supervisor o administrador autenticado que ejecuta la acción
        $usuario = $request->user();

        // Salvaguarda de autenticación estricta
        abort_unless(
            $usuario instanceof User,
            403
        );

        // Localiza el técnico seleccionado en el sistema
        $empleado = User::query()
            ->whereKey($datos['empleado_id'])
            ->firstOrFail();

        // Determina si ya existía un técnico asignado para personalizar el mensaje de respuesta
        $asignacionAnterior = $orden
            ->asignacionActiva()
            ->first();

        // Ejecuta la acción de dominio que desactiva asignaciones previas, crea la nueva y genera auditoría
        $asignarOrden->ejecutar(
            $orden,
            $empleado,
            $usuario,
            $datos['observaciones'] ?? null
        );

        // Distingue entre asignación primaria y reasignación técnica
        $mensaje = $asignacionAnterior === null
            ? 'La reparación fue asignada correctamente.'
            : 'La reparación fue reasignada correctamente.';

        return back()->with(
            'success',
            $mensaje
        );
    }
}
