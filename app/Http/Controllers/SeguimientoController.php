<?php

namespace App\Http\Controllers;

use App\Models\OrdenServicio;
use Illuminate\View\View;

class SeguimientoController extends Controller
{
    public function show(string $folio): View
    {
        $orden = OrdenServicio::query()
            ->with([
                'equipo',
                'servicio',
                'historial' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                },
            ])
            ->where('folio', $folio)
            ->firstOrFail();

        return view('seguimiento.show', compact('orden'));
    }
}