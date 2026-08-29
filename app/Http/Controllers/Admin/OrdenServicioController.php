<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrdenServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use App\Notifications\EstadoReparacionActualizado;

class OrdenServicioController extends Controller
{
    public function index(): View
    {
        $ordenes = OrdenServicio::query()
            ->with(['user', 'equipo'])
            ->latest()
            ->get();

        return view('admin.ordenes.index', compact('ordenes'));
    }

    public function edit(OrdenServicio $orden): View
    {
        $orden->load([
            'user',
            'equipo',
            'historial.usuario',
        ]);

        $estados = [
            'Recibido',
            'En diagnóstico',
            'Esperando autorización',
            'Esperando refacción',
            'En reparación',
            'En pruebas',
            'Listo para entrega',
            'Entregado',
            'Cancelado',
        ];

        return view(
            'admin.ordenes.edit',
            compact('orden', 'estados')
        );
    }

    public function update(
        Request $request,
        OrdenServicio $orden
    ): RedirectResponse {
        $datos = $request->validate([
            'estado' => [
                'required',
                'string',
                'in:Recibido,En diagnóstico,Esperando autorización,Esperando refacción,En reparación,En pruebas,Listo para entrega,Entregado,Cancelado',
            ],
            'diagnostico' => [
                'nullable',
                'string',
                'max:3000',
            ],
            'costo_estimado' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'costo_final' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $estadoAnterior = $orden->estado;

        $orden->update([
            'estado' => $datos['estado'],
            'diagnostico' => $datos['diagnostico'] ?? null,
            'costo_estimado' => $datos['costo_estimado'] ?? null,
            'costo_final' => $datos['costo_final'] ?? null,
            'fecha_entrega' => $datos['estado'] === 'Entregado'
                ? now()->toDateString()
                : $orden->fecha_entrega,
        ]);

        if (
            $estadoAnterior !== $datos['estado'] ||
            !empty($datos['comentarios'])
        ) {
            $orden->historial()->create([
                'user_id' => $request->user()->id,
                'estado' => $datos['estado'],
                'comentarios' => $datos['comentario']
                    ?? 'Estado actualizado por el administrador.',
            ]);
        }

        if ($estadoAnterior !== $orden->estado) {
            $orden->user->notify(
                new EstadoReparacionActualizado(
                    $orden,
                    $datos['comentario'] ?? null
                )
            );
        }

        return redirect()
            ->route('admin.ordenes.edit', [
                'orden' => $orden->id,
            ])
            ->with(
                'success',
                'La reparación fue actualizada correctamente.'
            );
    }

    public function pdf(
        OrdenServicio $orden
    ): Response {
        $orden->load([
            'user',
            'equipo',
            'servicio',
            'historial.usuario',
        ]);

        $pdf = Pdf::loadView(
            'pdf.orden-servicio',
            compact('orden')
        )->setPaper('a4', 'portrait');

        return $pdf->download(
            'orden-'.$orden->folio.'.pdf'
        );
    }
}