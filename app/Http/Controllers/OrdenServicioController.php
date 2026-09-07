<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAutorizacion;
use App\Enums\EstadoOrden;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Requests\AutorizarOrdenServicioRequest;
use App\Http\Requests\StoreOrdenServicioRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrdenServicioController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        // Obtener las órdenes de servicio del usuario autenticado
        $ordenes = OrdenServicio::query()
            ->with('equipo')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('ordenes.index', compact('ordenes'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        // Obtener los equipos del usuario autenticado
        $equipos = Equipo::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('marca')
            ->get();
        // Verificar si el usuario tiene equipos registrados
        if ($equipos->isEmpty()) {
            return redirect()
                ->route('equipos.create')
                ->with(
                    'error',
                    'Primero debes registrar un equipo.'
                );

        }

        // Obtener los servicios disponibles
        $servicios = Servicio::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('ordenes.create', compact('equipos', 'servicios'));
    }

    public function store(
        StoreOrdenServicioRequest $request
    ): RedirectResponse {
        $datos = $request->validated();
        $usuarioId = $request->user()->id;

        $orden = DB::transaction(function () use (
            $datos,
            $usuarioId
        ): OrdenServicio {
            $orden = OrdenServicio::query()->create([
                'folio' => $this->generarFolio(),
                'user_id' => $usuarioId,
                'equipo_id' => $datos['equipo_id'],
                'problema_reportado' => $datos['problema_reportado'],
                'estado' => EstadoOrden::RECIBIDO->value,
                'fecha_ingreso' => now()->toDateString(),
                'servicio_id' => $datos['servicio_id'] ?? null,
            ]);

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

    public function show(
        OrdenServicio $orden
    ): View {
        $this->authorize('view', $orden);
        // Cargar las relaciones necesarias para la vista
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

    private function generarFolio(): string
    {
        do {
            $folio = 'REP-'
                .now()->format('Ymd')
                .'-'
                .strtoupper(substr(uniqid(), -5));
        } while (
            OrdenServicio::where('folio', $folio)->exists()
        );

        return $folio;
    }

    public function autorizar(
        AutorizarOrdenServicioRequest $request,
        OrdenServicio $orden
    ): RedirectResponse {
        $datos = $request->validated();

        abort_unless(
            $orden->estado === EstadoOrden::ESPERANDO_AUTORIZACION->value,
            422,
            'La reparación no está esperando autorización.'
        );

        abort_unless(
            $orden->autorizacion === EstadoAutorizacion::PENDIENTE->value,
            422,
            'El presupuesto ya fue autorizado o rechazado.'
        );

        $autorizada = $datos['decision']
            === EstadoAutorizacion::AUTORIZADA->value;

        DB::transaction(function () use (
            $autorizada,
            $datos,
            $orden,
            $request
        ): void {
            $orden->update([
                'autorizacion' => $datos['decision'],
                'fecha_autorizacion' => now(),
                'estado' => $autorizada
                    ? EstadoOrden::ESPERANDO_REFACCION->value
                    : EstadoOrden::CANCELADO->value,
            ]);

            $orden->historial()->create([
                'user_id' => $request->user()->id,
                'estado' => $orden->estado,
                'comentarios' => $autorizada
                    ? 'El cliente autorizó el presupuesto.'
                    : 'El cliente rechazó el presupuesto.',
            ]);
        });

        return redirect()
            ->route('ordenes.show', [
                'orden' => $orden->id,
            ])
            ->with(
                'success',
                $autorizada
                    ? 'Presupuesto autorizado correctamente.'
                    : 'Presupuesto rechazado.'
            );
    }

    public function pdf(
        OrdenServicio $orden
    ): Response {
        $this->authorize('downloadPdf', $orden);

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
