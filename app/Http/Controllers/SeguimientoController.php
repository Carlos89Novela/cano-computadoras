<?php

namespace App\Http\Controllers;

use App\Models\OrdenServicio;
use Illuminate\View\View;

class SeguimientoController extends Controller
{
    public function show(string $token): View
    {
        $orden = OrdenServicio::query()
            ->select([
                'id',
                'folio',
                'token_seguimiento',
                'equipo_id',
                'servicio_id',
                'estado',
                'fecha_ingreso',
                'fecha_entrega',
                'problema_reportado',
                'diagnostico',
                'costo_estimado',
                'costo_final',
            ])
            ->with([
                'equipo:id,tipo,marca,modelo',
                'servicio:id,nombre',
                'historial' => function ($query) {
                    $query
                        ->select([
                            'id',
                            'orden_servicio_id',
                            'estado',
                            'created_at',
                        ])
                        ->oldest('created_at');
                },
            ])
            ->where('token_seguimiento', $token)
            ->firstOrFail();

        return view(
            'seguimiento.show',
            compact('orden')
        );
    }
}
