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

/**
 * Controlador para la supervisión y control de cotizaciones técnicas.
 *
 * En el ciclo de servicio técnico, cuando un técnico finaliza el diagnóstico inicial
 * y propone un costo estimado, la orden ingresa al estado de revisión de cotización
 * 'PENDIENTE'. Este controlador gestiona:
 * 1. El listado server-side DataTables de cotizaciones por evaluar.
 * 2. La visualización pormenorizada del diagnóstico y los antecedentes del equipo.
 * 3. La aprobación formal de la cotización para presentarla al cliente.
 * 4. El rechazo fundado de la cotización con observaciones correctivas para el técnico.
 */
class RevisionCotizacionController extends Controller
{
    /**
     * Procesa la solicitud AJAX de DataTables con paginación, filtros y ordenamiento en servidor.
     *
     * @param  Request  $request  Petición HTTP con los parámetros estándar de DataTables (draw, start, length, search, order).
     * @return JsonResponse Respuesta JSON con el conjunto de datos formateado y conteos de registros.
     */
    public function data(Request $request): JsonResponse
    {
        // ---------------------------------------------------------------------
        // 1. Autorización y Validación del Actor
        // ---------------------------------------------------------------------
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User,
            403
        );

        // Se requiere al menos uno de los permisos operativos sobre cotizaciones
        abort_unless(
            $usuario->can('ordenes.aprobar_cotizacion')
            || $usuario->can('ordenes.rechazar_cotizacion'),
            403
        );

        // ---------------------------------------------------------------------
        // 2. Construcción de Consulta Base
        // ---------------------------------------------------------------------
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

        // Total sin filtros aplicados
        $recordsTotal = (clone $consulta)->count();

        // ---------------------------------------------------------------------
        // 3. Búsqueda Global Multicampo
        // ---------------------------------------------------------------------
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

        // Total con filtros aplicados
        $recordsFiltered = (clone $consulta)->count();

        // ---------------------------------------------------------------------
        // 4. Ordenamiento Seguro con Lista Blanca
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

        // ---------------------------------------------------------------------
        // 5. Paginación y Transformación de Registros
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

        $ordenes = $consulta
            ->skip($start)
            ->take($length)
            ->get();

        // Mapea la colección a la estructura esperada por las columnas de DataTables
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
                    'acciones' => view(
                        'supervisor.cotizaciones.partials.acciones',
                        compact('orden')
                    )->render(),
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

    /**
     * Muestra la vista detallada de una orden de servicio para la inspección de su cotización.
     *
     * @param  OrdenServicio  $orden  Orden de servicio a evaluar.
     * @return View Vista con diagnóstico técnico, presupuesto propuesto e historial.
     */
    public function show(
        OrdenServicio $orden
    ): View {
        // Valida mediante Policy que el usuario tenga facultades para revisar la cotización
        Gate::authorize(
            'viewQuoteReview',
            $orden
        );

        // Carga con anticipación las relaciones necesarias para renderizar la vista de detalle
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

    /**
     * Aprueba la cotización técnica de la orden de servicio.
     *
     * @param  Request  $request  Petición HTTP entrante con el usuario autenticado.
     * @param  OrdenServicio  $orden  Orden cuya cotización será aprobada.
     * @param  AprobarRevisionCotizacion  $aprobarRevisionCotizacion  Acción de dominio que transiciona el estado de cotización.
     * @return RedirectResponse Redirección al panel del rol correspondiente con mensaje flash.
     */
    public function approve(
        Request $request,
        OrdenServicio $orden,
        AprobarRevisionCotizacion $aprobarRevisionCotizacion
    ): RedirectResponse {
        // Valida facultades de aprobación mediante Policy
        Gate::authorize(
            'approveQuoteReview',
            $orden
        );

        $revisor = $request->user();

        abort_unless(
            $revisor instanceof User,
            403
        );

        // Ejecuta la aprobación: estado_revision_cotizacion = APROBADA, auditoría y registro en historial
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

    /**
     * Rechaza la cotización técnica devolviéndola al técnico asignado con observaciones.
     *
     * @param  RechazarRevisionCotizacionRequest  $request  Petición validada con el motivo u observación de rechazo.
     * @param  OrdenServicio  $orden  Orden de servicio cuya cotización es devuelta.
     * @param  RechazarRevisionCotizacion  $rechazarRevisionCotizacion  Acción de dominio que procesa la devolución.
     * @return RedirectResponse Redirección al panel del rol correspondiente con mensaje flash.
     */
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

        // Ejecuta el rechazo: estado_revision_cotizacion = RECHAZADA, almacena notas de revisión y audita
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
