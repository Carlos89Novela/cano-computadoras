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

    public function data(Request $request)
    {
        $columns = [
            'folio',
            'cliente',
            'equipo',
            'estado',
            'fecha_ingreso',
        ];

        $query = OrdenServicio::query()
            ->with(['user', 'equipo'])
            ->select('orden_servicios.*');

        $total = $query->count();

        // Filtering (global search)
        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('equipo', function ($q3) use ($search) {
                        $q3->where('marca', 'like', "%{$search}%")
                            ->orWhere('modelo', 'like', "%{$search}%");
                    })
                    ->orWhere('estado', 'like', "%{$search}%");
            });
        }

        $filtered = $query->count();

        // Ordering
        $orderColIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc');
        if ($orderColIndex !== null && isset($columns[$orderColIndex])) {
            $col = $columns[$orderColIndex];
            if ($col === 'cliente') {
                $query->join('users', 'orden_servicios.user_id', '=', 'users.id')
                    ->orderBy('users.name', $orderDir);
            } elseif ($col === 'equipo') {
                $query->join('equipos', 'orden_servicios.equipo_id', '=', 'equipos.id')
                    ->orderBy('equipos.marca', $orderDir)
                    ->orderBy('equipos.modelo', $orderDir);
            } else {
                $query->orderBy('orden_servicios.'.$col, $orderDir);
            }
        } else {
            $query->latest('id');
        }

        // Pagination
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $rows = $query->skip($start)->take($length)->get();

        $data = $rows->map(function ($orden) {
            return [
                'folio' => '<a class="text-purple-500 font-semibold" href="'.route('admin.ordenes.edit', ['orden' => $orden->id]).'">'.$orden->folio.'</a>',
                'cliente' => $orden->user?->name ?? '',
                'equipo' => ($orden->equipo?->marca ?? '').' '.($orden->equipo?->modelo ?? ''),
                'estado' => '<span class="inline-block rounded-full bg-purple-950 px-3 py-1 text-sm text-purple-200">'.e($orden->estado).'</span>',
                'fecha_ingreso' => $orden->fecha_ingreso?->format('d/m/Y') ?? '',
                'acciones' => '<a href="'.route('admin.ordenes.edit', ['orden' => $orden->id]).'" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Administrar</a>',
            ];
        })->toArray();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
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
            !empty($datos['comentario'])
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