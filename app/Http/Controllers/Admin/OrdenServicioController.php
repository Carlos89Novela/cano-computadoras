<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\OrdenServicio;
use App\Notifications\EstadoReparacionActualizado;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrdenServicioController extends Controller
{
    public function index(): View
    {
        $ordenes = OrdenServicio::query()
            ->with(['user', 'equipo'])
            ->latest()
            ->get();

        $estados = EstadoOrden::valores();
        $estadosRapidos = EstadoOrden::filtrosRapidos();

        return view('admin.ordenes.index', compact(
            'ordenes',
            'estados',
            'estadosRapidos'
        ));
    }

    public function data(Request $request)
    {
        $columns = [
            'folio',
            'cliente',
            'equipo',
            'estado',
            'fecha_ingreso',
            'costo_final',
        ];

        $query = OrdenServicio::query()
            ->with(['user', 'equipo'])
            ->select('orden_servicios.*');

        $estadoFiltro = $request->input('estado');
        if (! empty($estadoFiltro) && $estadoFiltro !== 'all') {
            $query->where('orden_servicios.estado', $estadoFiltro);
        }

        $total = $query->count();

        // Filtering (global search)
        $search = $request->input('search.value');
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('orden_servicios.folio', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('equipo', function ($q3) use ($search) {
                        $q3->where('marca', 'like', "%{$search}%")
                            ->orWhere('modelo', 'like', "%{$search}%");
                    })
                    ->orWhere('orden_servicios.estado', 'like', "%{$search}%");
            });
        }

        $filtered = $query->count();

        // Ordering
        $orderColIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc');
        if ($orderColIndex !== null && isset($columns[$orderColIndex])) {
            $col = $columns[$orderColIndex];
            if ($col === 'cliente') {
                $query->leftJoin('users', 'orden_servicios.user_id', '=', 'users.id')
                    ->orderBy('users.name', $orderDir);
            } elseif ($col === 'equipo') {
                $query->leftJoin('equipos', 'orden_servicios.equipo_id', '=', 'equipos.id')
                    ->orderBy('equipos.marca', $orderDir)
                    ->orderBy('equipos.modelo', $orderDir);
            } else {
                $query->orderBy('orden_servicios.'.$col, $orderDir);
            }
        } else {
            $query->latest('orden_servicios.id');
        }

        // Pagination
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $rows = $query->skip($start)->take($length)->get();

        $data = $rows->map(function ($orden) {
            return [
                'select' => '<input type="checkbox" class="orden-select" data-id="'.$orden->id.'">',
                'folio' => '<a class="text-purple-500 font-semibold" href="'.route('admin.ordenes.edit', ['orden' => $orden->id]).'">'.$orden->folio.'</a>',
                'cliente' => $orden->user?->name ?? '',
                'equipo' => ($orden->equipo?->marca ?? '').' '.($orden->equipo?->modelo ?? ''),
                'estado' => view('admin.ordenes.partials.estado-badge', ['estado' => $orden->estado])->render(),
                'fecha_ingreso' => $orden->fecha_ingreso?->format('d/m/Y') ?? '',
                'costo_final' => $orden->costo_final ?? 0,
                'acciones' => '<div class="flex flex-wrap gap-2"><a href="'.route('admin.ordenes.edit', ['orden' => $orden->id]).'" class="rounded-md border border-purple-600 bg-purple-600 px-3 py-2 text-xs font-medium text-white hover:bg-purple-500">Ver detalle</a><a href="'.route('admin.ordenes.edit', ['orden' => $orden->id]).'" class="rounded-md border border-blue-600 bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-500">Administrar</a></div>',
            ];
        })->toArray();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $datos = $request->validate([
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'integer',
                'distinct',
                'exists:orden_servicios,id',
            ],
            'estado' => [
                'required',
                Rule::enum(EstadoOrden::class),
            ],
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $ordenes = OrdenServicio::query()
            ->with('user')
            ->whereIn('id', $datos['ids'])
            ->get();

        $ordenesActualizadas = 0;

        foreach ($ordenes as $orden) {
            $estadoAnterior = $orden->estado;
            $estadoCambio = $estadoAnterior !== $datos['estado'];
            $tieneComentario = filled($datos['comentario'] ?? null);

            if (! $estadoCambio && ! $tieneComentario) {
                continue;
            }

            if ($estadoCambio) {
                $orden->update([
                    'estado' => $datos['estado'],
                    'fecha_entrega' => $datos['estado'] === EstadoOrden::ENTREGADO->value
                        ? now()->toDateString()
                        : $orden->fecha_entrega,
                ]);
            }

            $orden->historial()->create([
                'user_id' => $request->user()->id,
                'estado' => $orden->estado,
                'comentarios' => $datos['comentario']
                    ?? 'Cambio masivo de estado realizado por el administrador.',
            ]);

            if ($estadoCambio) {
                $orden->user->notify(
                    new EstadoReparacionActualizado(
                        $orden,
                        $datos['comentario'] ?? null
                    )
                );
            }

            $ordenesActualizadas++;
        }

        return response()->json([
            'success' => true,
            'updated' => $ordenesActualizadas,
        ]);
    }

    public function edit(OrdenServicio $orden): View
    {
        $orden->load([
            'user',
            'equipo',
            'historial.usuario',
        ]);

        $estados = EstadoOrden::valores();

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
                Rule::enum(EstadoOrden::class),
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
            'fecha_entrega' => $datos['estado'] === EstadoOrden::ENTREGADO->value
                ? now()->toDateString()
                : $orden->fecha_entrega,
        ]);

        if (
            $estadoAnterior !== $datos['estado'] ||
            ! empty($datos['comentario'])
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

    public function exportCsv(Request $request): Response
    {
        $query = OrdenServicio::query()
            ->with(['user', 'equipo'])
            ->orderBy('fecha_ingreso', 'desc')
            ->orderBy('id', 'desc');

        $estado = (string) $request->query('estado', 'all');
        if (! empty($estado) && $estado !== 'all') {
            $query->where('estado', $estado);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('orden_servicios.folio', 'like', "%{$search}%")
                    ->orWhere('orden_servicios.estado', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('equipo', function ($q3) use ($search) {
                        $q3->where('marca', 'like', "%{$search}%")
                            ->orWhere('modelo', 'like', "%{$search}%");
                    });
            });
        }

        $ordenes = $query->get();

        $headers = ['Folio', 'Cliente', 'Equipo', 'Estado', 'Fecha de ingreso', 'Costo final'];
        $rows = [$headers];

        foreach ($ordenes as $orden) {
            $rows[] = [
                $orden->folio,
                $orden->user?->name ?? '-',
                trim(($orden->equipo?->marca ?? '').' '.($orden->equipo?->modelo ?? '')) ?: '-',
                $orden->estado ?? '-',
                $orden->fecha_ingreso?->format('d/m/Y') ?? '-',
                '$'.number_format((float) ($orden->costo_final ?? 0), 2, '.', ','),
            ];
        }

        $csv = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        $filename = 'ordenes-cano-computadoras'.($estado !== 'all' ? '-'.Str::slug($estado) : '').'-'.now()->format('Y-m-d').'.csv';

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $query = OrdenServicio::query()
            ->with(['user', 'equipo'])
            ->orderBy('fecha_ingreso', 'desc')
            ->orderBy('id', 'desc');

        $estado = (string) $request->query('estado', 'all');
        if (! empty($estado) && $estado !== 'all') {
            $query->where('estado', $estado);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('orden_servicios.folio', 'like', "%{$search}%")
                    ->orWhere('orden_servicios.estado', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('equipo', function ($q3) use ($search) {
                        $q3->where('marca', 'like', "%{$search}%")
                            ->orWhere('modelo', 'like', "%{$search}%");
                    });
            });
        }

        $ordenes = $query->get();

        $resumenPorEstado = $ordenes
            ->groupBy('estado')
            ->map(function ($items, $estado) {
                return [
                    'cantidad' => $items->count(),
                    'total' => $items->sum(fn ($orden) => (float) ($orden->costo_final ?? 0)),
                ];
            })
            ->sortKeys();

        $totalOrdenes = $ordenes->count();
        $totalIngresos = $ordenes->sum(fn ($orden) => (float) ($orden->costo_final ?? 0));

        $pdf = Pdf::loadView('pdf.ordenes-reporte', [
            'ordenes' => $ordenes,
            'estado' => $estado,
            'fechaGeneracion' => now()->format('d/m/Y H:i'),
            'resumenPorEstado' => $resumenPorEstado,
            'totalOrdenes' => $totalOrdenes,
            'totalIngresos' => $totalIngresos,
        ])->setPaper('a4', 'landscape');

        $filename = 'reporte-ordenes'.($estado !== 'all' ? '-'.Str::slug($estado) : '').'-'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
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
