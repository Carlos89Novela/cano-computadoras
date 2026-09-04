<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
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
        $columnasPermitidas = [
            'folio' => 'orden_servicios.folio',
            'estado' => 'orden_servicios.estado',
            'fecha_ingreso' => 'orden_servicios.fecha_ingreso',
            'costo_final' => 'orden_servicios.costo_final',
        ];

        $query = OrdenServicio::query()
            ->with([
                'user:id,name',
                'equipo:id,marca,modelo',
            ])
            ->select('orden_servicios.*');

        $recordsTotal = OrdenServicio::query()->count();

        $estadoFiltro = $request->string('estado')->trim()->toString();

        if (
            $estadoFiltro !== ''
            && $estadoFiltro !== 'all'
            && in_array($estadoFiltro, EstadoOrden::valores(), true)
        ) {
            $query->where(
                'orden_servicios.estado',
                $estadoFiltro
            );
        }

        $search = $request->input('search.value');

        if (is_string($search) && trim($search) !== '') {
            $search = trim($search);

            $query->where(function ($consulta) use ($search) {
                $consulta
                    ->where(
                        'orden_servicios.folio',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'orden_servicios.estado',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas('user', function ($usuario) use ($search) {
                        $usuario->where(
                            'name',
                            'like',
                            "%{$search}%"
                        );
                    })
                    ->orWhereHas('equipo', function ($equipo) use ($search) {
                        $equipo
                            ->where(
                                'marca',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'modelo',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'numero_serie',
                                'like',
                                "%{$search}%"
                            );
                    });
            });
        }

        $recordsFiltered = (clone $query)->count();

        $orderColumnIndex = $request->integer('order.0.column');
        $orderColumn = $request->input(
            "columns.{$orderColumnIndex}.data"
        );

        $orderDirection = strtolower(
            (string) $request->input('order.0.dir', 'desc')
        );

        if (! in_array($orderDirection, ['asc', 'desc'], true)) {
            $orderDirection = 'desc';
        }

        if ($orderColumn === 'cliente') {
            $query->orderBy(
                User::query()
                    ->select('name')
                    ->whereColumn(
                        'users.id',
                        'orden_servicios.user_id'
                    )
                    ->limit(1),
                $orderDirection
            );
        } elseif ($orderColumn === 'equipo') {
            $query
                ->orderBy(
                    Equipo::query()
                        ->select('marca')
                        ->whereColumn(
                            'equipos.id',
                            'orden_servicios.equipo_id'
                        )
                        ->limit(1),
                    $orderDirection
                )
                ->orderBy(
                    Equipo::query()
                        ->select('modelo')
                        ->whereColumn(
                            'equipos.id',
                            'orden_servicios.equipo_id'
                        )
                        ->limit(1),
                    $orderDirection
                );
        } elseif (
            is_string($orderColumn)
            && array_key_exists($orderColumn, $columnasPermitidas)
        ) {
            $query->orderBy(
                $columnasPermitidas[$orderColumn],
                $orderDirection
            );
        } else {
            $query->latest('orden_servicios.id');
        }

        $start = max(
            $request->integer('start'),
            0
        );

        $requestedLength = $request->integer('length', 10);

        $length = min(
            max($requestedLength, 1),
            100
        );

        $rows = $query
            ->skip($start)
            ->take($length)
            ->get();

        $data = $rows->map(function (OrdenServicio $orden): array {

            $equipo = trim(implode(' ', array_filter([
                $orden->equipo?->marca,
                $orden->equipo?->modelo,
            ])));

            return [
                'select' => view(
                    'admin.ordenes.partials.select-checkbox',
                    ['orden' => $orden]
                )->render(),

                'folio' => view(
                    'admin.ordenes.partials.folio-link',
                    ['orden' => $orden]
                )->render(),

                'cliente' => e($orden->user?->name ?? ''),

                'equipo' => e($equipo),

                'estado' => view(
                    'admin.ordenes.partials.estado-badge',
                    ['estado' => $orden->estado]
                )->render(),

                'fecha_ingreso' => $orden->fecha_ingreso?->format(
                    'd/m/Y'
                ) ?? '',

                'costo_final' => (float) ($orden->costo_final ?? 0),

                'acciones' => view(
                    'admin.ordenes.partials.acciones',
                    ['orden' => $orden]
                )->render(),
            ];
        })->values();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
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
