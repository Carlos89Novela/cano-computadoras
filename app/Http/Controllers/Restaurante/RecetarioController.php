<?php

namespace App\Http\Controllers\Restaurante;

// ---------------------------------------------------------
// IMPORTACIONES DE CONTROLADORES Y MODELOS
// ---------------------------------------------------------
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\ProductoReceta;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFacade;

class RecetarioController extends Controller
{
    // =========================================================
    // VISTA PRINCIPAL DEL RECETARIO Y FICHAS TÉCNICAS (BOM)
    // =========================================================

    /**
     * Muestra la interfaz principal de recetas y costeo de platillos.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Empresa|null $empresa */
        $empresa = $user->empresa;

        return ViewFacade::make('restaurante.recetario.index', [
            'empresa' => $empresa,
        ]);
    }

    // =========================================================
    // DATOS REACTIVOS PARA DATATABLES / API JSON
    // =========================================================

    /**
     * Retorna el listado estructurado de recetas y escandallos para la empresa.
     */
    public function data(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = (int) $user->empresa_id;

        // Consulta utilizando el modelo correcto ProductoReceta
        $recetas = ProductoReceta::query()
            ->where('empresa_id', $empresaId)
            ->with(['producto:id,nombre,sku,precio_base', 'ingredientes.insumo'])
            ->get()
            ->map(function (ProductoReceta $receta): array {
                return [
                    'id' => $receta->id,
                    'producto' => $receta->producto->nombre ?? 'Sin producto',
                    'sku' => $receta->producto->sku ?? '---',
                    // Propiedades directas del modelo ProductoReceta
                    'estacion_cocina' => $receta->estacion_cocina,
                    'tiempo_preparacion_minutos' => $receta->tiempo_preparacion_minutos,
                    'rendimiento_porciones' => $receta->rendimiento_porciones,
                ];
            });

        return response()->json($recetas);
    }
}
