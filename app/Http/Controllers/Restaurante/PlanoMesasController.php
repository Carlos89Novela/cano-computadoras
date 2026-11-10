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
        /** @var Empresa $empresa */
        $empresa = $user->empresa ?? Empresa::query()->firstOrFail();

        /** @var Sucursal|null $sucursal */
        $sucursal = $user->sucursal
            ?? $user->sucursales()->where('sucursales.activo', true)->first()
            ?? Sucursal::query()->where('empresa_id', $empresa->id)->first();

        $sucursalId = $sucursal !== null ? (int) $sucursal->id : 1;

        // Recupera las áreas activas con sus mesas asociadas y comanda activa
        $areas = RestauranteArea::query()
            ->where('empresa_id', $empresa->id)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->with(['mesas' => fn ($q) => $q->where('activo', true)->with('comandaActual.mesero')])
            ->orderBy('orden')
            ->get();

        // Si no existen áreas, crea un área por defecto para facilitar el inicio
        if ($areas->isEmpty()) {
            $areaPrincipal = RestauranteArea::query()->create([
                'empresa_id' => $empresa->id,
                'sucursal_id' => $sucursalId,
                'nombre' => 'Salón Principal',
                'orden' => 1,
                'activo' => true,
            ]);

            // Crear 6 mesas demo
            for ($i = 1; $i <= 6; $i++) {
                RestauranteMesa::query()->create([
                    'restaurante_area_id' => $areaPrincipal->id,
                    'numero_o_nombre' => "Mesa {$i}",
                    'capacidad' => 4,
                    'forma' => $i % 2 === 0 ? 'redonda' : 'cuadrada',
                    'posicion_x' => (float) (($i * 14) + 5),
                    'posicion_y' => (float) (20 + (($i % 3) * 25)),
                    'estado' => 'disponible',
                ]);
            }

            $areas = RestauranteArea::query()
                ->where('id', $areaPrincipal->id)
                ->with(['mesas'])
                ->get();
        }

        // Permisos para habilitar el modo edición y arrastre
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
}
