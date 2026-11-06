<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-white">
                    Recepción de Mercancía en Almacén
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    Ingreso múltiple de artículos con cálculo de Costo Promedio Ponderado (CPP) y enlace a Órdenes de Compra.
                </p>
            </div>
            <a href="{{ route('inventario.recepciones.index', $empresa) }}"
               class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-semibold transition">
                ← Ver Historial de Recepciones
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="recepcionMercancia({
        empresaId: {{ $empresa->id }},
        catalogoProductos: {{ Js::from($productos) }},
        ordenesCompra: {{ Js::from($ordenesCompra) }},
        urlOrdenData: '{{ route('inventario.recepciones.orden-data', ['empresa' => $empresa->id, 'orden' => ':ORDEN_ID:']) }}'
    })">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Mensajes de Notificación --}}
            @if (session('success'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl text-xs font-medium">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-2xl text-xs font-medium space-y-1">
                    <div class="font-bold">Por favor corrige los siguientes errores:</div>
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('inventario.recepciones.store', $empresa) }}" method="POST" class="space-y-6">
                @csrf

                {{-- Campo oculto para la orden vinculada --}}
                <input type="hidden" name="orden_compra_id" :value="form.orden_compra_id">

                {{-- Panel 1: Parámetros del Documento y Logística --}}
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider pb-2 border-b border-slate-800">
                        1. Información General y Origen
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {{-- Enlace a Orden de Compra --}}
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-300 block mb-1 font-medium">
                                Enlazar a Orden de Compra (Opcional)
                            </label>
                            <select x-model="form.orden_compra_id" @change="cargarDatosOrdenCompra()"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                <option value="">-- Entrada Directa / Sin Orden Previa --</option>
                                <template x-for="oc in ordenesCompra" :key="oc.id">
                                    <option :value="oc.id" x-text="`${oc.folio} - ${oc.proveedor?.razon_social || oc.proveedor?.nombre_comercial || 'Proveedor'} [${oc.estado.toUpperCase()}]`"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Tipo de Recepción --}}
                        <div>
                            <label class="text-xs text-slate-300 block mb-1 font-medium">Tipo de Entrada *</label>
                            <select name="tipo" x-model="form.tipo" required
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo }}">{{ ucfirst(str_replace('_', ' ', $tipo)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Almacén Destino --}}
                        <div>
                            <label class="text-xs text-slate-300 block mb-1 font-medium">Almacén Destino *</label>
                            <select name="almacen_id" x-model.number="form.almacen_id" required :disabled="form.orden_compra_id !== ''"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 disabled:opacity-50">
                                <option value="">Seleccione almacén...</option>
                                @foreach ($almacenes as $alm)
                                    <option value="{{ $alm->id }}">{{ $alm->nombre }} ({{ $alm->codigo }})</option>
                                @endforeach
                            </select>
                            {{-- Respaldo si el campo está disabled --}}
                            <template x-if="form.orden_compra_id !== ''">
                                <input type="hidden" name="almacen_id" :value="form.almacen_id">
                            </template>
                        </div>

                        {{-- Proveedor --}}
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-300 block mb-1 font-medium">Proveedor *</label>
                            <select name="proveedor_id" x-model.number="form.proveedor_id" required :disabled="form.orden_compra_id !== ''"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 disabled:opacity-50">
                                <option value="">Seleccione proveedor...</option>
                                @foreach ($proveedores as $prov)
                                    <option value="{{ $prov->id }}">{{ $prov->razon_social ?? $prov->nombre_comercial ?? $prov->nombre }} ({{ $prov->rfc }})</option>
                                @endforeach
                            </select>
                            <template x-if="form.orden_compra_id !== ''">
                                <input type="hidden" name="proveedor_id" :value="form.proveedor_id">
                            </template>
                        </div>

                        {{-- Factura / Remisión Externa --}}
                        <div>
                            <label class="text-xs text-slate-300 block mb-1 font-medium">Factura / Remisión</label>
                            <input type="text" name="referencia_externa" x-model="form.referencia_externa" placeholder="ej. FAC-A-8941"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white uppercase focus:outline-none focus:border-indigo-500">
                        </div>

                        {{-- Justificación / Motivo --}}
                        <div>
                            <label class="text-xs text-slate-300 block mb-1 font-medium">Motivo de Recepción *</label>
                            <input type="text" name="motivo" x-model="form.motivo" required placeholder="ej. Surtido de pedido de refacciones"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                </div>

                {{-- Panel 2: Desglose de Partidas Recibidas --}}
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <div>
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                2. Partidas y Existencias
                            </h3>
                            <p class="text-[11px] text-slate-400">Verifica la cantidad física que ingresa y el costo unitario para el recálculo del Costo Promedio.</p>
                        </div>
                        <template x-if="form.orden_compra_id === ''">
                            <button type="button" @click="agregarLineaVacia()"
                                    class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                                + Agregar Artículo
                            </button>
                        </template>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-400 uppercase font-semibold text-[10px]">
                                    <th class="py-2.5 px-3">Artículo / Producto</th>
                                    <template x-if="form.orden_compra_id !== ''">
                                        <th class="py-2.5 px-3 text-center">Solicitado</th>
                                    </template>
                                    <template x-if="form.orden_compra_id !== ''">
                                        <th class="py-2.5 px-3 text-center">Pendiente</th>
                                    </template>
                                    <th class="py-2.5 px-3 text-center w-32">Cant. Recibida *</th>
                                    <th class="py-2.5 px-3 text-right w-36">Costo Unitario ($) *</th>
                                    <th class="py-2.5 px-3 text-right w-36">Importe ($)</th>
                                    <th class="py-2.5 px-3 text-center w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <template x-for="(linea, idx) in lineas" :key="idx">
                                    <tr class="hover:bg-slate-800/30 transition">
                                        {{-- Selector de Producto --}}
                                        <td class="py-2.5 px-3">
                                            <template x-if="form.orden_compra_id !== ''">
                                                <div>
                                                    <span class="font-bold text-white" x-text="linea.nombre"></span>
                                                    <span class="block text-[10px] font-mono text-indigo-400" x-text="`SKU: ${linea.sku}`"></span>
                                                    <input type="hidden" :name="`lineas[${idx}][producto_id]`" :value="linea.producto_id">
                                                </div>
                                            </template>
                                            <template x-if="form.orden_compra_id === ''">
                                                <select :name="`lineas[${idx}][producto_id]`" x-model.number="linea.producto_id" required
                                                        @change="onProductoSeleccionado(idx)"
                                                        class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                                                    <option value="">Seleccione un producto...</option>
                                                    <template x-for="p in catalogoProductos" :key="p.id">
                                                        <option :value="p.id" x-text="`${p.nombre} (${p.sku})`"></option>
                                                    </template>
                                                </select>
                                            </template>
                                        </td>

                                        {{-- Cantidad Solicitada (solo OC) --}}
                                        <template x-if="form.orden_compra_id !== ''">
                                            <td class="py-2.5 px-3 text-center font-mono text-slate-300" x-text="Number(linea.cantidad_solicitada).toFixed(2)"></td>
                                        </template>

                                        {{-- Cantidad Pendiente (solo OC) --}}
                                        <template x-if="form.orden_compra_id !== ''">
                                            <td class="py-2.5 px-3 text-center font-mono font-semibold text-amber-400" x-text="Number(linea.cantidad_pendiente).toFixed(2)"></td>
                                        </template>

                                        {{-- Cantidad Recibida --}}
                                        <td class="py-2.5 px-3">
                                            <input type="number" step="0.0001" min="0.0001" :max="form.orden_compra_id ? linea.cantidad_pendiente : null"
                                                   :name="`lineas[${idx}][cantidad]`" x-model.number="linea.cantidad" required
                                                   class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-center font-mono text-white focus:outline-none focus:border-indigo-500">
                                        </td>

                                        {{-- Costo Unitario --}}
                                        <td class="py-2.5 px-3">
                                            <input type="number" step="0.0001" min="0" :name="`lineas[${idx}][costo_unitario]`"
                                                   x-model.number="linea.costo_unitario" required
                                                   class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2 py-1.5 text-xs text-right font-mono text-white focus:outline-none focus:border-indigo-500">
                                        </td>

                                        {{-- Subtotal Calculado --}}
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-400"
                                            x-text="`$${(Number(linea.cantidad || 0) * Number(linea.costo_unitario || 0)).toFixed(2)}`">
                                        </td>

                                        {{-- Eliminar Fila --}}
                                        <td class="py-2.5 px-3 text-center">
                                            <button type="button" @click="eliminarLinea(idx)" class="text-rose-400 hover:text-rose-300 font-bold p-1">
                                                &times;
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                <template x-if="lineas.length === 0">
                                    <tr>
                                        <td :colspan="form.orden_compra_id !== '' ? 7 : 5" class="py-8 text-center text-slate-500 text-xs">
                                            No hay productos agregados para la recepción.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Resumen de Importe Total --}}
                    <div class="pt-4 border-t border-slate-800 flex justify-end">
                        <div class="w-64 space-y-2 text-xs">
                            <div class="flex justify-between text-slate-400">
                                <span>Partidas:</span>
                                <span class="text-white font-mono font-bold" x-text="lineas.length"></span>
                            </div>
                            <div class="flex justify-between text-sm font-bold text-white pt-2 border-t border-slate-800">
                                <span>Total Valuado:</span>
                                <span class="text-emerald-400 font-mono text-base" x-text="`$${totalImporte.toFixed(2)}`"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Botones de Acción --}}
                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('inventario.recepciones.index', $empresa) }}"
                       class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">
                        Cancelar
                    </a>
                    <button type="submit" :disabled="lineas.length === 0"
                            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/20 transition">
                        Confirmar y Asentar Recepción en Inventario
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Lógica Reactiva Alpine.js --}}
    <script>
        function recepcionMercancia(params) {
            return {
                catalogoProductos: params.catalogoProductos || [],
                ordenesCompra: params.ordenesCompra || [],
                urlOrdenData: params.urlOrdenData,

                form: {
                    orden_compra_id: '',
                    tipo: 'compra',
                    almacen_id: '',
                    proveedor_id: '',
                    referencia_externa: '',
                    motivo: 'Recepción de mercancía a almacén'
                },

                lineas: [],

                init() {
                    // Inicializar con una línea vacía si no hay orden seleccionada
                    if (this.lineas.length === 0) {
                        this.agregarLineaVacia();
                    }
                },

                agregarLineaVacia() {
                    this.lineas.push({
                        producto_id: '',
                        sku: '',
                        nombre: '',
                        cantidad_solicitada: 0,
                        cantidad_pendiente: 0,
                        cantidad: 1,
                        costo_unitario: 0
                    });
                },

                eliminarLinea(idx) {
                    this.lineas.splice(idx, 1);
                },

                onProductoSeleccionado(idx) {
                    const prodId = this.lineas[idx].producto_id;
                    const prod = this.catalogoProductos.find(p => p.id === prodId);
                    if (prod) {
                        this.lineas[idx].costo_unitario = Number(prod.costo_referencia) || 0;
                        this.lineas[idx].sku = prod.sku;
                        this.lineas[idx].nombre = prod.nombre;
                    }
                },

                async cargarDatosOrdenCompra() {
                    const ordenId = this.form.orden_compra_id;

                    if (!ordenId) {
                        this.lineas = [];
                        this.agregarLineaVacia();
                        return;
                    }

                    try {
                        const endpoint = this.urlOrdenData.replace(':ORDEN_ID:', ordenId);
                        const res = await fetch(endpoint, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (res.ok) {
                            const data = await res.json();
                            this.form.almacen_id = data.almacen_id;
                            this.form.proveedor_id = data.proveedor_id;
                            this.form.motivo = `Recepción de Orden de Compra: ${data.folio}`;
                            this.lineas = data.lineas;
                        } else {
                            alert('No fue posible cargar los datos de la orden de compra seleccionada.');
                        }
                    } catch (e) {
                        console.error('Error al consultar orden de compra:', e);
                        alert('Error de red al consultar la orden.');
                    }
                },

                get totalImporte() {
                    return this.lineas.reduce((acc, linea) => {
                        return acc + (Number(linea.cantidad || 0) * Number(linea.costo_unitario || 0));
                    }, 0);
                }
            };
        }
    </script>
</x-app-layout>