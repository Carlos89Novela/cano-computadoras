<?php

namespace App\Http\Controllers\Empleado;

use App\Actions\Ordenes\ActualizarTrabajoTecnico;
use App\Actions\Ordenes\EnviarReparacionAPruebas;
use App\Actions\Ordenes\IniciarReparacionAutorizada;
use App\Actions\Ordenes\MarcarReparacionListaParaEntrega;
use App\Actions\Ordenes\SolicitarRevisionCotizacion;
use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Http\Requests\Empleado\EnviarReparacionAPruebasRequest;
use App\Http\Requests\Empleado\MarcarReparacionListaParaEntregaRequest;
use App\Http\Requests\Empleado\UpdateOrdenTecnicaRequest;
use App\Models\OrdenAsignacion;
use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrdenAsignadaController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        $consultaBase = OrdenAsignacion::query()
            ->where('empleado_id', $usuario->id)
            ->where('activo', true)
            ->whereHas(
                'ordenServicio',
                function ($consulta): void {
                    $consulta->whereNotIn(
                        'estado',
                        EstadoOrden::finalizados()
                    );
                }
            );

        $recordsTotal = (clone $consultaBase)->count();

        $search = $request->input('search.value');

        if (is_string($search) && trim($search) !== '') {
            $search = trim($search);

            $consultaBase->whereHas(
                'ordenServicio',
                function ($consulta) use ($search): void {
                    $consulta
                        ->where(
                            'folio',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'estado',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'user',
                            function ($usuarioConsulta) use ($search): void {
                                $usuarioConsulta->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        )
                        ->orWhereHas(
                            'equipo',
                            function ($equipoConsulta) use ($search): void {
                                $equipoConsulta
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
                            'servicio',
                            function ($servicioConsulta) use ($search): void {
                                $servicioConsulta->where(
                                    'nombre',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        );
                }
            );
        }

        $recordsFiltered = (clone $consultaBase)->count();

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

        if ($orderColumn === 'fecha_asignacion') {
            $consultaBase->orderBy(
                'asignado_at',
                $orderDirection
            );
        } else {
            $consultaBase->latest('asignado_at');
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

        $rows = $consultaBase
            ->with([
                'ordenServicio.user:id,name',
                'ordenServicio.equipo:id,marca,modelo',
                'ordenServicio.servicio:id,nombre',
            ])
            ->skip($start)
            ->take($length)
            ->get();

        $data = $rows
            ->map(function (OrdenAsignacion $asignacion): array {
                $orden = $asignacion->ordenServicio;

                $equipo = trim(
                    $orden->equipo->marca
                    .' '
                    .$orden->equipo->modelo
                );

                return [
                    'folio' => e($orden->folio),
                    'cliente' => e($orden->user->name),
                    'equipo' => e($equipo),
                    'servicio' => e(
                        $orden->servicio_id === null
                        ? 'Diagnóstico general'
                        : $orden->servicio->nombre
                    ),
                    'estado' => e($orden->estado),
                    'fecha_asignacion' => $asignacion
                        ->asignado_at
                        ->format('d/m/Y H:i'),
                    'acciones' => view(
                        'empleado.ordenes.partials.acciones',
                        compact('orden')
                    )->render(),
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

    public function updateTechnical(
        UpdateOrdenTecnicaRequest $request,
        OrdenServicio $orden,
        ActualizarTrabajoTecnico $actualizarTrabajoTecnico
    ): RedirectResponse {
        $empleado = $request->user();

        abort_unless(
            $empleado instanceof User,
            403
        );

        $actualizarTrabajoTecnico->ejecutar(
            $orden,
            $empleado,
            $request->validated()
        );

        return redirect()
            ->route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
            ->with(
                'success',
                'La información técnica fue actualizada correctamente.'
            );
    }

    public function requestQuoteReview(
        Request $request,
        OrdenServicio $orden,
        SolicitarRevisionCotizacion $solicitarRevisionCotizacion
    ): RedirectResponse {
        Gate::authorize(
            'requestQuoteReview',
            $orden
        );

        $empleado = $request->user();

        abort_unless(
            $empleado instanceof User,
            403
        );

        try {
            $solicitarRevisionCotizacion->ejecutar(
                $orden,
                $empleado
            );
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('empleado.ordenes.show', [
                    'orden' => $orden->id,
                ])
                ->withErrors([
                    'cotizacion' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('empleado.ordenes.show', [
                'orden' => $orden->id,
            ])
            ->with(
                'success',
                'La cotizacion fue enviada a revision del supervisor.'
            );
    }

    public function startRepair(
        Request $request,
        OrdenServicio $orden,
        IniciarReparacionAutorizada $iniciarReparacion
    ): RedirectResponse {
        Gate::authorize(
            'startAuthorizedRepair',
            $orden
        );

        $empleado = $request->user();

        abort_unless(
            $empleado instanceof User,
            403
        );

        $iniciarReparacion->ejecutar(
            $orden,
            $empleado
        );

        return redirect()
            ->route(
                'empleado.ordenes.show',
                [
                    'orden' => $orden->id,
                ]
            )
            ->with(
                'success',
                'La reparación fue iniciada correctamente.'
            );
    }

    public function sendToTesting(
        EnviarReparacionAPruebasRequest $request,
        OrdenServicio $orden,
        EnviarReparacionAPruebas $enviarReparacionAPruebas
    ): RedirectResponse {
        $empleado = $request->user();

        abort_unless(
            $empleado instanceof User,
            403
        );

        $datos = $request->validated();

        $comentario = $datos['comentario'] ?? null;

        $enviarReparacionAPruebas->ejecutar(
            $orden,
            $empleado,
            is_string($comentario)
                ? $comentario
                : null
        );

        return redirect()
            ->route(
                'empleado.ordenes.show',
                [
                    'orden' => $orden->id,
                ]
            )
            ->with(
                'success',
                'La reparación fue enviada a pruebas correctamente.'
            );
    }

    public function markReadyForDelivery(
        MarcarReparacionListaParaEntregaRequest $request,
        OrdenServicio $orden,
        MarcarReparacionListaParaEntrega $marcarListaParaEntrega
    ): RedirectResponse {
        $empleado = $request->user();

        abort_unless(
            $empleado instanceof User,
            403
        );

        $datos = $request->validated();

        $costoFinal = (float) $datos['costo_final'];

        $comentario = $datos['comentario'] ?? null;

        $marcarListaParaEntrega->ejecutar(
            $orden,
            $empleado,
            $costoFinal,
            is_string($comentario)
                ? $comentario
                : null
        );

        return redirect()
            ->route('empleado.dashboard')
            ->with(
                'success',
                'La reparación quedó lista para entrega.'
            );
    }

    public function show(
        OrdenServicio $orden
    ): View {
        Gate::authorize(
            'viewAssigned',
            $orden
        );

        $orden->load([
            'user:id,name',
            'equipo:id,tipo,marca,modelo,numero_serie',
            'servicio:id,nombre',
            'historial.usuario:id,name',
            'asignacionActiva.empleado:id,name',
            'asignacionActiva.asignadoPor:id,name',
        ]);

        return view(
            'empleado.ordenes.show',
            compact('orden')
        );
    }
}
