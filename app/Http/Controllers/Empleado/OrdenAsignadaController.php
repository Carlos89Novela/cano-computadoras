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

/**
 * Controlador de Gestión Operativa de Órdenes Asignadas al Empleado Técnico.
 *
 * Administra el flujo de trabajo técnico completo sobre las órdenes de servicio
 * asignadas al técnico autenticado:
 * 1. Consulta y filtrado AJAX server-side para DataTables.
 * 2. Actualización de diagnóstico técnico, cotización estimada y notas de taller.
 * 3. Envío de cotización a revisión de supervisión.
 * 4. Inicio de reparación autorizada por el cliente.
 * 5. Envío a pruebas de control de calidad.
 * 6. Marcado de la reparación como lista para entrega al cliente.
 * 7. Vista detallada del historial y expediente del equipo en reparación.
 */
class OrdenAsignadaController extends Controller
{
    /**
     * Procesa la solicitud AJAX de DataTables con la cola de trabajo del técnico.
     *
     * @param  Request  $request  Petición HTTP con parámetros de búsqueda, orden y paginación.
     * @return JsonResponse Estructura JSON compatible con DataTables con registros y contadores.
     */
    public function data(Request $request): JsonResponse
    {
        // ---------------------------------------------------------------------
        // 1. Verificación del Actor
        // ---------------------------------------------------------------------
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        // ---------------------------------------------------------------------
        // 2. Consulta Base de Asignaciones Activas del Técnico
        // ---------------------------------------------------------------------
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

        // Conteo total previo a cualquier búsqueda
        $recordsTotal = (clone $consultaBase)->count();

        // ---------------------------------------------------------------------
        // 3. Filtro de Búsqueda Global en Datos de la Orden y Relaciones
        // ---------------------------------------------------------------------
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

        // Conteo filtrado
        $recordsFiltered = (clone $consultaBase)->count();

        // ---------------------------------------------------------------------
        // 4. Ordenamiento Seguro
        // ---------------------------------------------------------------------
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

        // ---------------------------------------------------------------------
        // 5. Paginación y Carga Eager de Relaciones
        // ---------------------------------------------------------------------
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

        // ---------------------------------------------------------------------
        // 6. Transformación y Renderizado de Acciones
        // ---------------------------------------------------------------------
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

    /**
     * Actualiza el diagnóstico técnico, costo estimado y notas de reparación del equipo.
     *
     * @param  UpdateOrdenTecnicaRequest  $request  Petición validada con datos técnicos.
     * @param  OrdenServicio  $orden  Orden de servicio a modificar.
     * @param  ActualizarTrabajoTecnico  $actualizarTrabajoTecnico  Acción de dominio que aplica los cambios y audita.
     * @return RedirectResponse Redirección a la vista de la orden con mensaje de éxito.
     */
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

    /**
     * Envía la cotización y diagnóstico formulados a la revisión y aprobación del supervisor.
     *
     * @param  Request  $request  Petición HTTP entrante con el usuario autenticado.
     * @param  OrdenServicio  $orden  Orden cuya cotización se enviará a revisión.
     * @param  SolicitarRevisionCotizacion  $solicitarRevisionCotizacion  Acción de dominio que procesa la solicitud.
     * @return RedirectResponse Redirección a la orden con confirmación o mensaje de error de validación.
     */
    public function requestQuoteReview(
        Request $request,
        OrdenServicio $orden,
        SolicitarRevisionCotizacion $solicitarRevisionCotizacion
    ): RedirectResponse {
        // Valida que el técnico tenga permisos para solicitar revisión en el estado actual de la orden
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

    /**
     * Pone en marcha formal los trabajos de reparación cuando la orden ya cuenta con autorización del cliente.
     *
     * @param  Request  $request  Petición HTTP entrante.
     * @param  OrdenServicio  $orden  Orden autorizada a iniciar.
     * @param  IniciarReparacionAutorizada  $iniciarReparacion  Acción de dominio que transiciona a EN_REPARACION.
     * @return RedirectResponse Redirección a la orden con confirmación.
     */
    public function startRepair(
        Request $request,
        OrdenServicio $orden,
        IniciarReparacionAutorizada $iniciarReparacion
    ): RedirectResponse {
        // Valida mediante Policy que la orden esté debidamente autorizada y lista para reparación
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

    /**
     * Transiciona la orden de servicio a etapa de pruebas de control de calidad o estabilidad.
     *
     * @param  EnviarReparacionAPruebasRequest  $request  Petición validada con comentarios técnicos opcionales.
     * @param  OrdenServicio  $orden  Orden en reparación.
     * @param  EnviarReparacionAPruebas  $enviarReparacionAPruebas  Acción de dominio que ejecuta la transición a EN_PRUEBAS.
     * @return RedirectResponse Redirección a la orden con mensaje de confirmación.
     */
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

    /**
     * Finaliza los trabajos técnicos, establece el costo final definitivo y marca el equipo como listo para entrega.
     *
     * @param  MarcarReparacionListaParaEntregaRequest  $request  Petición con el costo final y comentarios de cierre.
     * @param  OrdenServicio  $orden  Orden en pruebas o reparación.
     * @param  MarcarReparacionListaParaEntrega  $marcarListaParaEntrega  Acción de dominio que transiciona a LISTO_PARA_ENTREGA.
     * @return RedirectResponse Redirección al panel del empleado con mensaje flash.
     */
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

    /**
     * Muestra la vista detallada de la orden de servicio asignada con todo su expediente técnico.
     *
     * @param  OrdenServicio  $orden  Orden de servicio asignada al técnico.
     * @return View Vista con diagnóstico, cotización, historial de bitácora y datos del cliente.
     */
    public function show(
        OrdenServicio $orden
    ): View {
        // Valida que el empleado tenga acceso a esta orden específica
        Gate::authorize(
            'viewAssigned',
            $orden
        );

        // Carga con anticipación el cliente, equipo, servicio e historial
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
