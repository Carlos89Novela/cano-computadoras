<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Recepción de Inventario
                </h2>
                <p class="text-sm text-gray-500">
                    Empresa: <span class="font-semibold text-gray-700">{{ $empresa->nombre }}</span>
                </p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                &larr; Volver al panel
            </a>
        </div>
    </x-slot>

    <div class="py-8" x-data="recepcionForm()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Mensajes flash de éxito --}}
            @if (session('success'))
                <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 rounded shadow-sm">
                    <p class="font-medium">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Errores de validación --}}
            @if ($errors->any())
                <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 rounded shadow-sm">
                    <p class="font-semibold mb-1">Por favor verifica los siguientes campos:</p>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach ($errors->all() as$error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('inventario.recepciones.store', $empresa) }}" method="POST" class="space-y-6">
                @csrf

                {{-- Sección 1: Datos Generales del Documento --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 border-b pb-3 mb-4">
                        1. Encabezado de la Recepción
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {{-- Almacén --}}
                        <div>
                            <label for="almacen_id" class="block text-sm font-medium text-gray-700">
                                Almacén de Destino <span class="text-rose-500">*</span>
                            </label>
                            <select id="almacen_id" name="almacen_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">-- Seleccionar Almacén --</option>
                                @foreach ($almacenes as$almacen)
                                    <option value="{{ $almacen->id }}" {{ old('almacen_id') == $almacen->id ? 'selected' : '' }}>
                                        {{ $almacen->nombre }} ({{$almacen->codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Tipo de Recepción --}}
                        <div>
                            <label for="tipo" class="block text-sm font-medium text-gray-700">
                                Tipo de Entrada <span class="text-rose-500">*</span>
                            </label>
                            <select id="tipo" name="tipo" x-model="tipo" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @foreach ($tipos as$tipoItem)
                                    <option value="{{ $tipoItem }}" {{ old('tipo', 'compra') === $tipoItem ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $tipoItem)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Proveedor (Condicional para Compra) --}}
                        <div x-show="tipo === 'compra'" x-cloak>
                            <label for="proveedor_id" class="block text-sm font-medium text-gray-700">
                                Proveedor <span class="text-rose-500">*</span>
                            </label>
                            <select id="proveedor_id" name="proveedor_id" :required="tipo === 'compra'"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">-- Seleccionar Proveedor --</option>
                                @foreach ($proveedores as$proveedor)
                                    <option value="{{ $proveedor->id }}" {{ old('proveedor_id') == $proveedor->id ? 'selected' : '' }}>
                                        {{ $proveedor->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Referencia Externa --}}
                        <div>
                            <label for="referencia_externa" class="block text-sm font-medium text-gray-700">
                                Referencia Externa (Factura / Remisión)
                            </label>
                            <input type="text" id="referencia_externa" name="referencia_externa"
                                value="{{ old('referencia_externa') }}" placeholder="Ej. FAC-2026-001"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        {{-- Motivo --}}
                        <div class="md:col-span-2">
                            <label for="motivo" class="block text-sm font-medium text-gray-700">
                                Motivo / Justificación <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="motivo" name="motivo" required
                                value="{{ old('motivo') }}" placeholder="Ej. Compra mensual de componentes de ensamble"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                    </div>
                </div>

                {{-- Sección 2: Detalle de Productos y Líneas Dinámicas --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between border-b pb-3 mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                2. Líneas de Productos
                            </h3>
                            <p class="text-xs text-gray-500">
                                Agrega los artículos recibidos con sus cantidades y costos pactados.
                            </p>
                        </div>
                        <button type="button" @click="agregarLinea()"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition">
                            + Agregar Producto
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">Producto</th>
                                    <th class="px-4 py-3 text-right font-medium w-36">Cantidad</th>
                                    <th class="px-4 py-3 text-right font-medium w-40">Costo Unit. ($)</th>
                                    <th class="px-4 py-3 text-right font-medium w-40">Subtotal ($)</th>
                                    <th class="px-4 py-3 text-center font-medium w-20">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="(linea, index) in lineas" :key="index">
                                    <tr>
                                        {{-- Selector de Producto --}}
                                        <td class="px-4 py-2">
                                            <select :name="`lineas[${index}][producto_id]`" x-model="linea.producto_id"
                                                @change="onProductoChange(index)" required
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="">-- Seleccionar Producto --</option>
                                                <template x-for="p in catalogoProductos" :key="p.id">
                                                    <option :value="p.id" x-text="`${p.sku} - ${p.nombre}`"
                                                        :disabled="esProductoYaSeleccionado(p.id, index)">
                                                    </option>
                                                </template>
                                            </select>
                                        </td>

                                        {{-- Cantidad --}}
                                        <td class="px-4 py-2 text-right">
                                            <input type="number" step="0.0001" min="0.0001" :name="`lineas[${index}][cantidad]`"
                                                x-model="linea.cantidad" required placeholder="0.0000"
                                                class="block w-full text-right rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </td>

                                        {{-- Costo Unitario --}}
                                        <td class="px-4 py-2 text-right">
                                            <input type="number" step="0.0001" min="0" :name="`lineas[${index}][costo_unitario]`"
                                                x-model="linea.costo_unitario" required placeholder="0.0000"
                                                class="block w-full text-right rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </td>

                                        {{-- Subtotal Calculado --}}
                                        <td class="px-4 py-2 text-right font-medium text-gray-700">
                                            $<span x-text="calcularSubtotal(linea)"></span>
                                        </td>

                                        {{-- Eliminar Línea --}}
                                        <td class="px-4 py-2 text-center">
                                            <button type="button" @click="eliminarLinea(index)"
                                                :disabled="lineas.length === 1"
                                                :class="lineas.length === 1 ? 'opacity-30 cursor-not-allowed' : 'text-rose-600 hover:text-rose-900'"
                                                class="font-bold">
                                                &times;
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right font-bold text-gray-700">
                                        Total General:
                                    </td>
                                    <td class="px-4 py-3 text-right font-extrabold text-indigo-700 text-base">
                                        $<span x-text="calcularTotalGeneral()"></span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Botón Guardar --}}
                    <div class="mt-6 flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-semibold rounded-md shadow-sm text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition">
                            Confirmar y Registrar Recepción
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Script Reactivo con Alpine.js --}}
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
