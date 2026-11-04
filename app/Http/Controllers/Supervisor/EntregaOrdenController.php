<?php

namespace App\Http\Controllers\Supervisor;

use App\Actions\Ordenes\EntregarOrdenServicio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\EntregarOrdenServicioRequest;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class EntregaOrdenController extends Controller
{
    public function store(
        EntregarOrdenServicioRequest $request,
        OrdenServicio $orden,
        EntregarOrdenServicio $entregarOrden
    ): RedirectResponse {
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        $datos = $request->validated();
        $comentario = $datos['comentario'] ?? null;
        $accionComprobante = (string) ($datos['accion_comprobante'] ?? 'imprimir');

        $entregarOrden->ejecutar(
            $orden,
            $usuario,
            is_string($comentario) ? $comentario : null
        );

        $mensajeExito = match ($accionComprobante) {
            'correo' => 'La entrega del equipo fue registrada y se envió el comprobante por correo al cliente.',
            'ambas' => 'Entrega registrada: se envió el comprobante por correo y se preparó el PDF para impresión.',
            'imprimir' => 'Entrega registrada: se generó el comprobante listo para imprimir.',
            default => 'La entrega del equipo fue registrada correctamente.',
        };

        $destino = $usuario->hasRole('administrador')
            ? redirect()->route('admin.ordenes.edit', ['orden' => $orden->id])
            : redirect()->to(route('supervisor.dashboard').'#entregas');

        $respuesta = $destino->with('success', $mensajeExito);

        // Si se solicitó imprimir o ambas, adjunta la URL del PDF a la sesión
        if (in_array($accionComprobante, ['imprimir', 'ambas'], true)) {
            $respuesta->with(
                'imprimir_comprobante_url',
                route('admin.ordenes.pdf', ['orden' => $orden->id])
            );
        }

        return $respuesta;
    }
}
