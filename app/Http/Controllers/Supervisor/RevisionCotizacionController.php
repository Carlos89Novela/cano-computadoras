<?php

namespace App\Http\Controllers\Supervisor;

use App\Actions\Ordenes\AprobarRevisionCotizacion;
use App\Actions\Ordenes\RechazarRevisionCotizacion;
use App\Enums\EstadoRevisionCotizacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Supervisor\RechazarRevisionCotizacionRequest;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RevisionCotizacionController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        abort_unless(
            $usuario->can('ordenes.aprobar_cotizacion')
            || $usuario->can('ordenes.rechazar_cotizacion'),
            403
        );

        $consulta = OrdenServicio::query()
            ->with([
                'user:id,name',
                'equipo:id,marca,modelo',
                'asignacionActiva.empleado:id,name',
            ])
            ->where(
                'estado_revision_cotizacion',
                EstadoRevisionCotizacion::PENDIENTE->value
            );

        $recordsTotal = (clone $consulta)->count();

        $search = $request->input('search.value');

        if (is_string($search) && trim($search) !== '') {
            $search = trim($search);

            $consulta->where(function ($query) use ($search): void {
                $query
                    ->where(
                        'folio',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'diagnostico',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'user',
                        function ($cliente) use ($search): void {
                            $cliente->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    )
                    ->orWhereHas(
                        'equipo',
                        function ($equipo) use ($search): void {
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
                                );
                        }
                    )
                    ->orWhereHas(
                        'asignacionActiva.empleado',
                        function ($empleado) use ($search): void {
                            $empleado->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
            });
        }

        $recordsFiltered = (clone $consulta)->count();

        $orderDirection = strtolower(
            (string) $request->input(
                'order.0.dir',
                'desc'
            )
        );

        if (! in_array($orderDirection, ['asc', 'desc'], true)) {
            $orderDirection = 'desc';
        }

        $orderColumnIndex = $request->integer(
            'order.0.column'
        );

        $orderColumn = $request->input(
            "columns.{$orderColumnIndex}.data"
        );

        $columnasPermitidas = [
            'folio' => 'folio',
            'costo_estimado' => 'costo_estimado',
            'fecha_solicitud' => 'updated_at',
        ];

        if (
            is_string($orderColumn)
            && array_key_exists(
                $orderColumn,
                $columnasPermitidas
            )
        ) {
            $consulta->orderBy(
                $columnasPermitidas[$orderColumn],
                $orderDirection
            );
        } else {
            $consulta->latest('updated_at');
        }

        $start = max(
            $request->integer('start'),
            0
        );

        $requestedLength = $request->integer(
            'length',
            10
        );

        $length = min(
            max($requestedLength, 1),
            100
        );

        $ordenes = $consulta
            ->skip($start)
            ->take($length)
            ->get();

        $data = $ordenes
            ->map(function (OrdenServicio $orden): array {
                $equipo = trim(
                    $orden->equipo->marca
                    .' '
                    .$orden->equipo->modelo
                );

                return [
                    'folio' => e($orden->folio),
                    'empleado' => e(
                        $orden->asignacionActiva?->empleado->name
                        ?? 'Sin empleado'
                    ),
                    'cliente' => e($orden->user->name),
                    'equipo' => e($equipo),
                    'diagnostico' => e(
                        $orden->diagnostico
                        ?? 'Sin diagnóstico'
                    ),
                    'costo_estimado' => (float) (
                        $orden->costo_estimado
                        ?? 0
                    ),
                    'fecha_solicitud' => $orden
                        ->updated_at
                        ->format('d/m/Y H:i'),
                    'orden_id' => $orden->id,
                ];
            })
            ->values();

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function show(
        OrdenServicio $orden
    ): View {
        Gate::authorize(
            'viewQuoteReview',
            $orden
        );

        $orden->load([
            'user:id,name',
            'equipo:id,tipo,marca,modelo,numero_serie',
            'servicio:id,nombre',
            'asignacionActiva.empleado:id,name',
            'historial.usuario:id,name',
        ]);

        return view(
            'supervisor.cotizaciones.show',
            compact('orden')
        );
    }

    public function approve(
        Request $request,
        OrdenServicio $orden,
        AprobarRevisionCotizacion $aprobarRevisionCotizacion
    ): RedirectResponse {
        Gate::authorize(
            'approveQuoteReview',
            $orden
        );

        $revisor = $request->user();

        abort_unless(
            $revisor instanceof User,
            403
        );

        $aprobarRevisionCotizacion->ejecutar(
            $orden,
            $revisor
        );

        $rutaDestino = $revisor->hasRole('administrador')
            ? 'admin.dashboard'
            : 'supervisor.dashboard';

        return redirect()
            ->route($rutaDestino)
            ->with(
                'success',
                'La cotización fue aprobada correctamente.'
            );

    }

    public function reject(
        RechazarRevisionCotizacionRequest $request,
        OrdenServicio $orden,
        RechazarRevisionCotizacion $rechazarRevisionCotizacion
    ): RedirectResponse {
        $revisor = $request->user();

        abort_unless(
            $revisor instanceof User,
            403
        );

        $datos = $request->validated();

        $rechazarRevisionCotizacion->ejecutar(
            $orden,
            $revisor,
            $datos['observacion']
        );

        $rutaDestino = $revisor->hasRole('administrador')
            ? 'admin.dashboard'
            : 'supervisor.dashboard';

        return redirect()
            ->route($rutaDestino)
            ->with(
                'success',
                'La cotización fue devuelta al empleado.'
            );
    }
}
