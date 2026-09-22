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

        $entregarOrden->ejecutar(
            $orden,
            $usuario,
            is_string($comentario)
                ? $comentario
                : null
        );

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

        return redirect()
            ->to(route('supervisor.dashboard')
                .'#entregas')
            ->with(
                'success',
                'La entrega del equipo fue registrada correctamente.'
            );
    }
}
