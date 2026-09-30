<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoOrden;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUpdateOrdenServicioRequest;
use App\Http\Requests\Admin\UpdateOrdenServicioRequest;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Notifications\EstadoReparacionActualizado;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Controlador de Gestión Administrativa de Órdenes de Servicio.
 *
 * Módulo neurálgico para el control operativo y directivo del taller:
 * - Tablero interactivo con DataTables del lado del servidor (búsqueda multicampo, ordenamiento compuesto y paginación).
 * - Actualización masiva de órdenes con validación estricta de máquinas de estados (`permiteTransicionA`).
 * - Edición individual de estados, diagnósticos, costos preliminares/definitivos y notas.
 * - Despacho automático de notificaciones telemáticas a clientes ante actualizaciones de avance.
 * - Generación de reportes ejecutivos en formatos CSV (con BOM UTF-8) y PDF apaisado.
 */
class OrdenServicioController extends Controller
{
    /**
     * Muestra la bandeja administrativa de órdenes con catálogos de filtros rápidos.
     *
     * @return View Vista 'admin.ordenes.index' con órdenes recientes y estados para filtrado.
     */
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

    /**
     * Endpoint JSON para DataTables con procesamiento 100% del lado del servidor.
     *
     * Permite búsquedas globales concurrentes (folio, estado, nombre del cliente, marca, modelo o serie del equipo),
     * filtros exactos por estado, ordenamiento relacional y renderizado de componentes parciales HTML.
     *
     * @param  Request  $request  Petición DataTables con parámetros draw, start, length, order y search.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON compatible con el protocolo DataTables.
     */
    public function data(Request $request)
    {
        // Mapeo seguro de columnas ordenables en base de datos para prevenir inyecciones SQL
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

        // Filtro exacto por estado operativo
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

        // Búsqueda multicriterio (Full-text LIKE en orden, cliente y equipo)
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

        // Resolución del ordenamiento dinámico
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
            // Ordenamiento por subconsulta sobre el nombre del usuario
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
            // Ordenamiento por subconsultas sobre la marca y modelo del equipo
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

        /** @var EloquentCollection<int, OrdenServicio> $rows */
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

                'cliente' => e($orden->user->name),

                'equipo' => e($equipo),

                'estado' => view(
                    'admin.ordenes.partials.estado-badge',
                    ['estado' => $orden->estado]
                )->render(),

                'fecha_ingreso' => $orden->fecha_ingreso->format(
                    'd/m/Y'
                ),

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

    /**
     * Procesa la actualización masiva de estados sobre múltiples órdenes seleccionadas.
     *
     * Valida de manera individual que cada orden cumpla con la máquina de transiciones permitidas
     * (`permiteTransicionA`). Omite órdenes incompatibles y registra en bitácora cada actualización efectiva,
     * alertando opcionalmente al cliente con mensajes personalizados.
     *
     * @param  BulkUpdateOrdenServicioRequest  $request  Petición validada con arreglo de IDs y estado destino.
     * @return \Illuminate\Http\JsonResponse Resumen de conteos: exitosas, omitidas y fallidas.
     */
    public function bulkUpdate(BulkUpdateOrdenServicioRequest $request)
    {
        $datos = $request->validated();

        $nuevoEstado = EstadoOrden::from($datos['estado']);

        $ordenes = OrdenServicio::query()
            ->with('user')
            ->whereIn('id', $datos['ids'])
            ->get();

        $ordenesActualizadas = 0;
        $ordenesOmitidas = 0;
        $ordenesFallidas = 0;

        foreach ($ordenes as $orden) {
            $estadoActual = EstadoOrden::tryFrom($orden->estado);

            if ($estadoActual === null) {
                $ordenesOmitidas++;

                continue;
            }

            $estadoCambio = $estadoActual !== $nuevoEstado;
            $tieneComentario = filled($datos['comentario'] ?? null);
            $tieneMensajeCliente = filled($datos['mensaje_cliente'] ?? null);

            // Regla de negocio: La máquina de estados debe permitir la transición hacia el nuevo estado
            if (
                $estadoCambio
                && ! $estadoActual->permiteTransicionA($nuevoEstado)
            ) {
                $ordenesOmitidas++;

                continue;
            }

            // Si no hay cambio de estado ni notas, no se procesa
            if (! $estadoCambio && ! $tieneComentario && ! $tieneMensajeCliente) {
                $ordenesOmitidas++;

                continue;
            }

            try {
                DB::transaction(function () use (
                    $datos,
                    $estadoCambio,
                    $nuevoEstado,
                    $orden,
                    $request
                ): void {
                    // Actualiza estado y ajusta fecha de entrega si corresponde
                    if ($estadoCambio) {
                        $orden->update([
                            'estado' => $nuevoEstado->value,
                            'fecha_entrega' => $nuevoEstado === EstadoOrden::ENTREGADO
                                ? ($orden->fecha_entrega ?? now()->toDateString())
                                : null,
                        ]);
                    }

                    // Asienta en la bitácora interna de la orden
                    $orden->historial()->create([
                        'user_id' => $request->user()->id,
                        'estado' => $orden->estado,
                        'comentarios' => $datos['comentario']
                            ?? ($estadoCambio ? 'Cambio masivo de estado realizado por el Administrador.' : null),
                        'mensaje_cliente' => $datos['mensaje_cliente'] ?? null,
                    ]);
                });
            } catch (Throwable $exception) {
                $ordenesFallidas++;

                Log::error(
                    'No fue posible actualizar una orden mediante la acción masiva.',
                    [
                        'orden_id' => $orden->id,
                        'estado_solicitado' => $nuevoEstado->value,
                        'exception' => $exception,
                    ]
                );

                continue;
            }

            $orden->refresh();

            // Notificación al cliente si cambió el estado o se agregó un mensaje explícito
            $debeNotificar = $estadoCambio
                || filled($datos['mensaje_cliente'] ?? null);

            if ($debeNotificar && $orden->user !== null) {
                try {
                    $orden->user->notify(
                        new EstadoReparacionActualizado(
                            $orden,
                            $datos['mensaje_cliente'] ?? null
                        )
                    );
                } catch (Throwable $exception) {
                    Log::warning(
                        'La orden fue actualizada, pero no se pudo enviar la notificación.',
                        [
                            'orden_id' => $orden->id,
                            'user_id' => $orden->user_id,
                            'exception' => $exception,
                        ]
                    );
                }
            }

            $ordenesActualizadas++;
        }

        return response()->json([
            'success' => true,
            'updated' => $ordenesActualizadas,
            'skipped' => $ordenesOmitidas,
            'failed' => $ordenesFallidas,
        ]);
    }

    /**
     * Muestra la pantalla de edición administrativa de una orden de servicio.
     *
     * Calcula dinámicamente los estados a los cuales es legal transicionar según el estado actual.
     *
     * @param  OrdenServicio  $orden  Orden inyectada por Route Model Binding.
     * @return View Formulario de edición con las opciones válidas del ciclo de vida.
     */
    public function edit(OrdenServicio $orden): View
    {
        $orden->load([
            'user',
            'equipo',
            'historial.usuario',
        ]);

        $estadoActual = EstadoOrden::tryFrom($orden->estado);

        // Conjunto de estados permitidos según la máquina de estados
        $estados = $estadoActual
            ? array_values(array_unique([
                $estadoActual->value,
                ...$estadoActual->valoresPermitidos(),
            ]))
            : [$orden->estado];

        return view(
            'admin.ordenes.edit',
            compact('orden', 'estados')
        );
    }

    /**
     * Procesa la actualización individual de la orden por parte del administrador.
     *
     * @param  UpdateOrdenServicioRequest  $request  Petición validada con datos técnicos y monetarios.
     * @param  OrdenServicio  $orden  Orden a actualizar.
     * @return RedirectResponse Redirección a la edición con mensaje flash de éxito.
     */
    public function update(
        UpdateOrdenServicioRequest $request,
        OrdenServicio $orden
    ): RedirectResponse {
        $datos = $request->validated();
        $usuarioId = $request->user()->id;
        $estadoAnterior = $orden->estado;

        DB::transaction(function () use (
            $datos,
            $usuarioId,
            $orden,
            $estadoAnterior
        ): void {
            // Actualización de campos operativos y costos
            $orden->update([
                'estado' => $datos['estado'],
                'diagnostico' => $datos['diagnostico'] ?? null,
                'costo_estimado' => $datos['costo_estimado'] ?? null,
                'costo_final' => $datos['costo_final'] ?? null,
                'fecha_entrega' => $datos['estado'] === EstadoOrden::ENTREGADO->value
                    ? ($orden->fecha_entrega ?? now()->toDateString())
                    : null,
            ]);

            $estadoCambio = $estadoAnterior !== $datos['estado'];
            $tieneComentario = filled($datos['comentario'] ?? null);
            $tieneMensajeCliente = filled($datos['mensaje_cliente'] ?? null);

            // Registro en historial técnico
            if ($estadoCambio || $tieneComentario || $tieneMensajeCliente) {
                $orden->historial()->create([
                    'user_id' => $usuarioId,
                    'estado' => $datos['estado'],
                    'comentarios' => $datos['comentario']
                        ?? ($estadoCambio ? 'Estado Actualizado por el Administrador.' : null),
                    'mensaje_cliente' => $datos['mensaje_cliente'] ?? null,
                ]);
            }
        });

        $orden->refresh();

        // Notificación al cliente si varió el estatus o se incluyó mensaje público
        $debeNotificar = $estadoAnterior !== $orden->estado
            || filled($datos['mensaje_cliente'] ?? null);

        if ($debeNotificar && $orden->user !== null) {
            $orden->user->notify(
                new EstadoReparacionActualizado(
                    $orden,
                    $datos['mensaje_cliente'] ?? null
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

    /**
     * Exporta el listado de órdenes en formato CSV estructurado con codificación UTF-8 BOM.
     *
     * Permite abrir directamente en Microsoft Excel sin problemas de tildes o caracteres especiales en español.
     *
     * @param  Request  $request  Petición HTTP con filtros opcionales de búsqueda y estado.
     * @return Response Descarga del archivo plano .csv.
     */
    public function exportCsv(Request $request): Response
    {
        $estado = $this->obtenerEstadoExportacion($request);

        // Recupera la colección filtrada
        $ordenes = $this
            ->consultaExportacion($request, $estado)
            ->get();

        $encabezados = [
            'Folio',
            'Cliente',
            'Equipo',
            'Estado',
            'Fecha de ingreso',
            'Costo final',
        ];

        $filas = [$encabezados];

        foreach ($ordenes as $orden) {
            $equipo = trim(implode(' ', array_filter([
                $orden->equipo?->marca,
                $orden->equipo?->modelo,
            ])));

            $filas[] = [
                $orden->folio,
                $orden->user->name,
                $equipo !== '' ? $equipo : '-',
                $orden->estado ?? '-',
                $orden->fecha_ingreso->format('d/m/Y'),
                '$'.number_format(
                    (float) ($orden->costo_final ?? 0),
                    2,
                    '.',
                    ','
                ),
            ];
        }

        // Creación del flujo de memoria temporal para armar el CSV
        $archivo = fopen('php://temp', 'r+');

        if ($archivo === false) {
            abort(
                500,
                'No fue posible generar el archivo CSV.'
            );
        }

        // Inserción obligatoria del Byte Order Mark (BOM) UTF-8 para compatibilidad nativa con Excel
        fwrite($archivo, "\xEF\xBB\xBF");

        foreach ($filas as $fila) {
            fputcsv(
                stream: $archivo,
                fields: $fila,
                separator: ';',
                enclosure: '"',
                escape: '',
                eol: "\r\n"
            );
        }

        rewind($archivo);

        $contenido = stream_get_contents($archivo);

        fclose($archivo);

        if ($contenido === false) {
            abort(
                500,
                'No fue posible obtener el contenido del archivo CSV.'
            );
        }

        $nombreArchivo = 'ordenes-cano-computadoras'
            .($estado !== 'all' ? '-'.Str::slug($estado) : '')
            .'-'.now()->format('Y-m-d')
            .'.csv';

        return response($contenido, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombreArchivo.'"',
        ]);
    }

    /**
     * Genera un reporte gerencial en PDF en orientación apaisada (Landscape) con resúmenes ejecutivos.
     *
     * Agrupa y calcula totales de ingresos monetarios y conteo de equipos por cada estado de reparación.
     *
     * @param  Request  $request  Petición HTTP con filtros activos.
     * @return Response Descarga del documento PDF compilado con DomPDF.
     */
    public function exportPdf(Request $request): Response
    {
        $estado = $this->obtenerEstadoExportacion($request);

        $ordenes = $this
            ->consultaExportacion($request, $estado)
            ->get();

        // Agrupación estadística por estado para la tabla resumen
        $resumenPorEstado = $ordenes
            ->groupBy('estado')
            ->map(function ($items): array {
                return [
                    'cantidad' => $items->count(),
                    'total' => $items->sum(
                        fn (OrdenServicio $orden): float => (float) (
                            $orden->costo_final ?? 0
                        )
                    ),
                ];
            })
            ->sortKeys();

        $totalOrdenes = $ordenes->count();

        $totalIngresos = $ordenes->sum(
            fn (OrdenServicio $orden): float => (float) (
                $orden->costo_final ?? 0
            )
        );

        $pdf = Pdf::loadView('pdf.ordenes-reporte', [
            'ordenes' => $ordenes,
            'estado' => $estado,
            'fechaGeneracion' => now()->format('d/m/Y H:i'),
            'resumenPorEstado' => $resumenPorEstado,
            'totalOrdenes' => $totalOrdenes,
            'totalIngresos' => $totalIngresos,
        ])->setPaper('a4', 'landscape');

        $nombreArchivo = 'reporte-ordenes'
            .($estado !== 'all' ? '-'.Str::slug($estado) : '')
            .'-'.now()->format('Y-m-d')
            .'.pdf';

        return $pdf->download($nombreArchivo);
    }

    /**
     * Imprime y descarga la boleta individual de la orden de servicio en formato A4 vertical.
     *
     * @param  OrdenServicio  $orden  Orden a imprimir.
     * @return Response Descarga del PDF.
     */
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

    /**
     * Resuelve y valida el parámetro de estado para exportaciones masivas.
     *
     * @param  Request  $request  Petición HTTP entrante.
     * @return string Estado válido o 'all' por defecto.
     */
    private function obtenerEstadoExportacion(Request $request): string
    {
        $estado = trim(
            (string) $request->query('estado', 'all')
        );

        if (
            $estado === ''
            || $estado === 'all'
            || ! in_array($estado, EstadoOrden::valores(), true)
        ) {
            return 'all';
        }

        return $estado;
    }

    /**
     * Construye la consulta Eloquent común para los motores de exportación aplicando filtros y ordenamiento.
     *
     * @param  Request  $request  Petición con texto de búsqueda opcional.
     * @param  string  $estado  Estado específico o 'all'.
     * @return Builder<OrdenServicio> Consulta preparada para ejecución.
     */
    private function consultaExportacion(
        Request $request,
        string $estado
    ): Builder {
        $query = OrdenServicio::query()
            ->with([
                'user:id,name',
                'equipo:id,marca,modelo,numero_serie',
            ])
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id');

        if ($estado !== 'all') {
            $query->where(
                'orden_servicios.estado',
                $estado
            );
        }

        $search = trim(
            (string) $request->query('search', '')
        );

        if ($search === '') {
            return $query;
        }

        // Búsqueda en folio, estado, cliente o equipo
        return $query->where(
            function (Builder $consulta) use ($search) {
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
                    ->orWhereHas(
                        'user',
                        function (Builder $usuario) use ($search) {
                            $usuario->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    )
                    ->orWhereHas(
                        'equipo',
                        function (Builder $equipo) use ($search) {
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
                        }
                    );
            }
        );
    }
}
