<?php

namespace App\Http\Controllers\Supervisor;

use App\Actions\Ordenes\AsignarOrden;
use App\Http\Controllers\Controller;
use App\Http\Requests\AsignarOrdenRequest;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class AsignacionController extends Controller
{
    public function store(
        AsignarOrdenRequest $request,
        OrdenServicio $orden,
        AsignarOrden $asignarOrden
    ): RedirectResponse {
        $datos = $request->validated();

        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        $empleado = User::query()
            ->whereKey($datos['empleado_id'])
            ->firstOrFail();

        $asignacionAnterior = $orden
            ->asignacionActiva()
            ->first();

        $asignarOrden->ejecutar(
            $orden,
            $empleado,
            $usuario,
            $datos['observaciones'] ?? null
        );

        $mensaje = $asignacionAnterior === null
            ? 'La reparación fue asignada correctamente.'
            : 'La reparación fue reasignada correctamente.';

        return back()->with(
            'success',
            $mensaje
        );
    }
}
