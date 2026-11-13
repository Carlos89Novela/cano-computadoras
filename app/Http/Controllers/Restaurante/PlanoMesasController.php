<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\RestauranteArea;
use App\Models\RestauranteMesa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View as ViewFacade;

class PlanoMesasController extends Controller
{
    /**
     * Muestra el mapa gráfico interactivo del salón.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Obtener explícitamente la empresa restaurantera (La Fogata Bistro)
        $empresa = Empresa::query()->where('id', 3)->first()
            ?? Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
            ?? Empresa::query()->firstOrFail();

        // 2. Obtener la sucursal de restaurante perteneciente a esa empresa
        $sucursal = $user->sucursales()
            ->where('sucursales.empresa_id', $empresa->id)
            ->where('sucursales.activo', true)
            ->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->where('codigo', 'REST-01')->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->first()
            ?? Sucursal::query()->first();

        $sucursalId = $sucursal !== null ? (int) $sucursal->id : 2;

        $hoy = now()->toDateString();

        // 3. Recuperar las áreas y mesas reales de La Fogata Bistro
        $areas = RestauranteArea::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->with([
                'mesas' => function ($q) use ($hoy) {
                    $q->where('activo', true)
                        ->with([
                            'comandaActual.mesero',
                            'reservaciones' => function ($rq) use ($hoy) {
                                $rq->whereDate('fecha_hora_reservacion', $hoy)
                                    ->where('estado', 'confirmada')
                                    ->orderBy('fecha_hora_reservacion');
                            },
                        ]);
                },
            ])
            ->orderBy('orden')
            ->get();

        $puedeEditarPlano = $user->hasRole(['administrador', 'supervisor']);

        return ViewFacade::make('restaurante.mesas.plano', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'areas' => $areas,
            'puedeEditarPlano' => $puedeEditarPlano,
        ]);
    }

    /**
     * Guarda la nueva distribución de coordenadas de las mesas (Drag & Drop).
     */
    public function guardarPosiciones(Request $request): JsonResponse
    {
        /** @var array{
         *     posiciones: array<int, array{
         *         id: int,
         *         posicion_x: numeric,
         *         posicion_y: numeric
         *     }>
         * } $validated */
        $validated = $request->validate([
            'posiciones' => ['required', 'array', 'min:1'],
            'posiciones.*.id' => ['required', 'integer', 'exists:restaurante_mesas,id'],
            'posiciones.*.posicion_x' => ['required', 'numeric', 'min:0', 'max:100'],
            'posiciones.*.posicion_y' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['posiciones'] as $pos) {
                RestauranteMesa::query()->where('id', $pos['id'])->update([
                    'posicion_x' => round((float) $pos['posicion_x'], 2),
                    'posicion_y' => round((float) $pos['posicion_y'], 2),
                ]);
            }
        });

        return response()->json(['message' => 'Distribución del salón guardada exitosamente.']);
    }

    /**
     * Crea una nueva mesa en el área activa.
     */
    public function storeMesa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'restaurante_area_id' => ['required', 'integer', 'exists:restaurante_areas,id'],
            'numero_o_nombre' => ['required', 'string', 'max:30'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:50'],
            'forma' => ['required', 'string', 'in:cuadrada,redonda,rectangular'],
        ]);

        $mesa = RestauranteMesa::query()->create([
            'restaurante_area_id' => $validated['restaurante_area_id'],
            'numero_o_nombre' => $validated['numero_o_nombre'],
            'capacidad' => $validated['capacidad'],
            'forma' => $validated['forma'],
            'posicion_x' => 45.00,
            'posicion_y' => 45.00,
            'estado' => 'disponible',
        ]);

        return response()->json(['message' => 'Mesa creada.', 'mesa' => $mesa]);
    }

    /**
     * Actualiza el nombre, capacidad o forma geométrica de una mesa existente.
     */
    public function updateMesa(Request $request, RestauranteMesa $mesa): JsonResponse
    {
        $validated = $request->validate([
            'numero_o_nombre' => ['required', 'string', 'max:30'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:50'],
            'forma' => ['required', 'string', 'in:cuadrada,redonda,rectangular'],
        ]);

        $mesa->update([
            'numero_o_nombre' => $validated['numero_o_nombre'],
            'capacidad' => $validated['capacidad'],
            'forma' => $validated['forma'],
        ]);

        return response()->json([
            'message' => 'Mesa actualizada correctamente.',
            'mesa' => $mesa->fresh(),
        ]);
    }

    /**
     * Elimina (o desactiva si tiene historial) una mesa del plano.
     */
    public function destroyMesa(RestauranteMesa $mesa): JsonResponse
    {
        if ($mesa->estado !== 'disponible' || $mesa->comanda_actual_id !== null) {
            return response()->json([
                'message' => 'No se puede eliminar una mesa que tiene una comanda activa o cuenta pendiente.',
            ], 422);
        }

        $mesa->delete();

        return response()->json([
            'message' => 'Mesa eliminada del plano.',
        ]);
    }
}
