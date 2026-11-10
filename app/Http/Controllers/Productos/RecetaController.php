<?php

namespace App\Http\Controllers\Productos;

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

class RecetaController extends Controller
{
    /**
     * Muestra la interfaz de edición o creación de la receta para un producto.
     */
    public function edit(Empresa $empresa, Producto$producto): View
    {
        abort_unless($producto->empresa_id ===$empresa->id, 404);

        $receta =$producto->receta()->with('ingredientes.insumo')->first();

        // Insumos elegibles (productos de la empresa que no son recetas elaboradas)
        $insumosDisponibles = Producto::query()
            ->where('empresa_id', $empresa->id)
            ->where('id', '!=', $producto->id)
            ->where('es_receta', false)
            ->where('activo', true)
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
     * Guarda o actualiza la ficha técnica y la lista de ingredientes del platillo.
     */
    public function update(Request $request, Empresa $empresa, Producto$producto): RedirectResponse
    {
        abort_unless($producto->empresa_id ===$empresa->id, 404);

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
         * } $validated */
        $validated =$request->validate([
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

        DB::transaction(function () use ($empresa, $producto,$validated) {
            // Marcar el producto como receta
            $producto->update(['es_receta' => true]);

            /** @var ProductoReceta $receta */$receta = ProductoReceta::query()->updateOrCreate(
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

            // Reemplazar la lista de ingredientes de la receta
            $receta->ingredientes()->delete();

            if (! empty($validated['ingredientes'])) {
                foreach ($validated['ingredientes'] as$ing) {
                    ProductoRecetaDetalle::query()->create([
                        'producto_receta_id' => $receta->id,
                        'insumo_producto_id' => $ing['insumo_producto_id'],
                        'cantidad_requerida' => $ing['cantidad_requerida'],
                        'porcentaje_merma' => $ing['porcentaje_merma'] ?? 0.00,
                    ]);
                }
            }
        });

        return redirect()
            ->route('productos.recetas.edit', ['empresa' => $empresa, 'producto' =>$producto])
            ->with('success', 'Receta y componentes guardados exitosamente.');
    }
}
