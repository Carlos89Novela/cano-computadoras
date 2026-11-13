<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold leading-tight text-white flex items-center gap-2">
                <span>🍳 Ficha Técnica de Receta: <span class="text-indigo-400">{{ $producto->nombre }}</span></span>
            </h2>
            <a href="{{ route('restaurante.recetas.index') }}"
               class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-lg border border-slate-800 transition">
                &larr; Volver al Recetario
            </a>
        </div>
    </x-slot>

    @php
        $ingredientesExistentes = [];
        if ($receta) {
            foreach ($receta->ingredientes as $ing) {
                $ins = $ing->insumo;
                $ingredientesExistentes[] = [
                    'insumo_producto_id' => $ing->insumo_producto_id,
                    'cantidad_requerida' => (float) $ing->cantidad_requerida,
                    'porcentaje_merma' => (float) $ing->porcentaje_merma,
                    'unidad_medida' => $ins?->unidad_medida ?? 'kg',
                    'costo_referencia' => (float) ($ins?->costo_referencia ?? $ins?->precio_base ?? 0),
                ];
            }
        }
    @endphp

    <div class="py-8" x-data="recetaEditor()">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

            <form action="{{ route('productos.recetas.update', ['empresa' => $empresa->id, 'producto' => $producto->id]) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- 1. CONFIGURACIÓN GENERAL --}}
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-5 mb-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-400">Configuración General del Platillo</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Rendimiento (Porciones)</label>
                            <input type="number" step="0.01" min="0.01" name="rendimiento_porciones"
                                   value="{{ old('rendimiento_porciones', $receta?->rendimiento_porciones ?? 1.00) }}"
                                   class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500" required>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Tiempo Preparación (Minutos)</label>
                            <input type="number" min="1" name="tiempo_preparacion_minutos"
                                   value="{{ old('tiempo_preparacion_minutos', $receta?->tiempo_preparacion_minutos ?? 10) }}"
                                   class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500" required>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Estación de Cocina (KDS)</label>
                            <select name="estacion_cocina" class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500" required>
                                @php $est = old('estacion_cocina', $receta?->estacion_cocina ?? 'cocina'); @endphp
                                <option value="cocina" @selected($est === 'cocina')>🍳 Cocina Caliente</option>
                                <option value="parrilla" @selected($est === 'parrilla')>🔥 Parrilla / Cortes</option>
                                <option value="postres" @selected($est === 'postres')>🍰 Repostería / Postres</option>
                                <option value="barra" @selected($est === 'barra')>🍹 Barra / Bebidas</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2 pt-5">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" name="activo" value="1" id="receta-activa"
                                   @checked(old('activo', $receta?->activo ?? true))
                                   class="rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-0">
                            <label for="receta-activa" class="text-xs font-semibold text-slate-300">Receta Habilitada</label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Instrucciones de Montaje / Emplatado</label>
                        <textarea name="instrucciones" rows="2"
                                  placeholder="Detalles sobre presentación, temperatura y notas técnicas para la brigada..."
                                  class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl p-3 text-white focus:outline-none focus:border-indigo-500">{{ old('instrucciones', $receta?->instrucciones ?? '') }}</textarea>
                    </div>
                </div>

                {{-- 2. INSUMOS E INGREDIENTES (BOM) --}}
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-400">Insumos Requeridos por Porción (Escandallo)</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Estos artículos se descontarán automáticamente del almacén al cobrar o comandar.</p>
                        </div>
                        <button type="button" @click="agregarLinea()"
                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1">
                            <span>+</span> Agregar Insumo
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead>
                                <tr class="text-slate-500 border-b border-slate-800 text-[10px] uppercase font-bold tracking-wider">
                                    <th class="py-2.5 w-5/12">Materia Prima / Insumo</th>
                                    <th class="py-2.5 w-1/12 text-center">Unidad</th>
                                    <th class="py-2.5 w-2/12">Cant. Requerida</th>
                                    <th class="py-2.5 w-2/12">% Merma</th>
                                    <th class="py-2.5 w-1/12 text-right">Costo Estimado</th>
                                    <th class="py-2.5 w-1/12 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(linea, index) in lineas" :key="index">
                                    <tr class="border-b border-slate-800/60 hover:bg-slate-950/40">
                                        <td class="py-2.5 pr-2">
                                            <select :name="'ingredientes[' + index + '][insumo_producto_id]'"
                                                    x-model="linea.insumo_producto_id"
                                                    @change="alCambiarInsumo(index)"
                                                    class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1.5 text-white focus:outline-none focus:border-indigo-500" required>
                                                <option value="">-- Seleccionar Insumo --</option>
                                                @foreach ($insumosDisponibles as $ins)
                                                    <option value="{{ $ins->id }}"
                                                            data-unidad="{{ $ins->unidad_medida }}"
                                                            data-costo="{{ (float) ($ins->costo_referencia ?? $ins->precio_base) }}">
                                                        {{ $ins->nombre }} ({{ strtoupper($ins->sku) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="py-2.5 text-center text-slate-400 font-mono" x-text="linea.unidad_medida || '-'"></td>
                                        <td class="py-2.5 pr-2">
                                            <input type="number" step="0.0001" min="0.0001"
                                                   :name="'ingredientes[' + index + '][cantidad_requerida]'"
                                                   x-model.number="linea.cantidad_requerida"
                                                   class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1.5 text-white focus:outline-none focus:border-indigo-500" required>
                                        </td>
                                        <td class="py-2.5 pr-2">
                                            <div class="flex items-center gap-1">
                                                <input type="number" step="0.01" min="0" max="100"
                                                       :name="'ingredientes[' + index + '][porcentaje_merma]'"
                                                       x-model.number="linea.porcentaje_merma"
                                                       class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                                <span class="text-slate-500">%</span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 text-right font-bold text-emerald-400"
                                            x-text="'$' + calcularCostoLinea(linea).toFixed(2)"></td>
                                        <td class="py-2.5 text-center">
                                            <button type="button" @click="quitarLinea(index)"
                                                    class="text-rose-400 hover:text-rose-300 font-bold p-1">✕</button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="lineas.length === 0">
                                    <td colspan="6" class="py-8 text-center text-slate-500 text-xs">
                                        No hay insumos asignados a esta receta. Haz clic en "+ Agregar Insumo".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Totales y Guardar --}}
                    <div class="pt-4 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs text-slate-400 space-y-1">
                            <div>Precio de Venta Actual: <strong class="text-white">${{ number_format((float) $producto->precio_base, 2) }}</strong></div>
                            <div>Costo Total de Insumos: <strong class="text-emerald-400" x-text="'$' + calcularCostoTotal().toFixed(2)"></strong></div>
                        </div>

                        <button type="submit"
                                class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg transition">
                            Guardar Ficha Técnica
                        </button>
                    </div>
                </div>

            </form>

        </div>
    </div>

    <script>
        function recetaEditor() {
            return {
                lineas: @json($ingredientesExistentes),

                init() {
                    // Si ya traía insumos, asegurar unidad y costo de referencia
                    this.lineas.forEach(l => {
                        const opt = document.querySelector(`select option[value="${l.insumo_producto_id}"]`);
                        if (opt) {
                            l.unidad_medida = opt.getAttribute('data-unidad') || l.unidad_medida;
                            l.costo_referencia = parseFloat(opt.getAttribute('data-costo')) || l.costo_referencia;
                        }
                    });
                },

                agregarLinea() {
                    this.lineas.push({
                        insumo_producto_id: '',
                        cantidad_requerida: 0.100,
                        porcentaje_merma: 0,
                        unidad_medida: '',
                        costo_referencia: 0
                    });
                },

                quitarLinea(idx) {
                    this.lineas.splice(idx, 1);
                },

                alCambiarInsumo(idx) {
                    this.$nextTick(() => {
                        const select = document.querySelectorAll('select[name^="ingredientes"]')[idx];
                        if (select) {
                            const opt = select.options[select.selectedIndex];
                            if (opt && opt.value) {
                                this.lineas[idx].unidad_medida = opt.getAttribute('data-unidad') || '';
                                this.lineas[idx].costo_referencia = parseFloat(opt.getAttribute('data-costo')) || 0;
                            }
                        }
                    });
                },

                calcularCostoLinea(linea) {
                    const cant = parseFloat(linea.cantidad_requerida) || 0;
                    const merma = parseFloat(linea.porcentaje_merma) || 0;
                    const costo = parseFloat(linea.costo_referencia) || 0;
                    return (costo * cant) * (1 + (merma / 100));
                },

                calcularCostoTotal() {
                    return this.lineas.reduce((acc, l) => acc + this.calcularCostoLinea(l), 0);
                }
            };
        }
    </script>
</x-app-layout>
