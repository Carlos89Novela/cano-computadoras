<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-lg text-slate-100 flex items-center gap-2">
                <span>🍳</span> Ficha Técnica de Receta: {{ $producto->nombre }}
            </h2>
            <a href="{{ route('productos.productos.index', $empresa) }}" class="text-xs text-slate-400 hover:text-white transition">
                &larr; Volver al Catálogo
            </a>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="gestorReceta({
             insumosDisponibles: {{ Js::from($insumosDisponibles) }},
             ingredientesIniciales: {{ Js::from($receta?->ingredientes->map(fn($i) => [
                 'insumo_producto_id' => $i->insumo_producto_id,
                 'cantidad_requerida' => (float) $i->cantidad_requerida,
                 'porcentaje_merma' => (float) $i->porcentaje_merma,
             ]) ?? []) }}
         })">

        @if (session('success'))
            <div class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('productos.recetas.update', [$empresa, $producto]) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Datos Generales de la Ficha Técnica -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">
                    Configuración General del Platillo
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Rendimiento (Porciones)</label>
                        <input type="number" step="0.5" min="0.5" name="rendimiento_porciones"
                               value="{{ old('rendimiento_porciones', $receta->rendimiento_porciones ?? 1) }}"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Tiempo Preparación (Minutos)</label>
                        <input type="number" min="1" name="tiempo_preparacion_minutos"
                               value="{{ old('tiempo_preparacion_minutos', $receta->tiempo_preparacion_minutos ?? 10) }}"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Estación de Cocina (KDS)</label>
                        <select name="estacion_cocina" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                            @foreach (['cocina' => 'Cocina Caliente', 'barra' => 'Barra / Bebidas', 'parrilla' => 'Parrilla', 'postres' => 'Repostería / Fríos'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('estacion_cocina', $receta->estacion_cocina ?? 'cocina') === $val)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="activo" value="1" @checked(old('activo', $receta->activo ?? true))
                                   class="rounded border-slate-800 bg-slate-950 text-indigo-600 shadow-sm focus:ring-0">
                            <span class="text-xs font-medium text-slate-300">Receta Habilitada</span>
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-medium text-slate-300 mb-1">Instrucciones de Montaje / Emplatado</label>
                    <textarea name="instrucciones" rows="2" placeholder="Notas para el cocinero en la pantalla KDS..."
                              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">{{ old('instrucciones', $receta->instrucciones ?? '') }}</textarea>
                </div>
            </div>

            <!-- Insumos y Materia Prima -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                    <div>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                            Insumos Requeridos por Porción (Escandallo)
                        </h3>
                        <p class="text-[11px] text-slate-400">Estos artículos se descontarán automáticamente del almacén al cobrar o comandar.</p>
                    </div>
                    <button type="button" @click="agregarIngrediente()"
                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow transition">
                        + Agregar Insumo
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 font-semibold uppercase text-[10px]">
                                <th class="py-2 px-3">Materia Prima / Insumo</th>
                                <th class="py-2 px-3">Unidad</th>
                                <th class="py-2 px-3">Cant. Requerida</th>
                                <th class="py-2 px-3">% Merma</th>
                                <th class="py-2 px-3 text-right">Costo Estimado</th>
                                <th class="py-2 px-3 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(ing, idx) in ingredientes" :key="idx">
                                <tr class="border-b border-slate-800/60 hover:bg-slate-950/40 transition">
                                    <td class="py-2.5 px-3">
                                        <select :name="`ingredientes[${idx}][insumo_producto_id]`" x-model.number="ing.insumo_producto_id" required
                                                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                                            <option value="">-- Seleccionar Insumo --</option>
                                            <template x-for="item in insumosDisponibles" :key="item.id">
                                                <option :value="item.id" x-text="`${item.nombre} (${item.sku})`"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-400 font-mono" x-text="obtenerUnidad(ing.insumo_producto_id)"></td>
                                    <td class="py-2.5 px-3">
                                        <input type="number" step="0.0001" min="0.0001" :name="`ingredientes[${idx}][cantidad_requerida]`"
                                               x-model.number="ing.cantidad_requerida" required
                                               class="w-28 bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <input type="number" step="0.5" min="0" max="100" :name="`ingredientes[${idx}][porcentaje_merma]`"
                                               x-model.number="ing.porcentaje_merma"
                                               class="w-20 bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"> %
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-emerald-400 font-medium"
                                        x-text="'$' + calcularCostoLinea(ing).toFixed(2)">
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <button type="button" @click="eliminarIngrediente(idx)" class="text-rose-400 hover:text-rose-300 font-bold px-2 py-1">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <template x-if="ingredientes.length === 0">
                        <div class="py-6 text-center text-xs text-slate-500">
                            No se han asignado insumos a esta receta. Presiona "+ Agregar Insumo" para comenzar.
                        </div>
                    </template>
                </div>

                <!-- Resumen de Costo y Margen Teórico -->
                <div class="pt-4 border-t border-slate-800 flex justify-between items-center text-xs">
                    <div class="text-slate-400">
                        Precio de Venta Actual: <strong class="text-white font-mono">${{ number_format((float) $producto->precio_base, 2) }}</strong>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-slate-400">Costo Total de Insumos:</span>
                        <span class="text-base font-bold font-mono text-emerald-400" x-text="'$' + calcularCostoTotal().toFixed(2)"></span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg transition">
                    Guardar Ficha Técnica
                </button>
            </div>
        </form>
    </div>

    <script>
        function gestorReceta(config) {
            return {
                insumosDisponibles: config.insumosDisponibles || [],
                ingredientes: config.ingredientesIniciales || [],

                agregarIngrediente() {
                    this.ingredientes.push({
                        insumo_producto_id: '',
                        cantidad_requerida: 1.0000,
                        porcentaje_merma: 0.00
                    });
                },

                eliminarIngrediente(index) {
                    this.ingredientes.splice(index, 1);
                },

                obtenerUnidad(insumoId) {
                    const insumo = this.insumosDisponibles.find(i => i.id === Number(insumoId));
                    return insumo ? insumo.unidad_medida : '-';
                },

                calcularCostoLinea(ing) {
                    const insumo = this.insumosDisponibles.find(i => i.id === Number(ing.insumo_producto_id));
                    if (!insumo) return 0;
                    const merma = 1 + ((Number(ing.porcentaje_merma) || 0) / 100);
                    return (Number(ing.cantidad_requerida) || 0) * merma * Number(insumo.costo_referencia);
                },

                calcularCostoTotal() {
                    return this.ingredientes.reduce((total, ing) => total + this.calcularCostoLinea(ing), 0);
                }
            };
        }
    </script>
</x-app-layout>
