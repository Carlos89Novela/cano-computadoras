<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-gray-100 leading-tight">
                    Recepción de Inventario
                </h2>
                <p class="text-sm text-gray-400 mt-1">
                    Empresa: <span class="font-semibold text-purple-400">{{ $empresa->nombre }}</span>
                </p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-gray-400 hover:text-white transition">
                &larr; Volver al panel
            </a>
        </div>
    </x-slot>

    <div class="py-8 text-gray-200" x-data="recepcionForm()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="p-4 bg-emerald-950/60 border border-emerald-600/50 text-emerald-300 rounded-lg shadow-lg">
                    <p class="font-medium flex items-center gap-2">
                        <span>✔</span> {{ session('success') }}
                    </p>
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 bg-rose-950/60 border border-rose-600/50 text-rose-300 rounded-lg shadow-lg">
                    <p class="font-semibold mb-1">Por favor verifica los siguientes campos:</p>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('inventario.recepciones.store', $empresa) }}" method="POST" class="space-y-6">
                @csrf

                <!-- Sección 1: Encabezado -->
                <div class="bg-gray-900 border border-gray-800 overflow-hidden shadow-xl sm:rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-white border-b border-gray-800 pb-3 mb-5 flex items-center gap-2">
                        <span class="flex items-center justify-center w-6 h-6 rounded-full bg-purple-600 text-xs text-white">1</span>
                        Encabezado de la Recepción
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Almacén -->
                        <div>
                            <label for="almacen_id" class="block text-sm font-medium text-gray-300">
                                Almacén de Destino <span class="text-rose-400">*</span>
                            </label>
                            <select id="almacen_id" name="almacen_id" required
                                class="mt-1.5 block w-full rounded-lg bg-gray-800 border-gray-700 text-gray-100 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="" class="bg-gray-800 text-gray-400">-- Seleccionar Almacén --</option>
                                @foreach ($almacenes as $almacen)
                                    <option value="{{ $almacen->id }}" class="bg-gray-800 text-gray-100" {{ old('almacen_id') == $almacen->id ? 'selected' : '' }}>
                                        {{ $almacen->nombre }} ({{$almacen->codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Tipo de Recepción -->
                        <div>
                            <label for="tipo" class="block text-sm font-medium text-gray-300">
                                Tipo de Entrada <span class="text-rose-400">*</span>
                            </label>
                            <select id="tipo" name="tipo" x-model="tipo" required
                                class="mt-1.5 block w-full rounded-lg bg-gray-800 border-gray-700 text-gray-100 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                @foreach ($tipos as $tipoItem)
                                    <option value="{{ $tipoItem }}" class="bg-gray-800 text-gray-100" {{ old('tipo', 'compra') === $tipoItem ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $tipoItem)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Proveedor (Condicional) -->
                        <div x-show="tipo === 'compra'" x-cloak>
                            <label for="proveedor_id" class="block text-sm font-medium text-gray-300">
                                Proveedor <span class="text-rose-400">*</span>
                            </label>
                            <select id="proveedor_id" name="proveedor_id" :required="tipo === 'compra'"
                                class="mt-1.5 block w-full rounded-lg bg-gray-800 border-gray-700 text-gray-100 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="" class="bg-gray-800 text-gray-400">-- Seleccionar Proveedor --</option>
                                @foreach ($proveedores as $proveedor)
                                    <option value="{{ $proveedor->id }}" class="bg-gray-800 text-gray-100" {{ old('proveedor_id') == $proveedor->id ? 'selected' : '' }}>
                                        {{ $proveedor->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Referencia Externa -->
                        <div>
                            <label for="referencia_externa" class="block text-sm font-medium text-gray-300">
                                Referencia Externa (Factura / Remisión)
                            </label>
                            <input type="text" id="referencia_externa" name="referencia_externa"
                                value="{{ old('referencia_externa') }}" placeholder="Ej. FAC-2026-001"
                                class="mt-1.5 block w-full rounded-lg bg-gray-800 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>

                        <!-- Motivo -->
                        <div class="md:col-span-2">
                            <label for="motivo" class="block text-sm font-medium text-gray-300">
                                Motivo / Justificación <span class="text-rose-400">*</span>
                            </label>
                            <input type="text" id="motivo" name="motivo" required
                                value="{{ old('motivo') }}" placeholder="Ej. Compra mensual de componentes de ensamble"
                                class="mt-1.5 block w-full rounded-lg bg-gray-800 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Tabla de Líneas Dinámicas -->
                <div class="bg-gray-900 border border-gray-800 overflow-hidden shadow-xl sm:rounded-xl p-6">
                    <div class="flex items-center justify-between border-b border-gray-800 pb-3 mb-5">
                        <div>
                            <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-purple-600 text-xs text-white">2</span>
                                Líneas de Productos
                            </h3>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Agrega los artículos recibidos con sus cantidades y costos pactados.
                            </p>
                        </div>
                        <button type="button" @click="agregarLinea()"
                            class="inline-flex items-center px-4 py-2 border border-purple-500/30 text-sm font-medium rounded-lg text-purple-200 bg-purple-900/40 hover:bg-purple-800/60 shadow-sm transition">
                            + Agregar Producto
                        </button>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-800">
                        <table class="min-w-full divide-y divide-gray-800 text-sm">
                            <thead class="bg-gray-800/60 text-gray-300 uppercase tracking-wider text-xs">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">Producto</th>
                                    <th class="px-4 py-3 text-right font-semibold w-36">Cantidad</th>
                                    <th class="px-4 py-3 text-right font-semibold w-40">Costo Unit. ($)</th>
                                    <th class="px-4 py-3 text-right font-semibold w-40">Subtotal ($)</th>
                                    <th class="px-4 py-3 text-center font-semibold w-20">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800 bg-gray-900/50">
                                <template x-for="(linea, index) in lineas" :key="index">
                                    <tr class="hover:bg-gray-800/30 transition">
                                        <!-- Producto -->
                                        <td class="px-4 py-2.5">
                                            <select :name="`lineas[${index}][producto_id]`" x-model="linea.producto_id"
                                                @change="onProductoChange(index)" required
                                                class="block w-full rounded-lg bg-gray-800 border-gray-700 text-gray-100 shadow-sm focus:border-purple-500 focus:ring-purple-500 text-sm">
                                                <option value="" class="bg-gray-800 text-gray-400">-- Seleccionar Producto --</option>
                                                <template x-for="p in catalogoProductos" :key="p.id">
                                                    <option :value="p.id" x-text="`${p.sku} - ${p.nombre}`"
                                                        :disabled="esProductoYaSeleccionado(p.id, index)"
                                                        class="bg-gray-800 text-gray-100">
                                                    </option>
                                                </template>
                                            </select>
                                        </td>

                                        <!-- Cantidad -->
                                        <td class="px-4 py-2.5 text-right">
                                            <input type="number" step="0.0001" min="0.0001" :name="`lineas[${index}][cantidad]`"
                                                x-model="linea.cantidad" required placeholder="0.0000"
                                                class="block w-full text-right rounded-lg bg-gray-800 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm focus:border-purple-500 focus:ring-purple-500 text-sm">
                                        </td>

                                        <!-- Costo Unitario -->
                                        <td class="px-4 py-2.5 text-right">
                                            <input type="number" step="0.0001" min="0" :name="`lineas[${index}][costo_unitario]`"
                                                x-model="linea.costo_unitario" required placeholder="0.0000"
                                                class="block w-full text-right rounded-lg bg-gray-800 border-gray-700 text-gray-100 placeholder-gray-500 shadow-sm focus:border-purple-500 focus:ring-purple-500 text-sm">
                                        </td>

                                        <!-- Subtotal -->
                                        <td class="px-4 py-2.5 text-right font-semibold text-gray-200">
                                            $<span x-text="calcularSubtotal(linea)"></span>
                                        </td>

                                        <!-- Eliminar -->
                                        <td class="px-4 py-2.5 text-center">
                                            <button type="button" @click="eliminarLinea(index)"
                                                :disabled="lineas.length === 1"
                                                :class="lineas.length === 1 ? 'opacity-20 cursor-not-allowed text-gray-600' : 'text-rose-400 hover:text-rose-200'"
                                                class="font-bold text-lg leading-none transition">
                                                &times;
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-800/80 border-t border-gray-700">
                                <tr>
                                    <td colspan="3" class="px-4 py-3.5 text-right font-bold text-gray-300">
                                        Total General:
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-extrabold text-purple-400 text-lg">
                                        $<span x-text="calcularTotalGeneral()"></span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Botón Enviar -->
                    <div class="mt-6 flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-semibold rounded-lg shadow-lg text-white bg-emerald-600 hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 focus:ring-offset-gray-900 transition">
                            Confirmar y Registrar Recepción
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function recepcionForm() {
            return {
                tipo: '{{ old('tipo', 'compra') }}',
                catalogoProductos: @json($productos),
                lineas: [
                    { producto_id: '', cantidad: '1.0000', costo_unitario: '0.0000' }
                ],
                agregarLinea() {
                    this.lineas.push({ producto_id: '', cantidad: '1.0000', costo_unitario: '0.0000' });
                },
                eliminarLinea(index) {
                    if (this.lineas.length > 1) {
                        this.lineas.splice(index, 1);
                    }
                },
                onProductoChange(index) {
                    const prodId = this.lineas[index].producto_id;
                    const prod = this.catalogoProductos.find(p => p.id == prodId);
                    if (prod && prod.costo_referencia) {
                        this.lineas[index].costo_unitario = parseFloat(prod.costo_referencia).toFixed(4);
                    }
                },
                esProductoYaSeleccionado(prodId, currentIndex) {
                    return this.lineas.some((linea, idx) => idx !== currentIndex && linea.producto_id == prodId);
                },
                calcularSubtotal(linea) {
                    const cant = parseFloat(linea.cantidad) || 0;
                    const costo = parseFloat(linea.costo_unitario) || 0;
                    return (cant * costo).toFixed(4);
                },
                calcularTotalGeneral() {
                    return this.lineas.reduce((acumulado, linea) => {
                        return acumulado + (parseFloat(this.calcularSubtotal(linea)) || 0);
                    }, 0).toFixed(4);
                }
            };
        }
    </script>
</x-app-layout>
