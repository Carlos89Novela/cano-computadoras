<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrden;
use App\Models\Equipo;
use App\Models\OrdenServicio;
use App\Models\Servicio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrdenServicioController extends Controller
{
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

    public function store(Request $request): RedirectResponse
    {
        // Validar los datos de la solicitud
        $datos = $request->validate([
            'equipo_id' => [
                'required',
                'integer',
                'exists:equipos,id',
            ],
            'problema_reportado' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'servicio_id' => [
                'nullable',
                'integer',
                'exists:servicios,id',
            ],
        ]);

        // Verificar que el equipo pertenece al usuario autenticado
        $equipo = Equipo::query()
            ->where('id', $datos['equipo_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Crear la orden de servicio
        $orden = OrdenServicio::create([
            'folio' => $this->generarFolio(),
            'user_id' => $request->user()->id,
            'equipo_id' => $equipo->id,
            'problema_reportado' => $datos['problema_reportado'],
            'estado' => EstadoOrden::RECIBIDO->value,
            'fecha_ingreso' => now()->toDateString(),
            'servicio_id' => $datos['servicio_id'] ?? null,
        ]);

        // Crear el historial de la orden de servicio
        $orden->historial()->create([
            'user_id' => $request->user()->id,
            'estado' => EstadoOrden::RECIBIDO->value,
            'comentarios' => 'Solicitud de reparación registrada.',
        ]);

        // Redirigir al usuario a la vista de la orden de servicio con un mensaje de éxito
        return redirect()
            ->route('ordenes.show', ['orden' => $orden->id])
            ->with(
                'success',
                'Solicitud de reparación registrada correctamente.'
            );
    }

    public function show(
        Request $request,
        OrdenServicio $orden
    ): View {
        abort_unless(
            (int) $orden->user_id === (int) $request->user()->id,
            403,
            'No tienes permiso para consultar esta reparación.'
        );
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

    private function verificarPropietario(
        Request $request,
        OrdenServicio $orden
    ): void {
        abort_unless(
            $orden->user_id === $request->user()->id,
            403,
            'No tienes permiso para consultar esta reparación.'
        );
    }

    public function autorizar(
        Request $request,
        OrdenServicio $orden
    ): RedirectResponse {
        abort_unless(
            (int) $orden->user_id === (int) $request->user()->id,
            403,
            'No tienes permiso para autorizar esta reparación.'
        );

        $datos = $request->validate([
            'decision' => [
                'required',
                'string',
                'in:autorizada,rechazada',
            ],
        ]);

        abort_unless(
            $orden->estado === EstadoOrden::ESPERANDO_AUTORIZACION->value,
            422,
            'La reparación no está esperando autorización.'
        );

        $autorizada = $datos['decision'] === 'autorizada';

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

        return redirect()
            ->route('ordenes.show', ['orden' => $orden->id])
            ->with(
                'success',
                $autorizada
                    ? 'Presupuesto autorizado correctamente.'
                    : 'Presupuesto rechazado.'
            );
    }

    public function pdf(
        Request $request,
        OrdenServicio $orden
    ): Response {
        abort_unless(
            (int) $orden->user_id === (int) $request->user()->id,
            403,
            'No tienes permiso para descargar esta orden.'
        );

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
