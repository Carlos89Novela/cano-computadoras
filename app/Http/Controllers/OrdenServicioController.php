<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Enums\EstadoRevisionCotizacion;
use App\Http\Requests\AutorizarOrdenServicioRequest;
use App\Http\Requests\StoreOrdenServicioRequest;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use App\Models\User;
use App\Notifications\PresupuestoAutorizadoPorCliente;
use App\Notifications\PresupuestoRechazadoPorCliente;
use App\Services\GeneradorFolioOrden;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador de Órdenes de Servicio para Clientes.
 *
 * Gestiona el ciclo de vida de las reparaciones desde la perspectiva del cliente:
 * - Creación y registro de nuevas solicitudes de reparación con asignación de folio único.
 * - Seguimiento detallado del avance y bitácora técnica de la orden.
 * - Autorización o rechazo del presupuesto/cotización técnica previamente aprobada por el taller.
 * - Notificación automática al personal técnico y supervisores ante decisiones presupuestales.
 * - Emisión y descarga del comprobante PDF oficial de la orden de servicio.
 */
class OrdenServicioController extends Controller
{
    use AuthorizesRequests;

    /**
     * Muestra el catálogo de órdenes de servicio solicitadas por el cliente autenticado.
     *
     * @param  Request  $request  Petición HTTP entrante.
     * @return View Vista con la colección de órdenes del cliente.
     */
    public function index(Request $request): View
    {
        $ordenes = OrdenServicio::query()
            ->with('equipo')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('ordenes.index', compact('ordenes'));
    }

    /**
     * Presenta el formulario para solicitar una nueva reparación.
     *
     * Si el cliente aún no ha registrado ningún equipo en su cuenta, lo redirige
     * con una alerta al módulo de alta de equipos.
     *
     * @param  Request  $request  Petición HTTP entrante.
     * @return View|RedirectResponse Formulario de alta o redirección a registro de equipo.
     */
    public function create(Request $request): View|RedirectResponse
    {
        // Obtiene los equipos pertenecientes al cliente ordenados por marca
        $equipos = Equipo::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('marca')
            ->get();

        // Regla previa: El cliente debe tener al menos un equipo registrado
        if ($equipos->isEmpty()) {
            return redirect()
                ->route('equipos.create')
                ->with(
                    'error',
                    'Primero debes registrar un equipo.'
                );
        }

        // Catálogo de servicios disponibles activos
        $servicios = Servicio::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('ordenes.create', compact('equipos', 'servicios'));
    }

    /**
     * Registra una nueva orden de servicio generando un folio garantizado anti-colisiones.
     *
     * @param  StoreOrdenServicioRequest  $request  Petición validada con el equipo, problema y servicio.
     * @param  GeneradorFolioOrden  $generadorFolio  Servicio inyectado para foliado secuencial.
     * @return RedirectResponse Redirección a la vista de seguimiento de la orden.
     */
    public function store(
        StoreOrdenServicioRequest $request,
        GeneradorFolioOrden $generadorFolio
    ): RedirectResponse {
        $datos = $request->validated();
        $usuarioId = $request->user()->id;

        // Creación atómica de la orden y su primer asiento en el historial
        $orden = DB::transaction(function () use (
            $datos,
            $generadorFolio,
            $usuarioId
        ): OrdenServicio {
            $orden = OrdenServicio::query()->create([
                'folio' => $generadorFolio->generar(),
                'user_id' => $usuarioId,
                'equipo_id' => $datos['equipo_id'],
                'problema_reportado' => $datos['problema_reportado'],
                'estado' => EstadoOrden::RECIBIDO->value,
                'fecha_ingreso' => now()->toDateString(),
                'servicio_id' => $datos['servicio_id'] ?? null,
            ]);

            // Asienta la recepción en el historial cronológico
            $orden->historial()->create([
                'user_id' => $usuarioId,
                'estado' => EstadoOrden::RECIBIDO->value,
                'comentarios' => 'Solicitud de reparación registrada.',
            ]);

            return $orden;
        });

        return redirect()
            ->route('ordenes.show', [
                'orden' => $orden->id,
            ])
            ->with(
                'success',
                'Solicitud de reparación registrada correctamente.'
            );
    }

    /**
     * Muestra la cronología y estado actual de una orden de servicio propia del cliente.
     *
     * @param  OrdenServicio  $orden  Orden de servicio inyectada por Route Model Binding.
     * @return View Vista de seguimiento de la orden.
     *
     * @throws AuthorizationException Si el usuario autenticado no es el dueño de la orden.
     */
    public function show(
        OrdenServicio $orden
    ): View {
        $this->authorize('view', $orden);

        // Carga ansiosa del equipo, servicio y de la bitácora ordenada cronológicamente
        $orden->load([
            'equipo',
            'servicio',
            'historial' => function ($query) {
                $query->with('usuario')
                    ->orderBy('created_at', 'asc');
            },
        ]);

        return view('ordenes.show', compact('orden'));
    }

    /**
     * Procesa la decisión del cliente sobre el presupuesto técnico (autorizar o rechazar).
     *
     * Transición y reglas:
     * - La orden debe tener cotización previamente 'APROBADA' y estado 'ESPERANDO_AUTORIZACION'.
     * - Si el cliente autoriza: transiciona a 'ESPERANDO_REFACCION'.
     * - Si el cliente rechaza: transiciona a 'CANCELADO'.
     * - Despacha notificaciones al técnico asignado y a todos los supervisores.
     *
     * @param  AutorizarOrdenServicioRequest  $request  Petición validada con el campo 'decision'.
     * @param  OrdenServicio  $orden  Orden sujeta a decisión presupuestal.
     * @return RedirectResponse Redirección a la orden con confirmación.
     */
    public function autorizar(
        AutorizarOrdenServicioRequest $request,
        OrdenServicio $orden
    ): RedirectResponse {
        $datos = $request->validated();
        $usuarioId = $request->user()->id;

        // Ejecución transaccional protegida con bloqueo pesimista
        $ordenActualizada = DB::transaction(
            function () use (
                $datos,
                $orden,
                $usuarioId
            ): OrdenServicio {
                $ordenBloqueada = OrdenServicio::query()
                    ->lockForUpdate()
                    ->findOrFail($orden->id);

                // Comprueba que la cotización haya sido visada por la supervisión
                abort_unless(
                    $ordenBloqueada->estado_revision_cotizacion
                        === EstadoRevisionCotizacion::APROBADA,
                    422,
                    'La cotización no cuenta con aprobación interna.'
                );

                // Comprueba que la orden esté en espera de decisión del cliente
                abort_unless(
                    $ordenBloqueada->estado
                        === EstadoOrden::ESPERANDO_AUTORIZACION->value,
                    422,
                    'La reparación no está esperando autorización.'
                );

                // Evita doble autorización o cambio de decisión posterior
                abort_unless(
                    $ordenBloqueada->autorizacion
                        === EstadoAutorizacion::PENDIENTE->value,
                    422,
                    'El presupuesto ya fue autorizado o rechazado.'
                );

                $autorizada = $datos['decision']
                    === EstadoAutorizacion::AUTORIZADA->value;

                // Actualiza decisión y estado operativo correspondiente
                $ordenBloqueada->update([
                    'autorizacion' => $datos['decision'],
                    'fecha_autorizacion' => now(),
                    'estado' => $autorizada
                        ? EstadoOrden::ESPERANDO_REFACCION->value
                        : EstadoOrden::CANCELADO->value,
                ]);

                // Asienta el evento en el historial
                $ordenBloqueada->historial()->create([
                    'user_id' => $usuarioId,
                    'estado' => $ordenBloqueada->estado,
                    'comentarios' => $autorizada
                        ? 'El cliente autorizó el presupuesto.'
                        : 'El cliente rechazó el presupuesto.',
                    'mensaje_cliente' => null,
                ]);

                return $ordenBloqueada;
            }
        );

        $autorizada = $ordenActualizada->autorizacion
            === EstadoAutorizacion::AUTORIZADA->value;

        // Preparación de la notificación según la decisión
        $notificacion = $autorizada
            ? new PresupuestoAutorizadoPorCliente(
                $ordenActualizada
            )
            : new PresupuestoRechazadoPorCliente(
                $ordenActualizada
            );

        $destinatarios = collect();

        // Agrega al técnico asignado si existe asignación activa
        $asignacionActiva = $ordenActualizada
            ->asignacionActiva()
            ->with('empleado')
            ->first();

        if ($asignacionActiva !== null) {
            $destinatarios->push(
                $asignacionActiva->empleado
            );
        }

        // Agrega a todos los supervisores del sistema
        $supervisores = User::role('supervisor')
            ->get();

        $destinatarios = $destinatarios
            ->concat($supervisores)
            ->unique(
                fn (User $usuario): int => $usuario->id
            )
            ->values();

        Notification::send(
            $destinatarios,
            $notificacion
        );

        return redirect()
            ->route('ordenes.show', [
                'orden' => $ordenActualizada->id,
            ])
            ->with(
                'success',
                $autorizada
                    ? 'Presupuesto autorizado correctamente.'
                    : 'Presupuesto rechazado.'
            );
    }

    /**
     * Genera y descarga el comprobante en PDF oficial de la orden de servicio.
     *
     * @param  OrdenServicio  $orden  Orden de servicio a imprimir.
     * @return Response Descarga del documento PDF formateado en hoja A4 portrait.
     *
     * @throws AuthorizationException Si el usuario no tiene permisos de descarga sobre la orden.
     */
    public function pdf(
        OrdenServicio $orden
    ): Response {
        $this->authorize('downloadPdf', $orden);

        // Carga de modelos asociados para la plantilla del reporte
        $orden->load([
            'user',
            'equipo',
            'servicio',
            'historial' => function ($query) {
                $query->with('usuario')
                    ->orderBy('created_at', 'asc');
            },
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
