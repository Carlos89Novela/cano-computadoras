<x-app-layout>
    {{-- ===================================================================== --}}
    {{-- ENCABEZADO DE LA FICHA TÉCNICA DE RECETA (BOM)                      --}}
    {{-- ===================================================================== --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-lg text-slate-100 flex items-center gap-2">
                <span>🍳</span> Ficha Técnica de Receta: {{ $producto->nombre }}
            </h2>
            <a href="{{ route('productos.index', $empresa) }}" class="text-xs text-slate-400 hover:text-white transition">
                &larr; Volver al Catálogo
            </a>
        </div>
    </x-slot>

    {{-- ===================================================================== --}}
    {{-- CONTENEDOR PRINCIPAL CON ESTADO REACTIVO ALPINE.JS                  --}}
    {{-- ===================================================================== --}}
    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="gestorReceta({
             insumosDisponibles: {{ Js::from($insumosDisponibles ?? []) }},
             ingredientesIniciales: {{ Js::from(
                 ($receta?->ingredientes ?? collect())->map(fn ($i) => [
                     'insumo_producto_id' => $i->insumo_producto_id !== null ? (int) $i->insumo_producto_id : '',
                     'cantidad_requerida' => (float) ($i->cantidad_requerida ?? 0),
                     'porcentaje_merma' => (float) ($i->porcentaje_merma ?? 0),
                     'unidad_medida' => $i->unidad_medida ?? null,
                 ])->values()
             ) }}
         })">

        {{-- Mensaje de confirmación al guardar cambios en la ficha técnica --}}
        @if (session('success'))
            <div class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        {{-- Errores de validación del formulario --}}
        @if ($errors->any())
            <div class="mb-4 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs">
                <p class="font-bold mb-1">Revisa los datos de la ficha técnica:</p>
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('productos.recetas.update', [$empresa, $producto]) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- ============================================================= --}}
            {{-- SECCIÓN 1: CONFIGURACIÓN GENERAL DEL PLATILLO / PREPARACIÓN   --}}
            {{-- ============================================================= --}}
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">
                    Configuración General del Platillo
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    {{-- Rendimiento en porciones por receta --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Rendimiento (Porciones)</label>
                        <input type="number" step="0.5" min="0.5" name="rendimiento_porciones"
                               value="{{ old('rendimiento_porciones', $receta->rendimiento_porciones ?? 1) }}"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    {{-- Tiempo estimado de preparación para cocina / KDS --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Tiempo Preparación (Minutos)</label>
                        <input type="number" min="1" name="tiempo_preparacion_minutos"
                               value="{{ old('tiempo_preparacion_minutos', $receta->tiempo_preparacion_minutos ?? 10) }}"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    {{-- Estación de destino en el monitor KDS --}}
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

                    {{-- Estado activo/inactivo de la receta --}}
                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="activo" value="1" @checked(old('activo', $receta->activo ?? true))
                                   class="rounded border-slate-800 bg-slate-950 text-indigo-600 shadow-sm focus:ring-0">
                            <span class="text-xs font-medium text-slate-300">Receta Habilitada</span>
                        </label>
                    </div>
                </div>

                {{-- Notas e instrucciones de montaje/emplatado --}}
                <div class="mt-4">
                    <label class="block text-xs font-medium text-slate-300 mb-1">Instrucciones de Montaje / Emplatado</label>
                    <textarea name="instrucciones" rows="2" placeholder="Notas para el cocinero en la pantalla KDS..."
                              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">{{ old('instrucciones', $receta->instrucciones ?? '') }}</textarea>
                </div>
            </div>

            {{-- ============================================================= --}}
            {{-- SECCIÓN 2: INSUMOS Y MATERIA PRIMA (ESCANDALLO / BOM)         --}}
            {{-- ============================================================= --}}
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                    <div>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                            Insumos Requeridos por Porción (Escandallo)
                        </h3>
                        <p class="text-[11px] text-slate-400">Estos artículos se descontarán automáticamente del almacén al cobrar o comandar.</p>
                    </div>
                    <button type="button" @click="agregarIngrediente()"
                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold shadow transition cursor-pointer">
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
                                    {{-- Selector de insumo/materia prima --}}
                                    <td class="py-2.5 px-3">
                                        <select :name="`ingredientes[${idx}][insumo_producto_id]`"
                                                x-model.number="ing.insumo_producto_id"
                                                required
                                                class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                                            <option value="">-- Seleccionar Insumo --</option>
                                            <template x-for="item in insumosDisponibles" :key="item.id">
                                                <option :value="Number(item.id)"
                                                        :selected="Number(item.id) === Number(ing.insumo_producto_id)"
                                                        x-text="`${item.nombre} (${item.sku ?? 'S/N'})`"></option>
                                            </template>
                                        </select>
                                    </td>

                                    {{-- Unidad de medida del insumo seleccionado --}}
                                    <td class="py-2.5 px-3 text-slate-400 font-mono"
                                        x-text="obtenerUnidad(ing.insumo_producto_id, ing.unidad_medida)"></td>

                                    {{-- Cantidad requerida por porción --}}
                                    <td class="py-2.5 px-3">
                                        <input type="number" step="0.0001" min="0.0001"
                                               :name="`ingredientes[${idx}][cantidad_requerida]`"
                                               x-model.number="ing.cantidad_requerida"
                                               required
                                               class="w-28 bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white font-mono focus:outline-none focus:border-indigo-500">
                                    </td>

                                    {{-- Porcentaje de merma aplicable al insumo --}}
                                    <td class="py-2.5 px-3">
                                        <input type="number" step="0.5" min="0" max="100"
                                               :name="`ingredientes[${idx}][porcentaje_merma]`"
                                               x-model.number="ing.porcentaje_merma"
                                               class="w-20 bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"> %
                                    </td>

                                    {{-- Costo estimado por renglón considerando cantidad y merma --}}
                                    <td class="py-2.5 px-3 text-right font-mono text-emerald-400 font-medium"
                                        x-text="'$' + calcularCostoLinea(ing).toFixed(2)">
                                    </td>

                                    {{-- Eliminar renglón de insumo --}}
                                    <td class="py-2.5 px-3 text-center">
                                        <button type="button" @click="eliminarIngrediente(idx)"
                                                class="text-rose-400 hover:text-rose-300 font-bold px-2 py-1 cursor-pointer">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    {{-- Mensaje cuando no existen ingredientes en la receta --}}
                    <template x-if="ingredientes.length === 0">
                        <div class="py-6 text-center text-xs text-slate-500">
                            No se han asignado insumos a esta receta. Presiona "+ Agregar Insumo" para comenzar.
                        </div>
                    </template>
                </div>

                {{-- Resumen comparativo entre precio de venta y costo teórico de insumos --}}
                <div class="pt-4 border-t border-slate-800 flex flex-wrap justify-between items-center gap-4 text-xs">
                    <div class="text-slate-400">
                        Precio de Venta Actual: <strong class="text-white font-mono">${{ number_format((float) $producto->precio_base, 2) }}</strong>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-slate-400">Costo Total de Insumos:</span>
                        <span class="text-base font-bold font-mono text-emerald-400" x-text="'$' + calcularCostoTotal().toFixed(2)"></span>
                    </div>
                </div>
            </div>

            {{-- Botón de guardado de la ficha técnica --}}
            <div class="flex justify-end gap-3">
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg transition cursor-pointer">
                    Guardar Ficha Técnica
                </button>
            </div>
        </form>
    </div>

    {{-- ===================================================================== --}}
    {{-- LÓGICA ALPINE.JS PARA GESTIÓN DE INSUMOS Y CÁLCULO DE COSTOS (BOM)  --}}
    {{-- ===================================================================== --}}
    <script>
        /**
         * Controlador reactivo de Alpine.js para administrar los ingredientes
         * y calcular el costeo teórico de una receta.
         *
         * @param {Object} config
         */
        function gestorReceta(config) {
            return {
                // Catálogo de insumos disponibles para seleccionar
                insumosDisponibles: Array.isArray(config.insumosDisponibles) ? config.insumosDisponibles : [],

                // Lista de ingredientes cargados o agregados en la ficha técnica
                ingredientes: Array.isArray(config.ingredientesIniciales)
                    ? config.ingredientesIniciales.map(ing => ({
                        insumo_producto_id: ing.insumo_producto_id ? Number(ing.insumo_producto_id) : '',
                        cantidad_requerida: Number(ing.cantidad_requerida ?? 0),
                        porcentaje_merma: Number(ing.porcentaje_merma ?? 0),
                        unidad_medida: ing.unidad_medida ?? null
                    }))
                    : [],

                /**
                 * Agrega una nueva fila vacía de insumo al escandallo.
                 */
                agregarIngrediente() {
                    this.ingredientes.push({
                        insumo_producto_id: '',
                        cantidad_requerida: 1.0000,
                        porcentaje_merma: 0.00,
                        unidad_medida: null
                    });
                },

                /**
                 * Elimina una fila de ingrediente según su índice.
                 *
                 * @param {number} index
                 */
                eliminarIngrediente(index) {
                    this.ingredientes.splice(index, 1);
                },

                /**
                 * Busca un insumo dentro del catálogo disponible por ID.
                 *
                 * @param {number|string} insumoId
                 * @returns {Object|undefined}
                 */
                buscarInsumo(insumoId) {
                    if (!insumoId) return undefined;
                    return this.insumosDisponibles.find(i => Number(i.id) === Number(insumoId));
                },

                /**
                 * Obtiene la unidad de medida del insumo seleccionado o su respaldo.
                 *
                 * @param {number|string} insumoId
                 * @param {string|null} unidadRespaldo
                 * @returns {string}
                 */
                obtenerUnidad(insumoId, unidadRespaldo = null) {
                    const insumo = this.buscarInsumo(insumoId);
                    return insumo?.unidad_medida || unidadRespaldo || '-';
                },

                /**
                 * Calcula el costo estimado de una línea aplicando merma y costo de referencia.
                 *
                 * @param {Object} ing
                 * @returns {number}
                 */
                calcularCostoLinea(ing) {
                    const insumo = this.buscarInsumo(ing.insumo_producto_id);
                    if (!insumo) return 0;

                    const mermaFactor = 1 + ((Number(ing.porcentaje_merma) || 0) / 100);
                    const cantidad = Number(ing.cantidad_requerida) || 0;
                    const costoRef = Number(insumo.costo_referencia ?? insumo.precio_base ?? 0);

                    return cantidad * mermaFactor * costoRef;
                },

                /**
                 * Suma el costo teórico de todos los insumos de la receta.
                 *
                 * @returns {number}
                 */
                calcularCostoTotal() {
                    return this.ingredientes.reduce((total, ing) => total + this.calcularCostoLinea(ing), 0);
                }
            };
        }
    </script>
</x-app-layout>
