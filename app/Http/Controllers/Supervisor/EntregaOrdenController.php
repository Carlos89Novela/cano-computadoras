<?php

namespace App\Http\Controllers\Supervisor;

use App\Actions\Ordenes\EntregarOrdenServicio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\EntregarOrdenServicioRequest;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Controlador para registrar la entrega física de un equipo al cliente.
 *
 * Coordina la transición final del ciclo de vida de la orden de servicio:
 * de "Listo para entrega" a "Entregado", registrando el momento exacto,
 * las notas del cierre de entrega, y direccionando al usuario según su rol operativo.
 */
class EntregaOrdenController extends Controller
{
    /**
     * Registra la entrega del equipo reparado al cliente final.
     *
     * @param  EntregarOrdenServicioRequest  $request  Petición HTTP validada con comentarios opcionales de entrega.
     * @param  OrdenServicio  $orden  Orden de servicio a finalizar.
     * @param  EntregarOrdenServicio  $entregarOrden  Acción de dominio que ejecuta la transición de estado.
     * @return RedirectResponse Redirección al detalle de administración o a la pestaña de entregas del supervisor.
     */
    public function store(
        EntregarOrdenServicioRequest $request,
        OrdenServicio $orden,
        EntregarOrdenServicio $entregarOrden
    ): RedirectResponse {
        // Obtiene el usuario autenticado que autoriza y efectúa la entrega física
        $usuario = $request->user();

        // Verificación de autenticación estricta
        abort_unless(
            $usuario instanceof User,
            403
        );

        $datos = $request->validated();

        $comentario = $datos['comentario'] ?? null;

        // Ejecuta la acción de dominio que valida precondiciones (autorizada, lista para entrega, costo final fijado),
        // marca la fecha de entrega, transiciona el estado a ENTREGADO, crea el evento en historial y audita.
        $entregarOrden->ejecutar(
            $orden,
            $usuario,
            is_string($comentario)
                ? $comentario
                : null
        );

        // Si la entrega fue procesada por un administrador, regresa a la pantalla de edición global de la orden
        if ($usuario->hasRole('administrador')) {
            return redirect()
                ->route('admin.ordenes.edit', [
                    'orden' => $orden->id,
                ])
                ->with(
                    'success',
                    'La entrega del equipo fue registrada correctamente.'
                );
        }

        // Si fue procesada por un supervisor, regresa al panel de supervisión en la sección de entregas
        return redirect()
            ->to(route('supervisor.dashboard')
                .'#entregas')
            ->with(
                'success',
                'La entrega del equipo fue registrada correctamente.'
            );
    }
}
