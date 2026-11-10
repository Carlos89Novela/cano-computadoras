<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\RestauranteMesa;
use App\Models\RestauranteReservacion;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View as ViewFacade;

class ReservacionController extends Controller
{
    /**
     * Muestra la vista de agenda y recepción de reservaciones.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Empresa $empresa */
        $empresa = $user->empresa ?? Empresa::query()->firstOrFail();

        /** @var Sucursal|null $sucursal */
        $sucursal = $user->sucursal
            ?? $user->sucursales()->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->first();

        abort_unless($sucursal !== null, 404, 'No hay sucursales activas.');

        $fechaFiltroStr = $request->string('fecha')->toString();
        $fechaFiltro = $fechaFiltroStr !== '' ? Carbon::parse($fechaFiltroStr)->startOfDay() : now()->startOfDay();

        $reservaciones = RestauranteReservacion::query()
            ->where('empresa_id', $empresa->id)
            ->where('sucursal_id', $sucursal->id)
            ->whereDate('fecha_hora_reservacion', $fechaFiltro)
            ->with(['mesa.area'])
            ->orderBy('fecha_hora_reservacion')
            ->get();

        $mesas = RestauranteMesa::query()
            ->whereHas('area', fn ($q) => $q->where('empresa_id', $empresa->id)->where('sucursal_id', $sucursal->id))
            ->where('activo', true)
            ->with('area')
            ->orderBy('numero_o_nombre')
            ->get();

        return ViewFacade::make('restaurante.reservaciones.index', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'fechaFiltro' => $fechaFiltro,
            'reservaciones' => $reservaciones,
            'mesas' => $mesas,
        ]);
    }

    /**
     * Registra una nueva reservación.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Empresa $empresa */
        $empresa = $user->empresa ?? Empresa::query()->firstOrFail();

        $validated = $request->validate([
            'sucursal_id' => ['required', 'integer'],
            'restaurante_mesa_id' => ['nullable', 'integer', 'exists:restaurante_mesas,id'],
            'cliente_nombre' => ['required', 'string', 'max:150'],
            'cliente_telefono' => ['nullable', 'string', 'max:25'],
            'cliente_email' => ['nullable', 'email', 'max:100'],
            'numero_personas' => ['required', 'integer', 'min:1', 'max:50'],
            'fecha_hora_reservacion' => ['required', 'date', 'after:now - 2 hours'],
            'duracion_estimada_minutos' => ['nullable', 'integer', 'min:30', 'max:360'],
            'notas_especiales' => ['nullable', 'string', 'max:500'],
        ]);

        $folio = 'RES-'.now()->format('Ymd').'-'.str_pad((string) (RestauranteReservacion::query()->count() + 1), 3, '0', STR_PAD_LEFT);

        $reservacion = RestauranteReservacion::query()->create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => (int) $validated['sucursal_id'],
            'restaurante_mesa_id' => $validated['restaurante_mesa_id'] ?? null,
            'folio' => $folio,
            'cliente_nombre' => $validated['cliente_nombre'],
            'cliente_telefono' => $validated['cliente_telefono'] ?? null,
            'cliente_email' => $validated['cliente_email'] ?? null,
            'numero_personas' => (int) $validated['numero_personas'],
            'fecha_hora_reservacion' => Carbon::parse($validated['fecha_hora_reservacion']),
            'duracion_estimada_minutos' => (int) ($validated['duracion_estimada_minutos'] ?? 90),
            'estado' => 'confirmada',
            'notas_especiales' => $validated['notas_especiales'] ?? null,
            'atendido_por_user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Reservación agendada con éxito.',
            'folio' => $reservacion->folio,
            'reservacion' => $reservacion->load('mesa'),
        ]);
    }

    /**
     * Marca la llegada del cliente a su mesa ("Sentar comensales").
     */
    public function sentar(Request $request, RestauranteReservacion $reservacion): JsonResponse
    {
        $mesaId = $request->integer('restaurante_mesa_id') ?: $reservacion->restaurante_mesa_id;

        if (! $mesaId) {
            return response()->json(['message' => 'Debe asignar una mesa para sentar al cliente.'], 422);
        }

        /** @var RestauranteMesa $mesa */
        $mesa = RestauranteMesa::query()->findOrFail($mesaId);

        if ($mesa->estado === 'ocupada') {
            return response()->json(['message' => "La {$mesa->numero_o_nombre} se encuentra ocupada."], 422);
        }

        DB::transaction(function () use ($reservacion, $mesa) {
            $reservacion->update([
                'restaurante_mesa_id' => $mesa->id,
                'estado' => 'sentada',
            ]);

            $mesa->update([
                'estado' => 'ocupada',
            ]);
        });

        return response()->json([
            'message' => "Comensales de {$reservacion->cliente_nombre} sentados en {$mesa->numero_o_nombre}.",
            'mesa_nombre' => $mesa->numero_o_nombre,
        ]);
    }

    /**
     * Actualiza el estado (cancelada, no_show, completada).
     */
    public function updateEstado(Request $request, RestauranteReservacion $reservacion): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:confirmada,sentada,completada,cancelada,no_show'],
        ]);

        $reservacion->update(['estado' => $validated['estado']]);

        return response()->json(['message' => 'Estado de reservación actualizado.']);
    }
}
