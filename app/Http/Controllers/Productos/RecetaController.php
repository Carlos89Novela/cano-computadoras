<?php

namespace App\Http\Controllers\Productos;

// ---------------------------------------------------------
// IMPORTACIONES DE CONTROLADORES, MODELOS Y SERVICIOS
// ---------------------------------------------------------
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoReceta;
use App\Models\ProductoRecetaDetalle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View as ViewFacade;

/**
 * Gestiona la creación y edición de fichas técnicas (BOM / escandallos)
 * y la asignación de insumos o materias primas para los productos del restaurante.
 */
class RecetaController extends Controller
{
    /**
     * Muestra la interfaz de edición o creación de la ficha técnica para un producto.
     *
     * @param Empresa  $empresa  Empresa propietaria del catálogo y la receta.
     * @param Producto $producto Producto o platillo al que se le asigna o edita la receta.
     */
    public function edit(Empresa $empresa, Producto $producto): View
    {
        // Garantizar que el producto pertenezca a la empresa solicitada
        abort_unless((int) $producto->empresa_id === (int) $empresa->id, 404);

        // Cargar la receta existente junto con sus ingredientes y el insumo asociado
        $receta = $producto->receta()
            ->with(['ingredientes.insumo'])
            ->first();

        // Obtener insumos elegibles de la empresa (excluyendo el propio producto y recetas elaboradas).
        // Se ordenan colocando primero los insumos de almacén (SKU 'INS-%') y después el resto.
        $insumosDisponibles = Producto::query()
            ->where('empresa_id', $empresa->id)
            ->where('id', '!=', $producto->id)
            ->where('es_receta', false)
            ->where('activo', true)
            ->orderByRaw("CASE WHEN UPPER(COALESCE(sku, '')) LIKE 'INS-%' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get(['id', 'sku', 'nombre', 'unidad_medida', 'costo_referencia']);

        return ViewFacade::make('productos.recetas.edit', [
            'empresa' => $empresa,
            'producto' => $producto,
            'receta' => $receta,
            'insumosDisponibles' => $insumosDisponibles,
        ]);
    }

    /**
     * Guarda o actualiza la configuración general de la ficha técnica
     * y reemplaza su lista de ingredientes / insumos requeridos.
     *
     * @param Request  $request  Datos del formulario de ficha técnica.
     * @param Empresa  $empresa  Empresa propietaria del producto y la receta.
     * @param Producto $producto Producto al que se actualiza la receta.
     */
    public function update(Request $request, Empresa $empresa, Producto $producto): RedirectResponse
    {
        // Garantizar que el producto pertenezca a la empresa solicitada
        abort_unless((int) $producto->empresa_id === (int) $empresa->id, 404);

        /** @var array{
         *     rendimiento_porciones: numeric,
         *     tiempo_preparacion_minutos: int,
         *     estacion_cocina: string,
         *     instrucciones?: string|null,
         *     activo?: bool,
         *     ingredientes?: array<int, array{
         *         insumo_producto_id: int,
         *         cantidad_requerida: numeric,
         *         porcentaje_merma?: numeric|null
         *     }>
         * } $validated
         */
        $validated = $request->validate([
            'rendimiento_porciones' => ['required', 'numeric', 'gt:0'],
            'tiempo_preparacion_minutos' => ['required', 'integer', 'min:1'],
            'estacion_cocina' => ['required', 'string', 'max:50'],
            'instrucciones' => ['nullable', 'string', 'max:2000'],
            'activo' => ['nullable', 'boolean'],
            'ingredientes' => ['nullable', 'array'],
            'ingredientes.*.insumo_producto_id' => ['required', 'integer', 'exists:productos,id'],
            'ingredientes.*.cantidad_requerida' => ['required', 'numeric', 'gt:0'],
            'ingredientes.*.porcentaje_merma' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($empresa, $producto, $validated): void {
            // Marcar el producto como platillo/producto con ficha técnica de receta
            $producto->update(['es_receta' => true]);

            /** @var ProductoReceta $receta */
            $receta = ProductoReceta::query()->updateOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'producto_id' => $producto->id,
                ],
                [
                    'rendimiento_porciones' => $validated['rendimiento_porciones'],
                    'tiempo_preparacion_minutos' => $validated['tiempo_preparacion_minutos'],
                    'estacion_cocina' => $validated['estacion_cocina'],
                    'instrucciones' => $validated['instrucciones'] ?? null,
                    'activo' => (bool) ($validated['activo'] ?? true),
                ]
            );

            // Reemplazar los ingredientes actuales por los enviados en la ficha técnica
            $receta->ingredientes()->delete();

            foreach ($validated['ingredientes'] ?? [] as $ing) {
                $insumoId = (int) $ing['insumo_producto_id'];

                // Evitar asignar el mismo producto como insumo de sí mismo
                if ($insumoId === (int) $producto->id) {
                    continue;
                }

                ProductoRecetaDetalle::query()->create([
                    'producto_receta_id' => $receta->id,
                    'insumo_producto_id' => $insumoId,
                    'cantidad_requerida' => $ing['cantidad_requerida'],
                    'porcentaje_merma' => $ing['porcentaje_merma'] ?? 0.00,
                ]);
            }
        });

        return redirect()
            ->route('productos.recetas.edit', ['empresa' => $empresa, 'producto' => $producto])
            ->with('success', 'Receta y componentes guardados exitosamente.');
    }
}
