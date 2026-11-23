{{-- ===================================================================== --}}
{{-- VISTA: CREAR ORDEN DE COMPRA A PROVEEDOR (ALPINE.JS + MULTI-TENANT)   --}}
{{-- Permite capturar sucursal, almacén, proveedor, fechas, observaciones  --}}
{{-- y partidas dinámicas vinculadas a la empresa activa.                 --}}
{{-- Considera que el costo unitario capturado ya incluye IVA (16%) y     --}}
{{-- desglosa subtotal e IVA hacia atrás para mantener coherencia con el   --}}
{{-- servicio de creación de órdenes de compra.                            --}}
{{-- ===================================================================== --}}
<x-app-layout :title="'Nueva Orden de Compra | ' . ($empresa->nombre ?? config('app.name'))">
    <div x-data="ordenCompraForm({
        sucursales: {{ Js::from($sucursales) }},
        almacenes: {{ Js::from($almacenes) }},
        proveedores: {{ Js::from($proveedores) }},
        catalogoProductos: {{ Js::from($productos) }},
        proveedorPreseleccionado: {{ Js::from(request()->query('proveedor_id')) }}
    })" class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6 space-y-6">

        {{-- ----------------------------------------------------------------- --}}
        {{-- ENCABEZADO DE PANTALLA Y EMPRESA ACTIVA                          --}}
        {{-- ----------------------------------------------------------------- --}}
        <header class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold tracking-tight text-white">
                        Crear Orden de Compra
                    </h1>

                    @if (isset($empresa))
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                            {{ $empresa->nombre }}
                        </span>
                    @endif
                </div>

                <p class="text-xs text-slate-400">
                    Captura los datos del proveedor y las partidas que serán solicitadas (costos con IVA incluido).
                </p>
            </div>

            <div>
                <a href="{{ route('compras.ordenes.index') }}"
                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold border border-slate-700 transition">
                    ← Volver al Listado
                </a>
            </div>
        </header>

        {{-- Mensajes de confirmación o errores de validación --}}
        @if (session('status'))
            <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl text-xs font-medium">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-2xl text-xs space-y-1">
                <p class="font-bold">Revisa los campos del formulario:</p>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('compras.ordenes.store') }}" class="space-y-6">
            @csrf

            {{-- ------------------------------------------------------------- --}}
            {{-- BLOQUE 1: ENCABEZADO DE LA ORDEN DE COMPRA                    --}}
            {{-- ------------------------------------------------------------- --}}
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
                {{-- Sucursal solicitante --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Sucursal *</label>
                    <select name="sucursal_id"
                            x-model.number="form.sucursal_id"
                            @change="sincronizarAlmacen()"
                            required
                            class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Seleccione sucursal...</option>
                        <template x-for="s in sucursales" :key="s.id">
                            <option :value="s.id" x-text="s.nombre"></option>
                        </template>
                    </select>
                </div>

                {{-- Almacén destino filtrado según la sucursal elegida --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Almacén Destino *</label>
                    <select name="almacen_id" x-model.number="form.almacen_id" required
                            class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Seleccione almacén...</option>
                        <template x-for="a in almacenesFiltrados" :key="a.id">
                            <option :value="a.id" x-text="a.nombre"></option>
                        </template>
                    </select>
                </div>

                {{-- Proveedor con acceso rápido para registrar uno nuevo si no existe --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-slate-300 font-medium">Proveedor *</label>

                        @if (isset($empresa) && \Illuminate\Support\Facades\Route::has('operacion.proveedores.create'))
                            <a href="{{ route('operacion.proveedores.create', ['empresa' => $empresa->id, 'retorno' => 'orden_compra']) }}"
                               title="Agregar un nuevo proveedor a {{ $empresa->nombre }}"
                               class="text-[11px] font-semibold text-indigo-400 hover:text-indigo-300 transition">
                                + Nuevo proveedor
                            </a>
                        @endif
                    </div>

                    <select name="proveedor_id" x-model.number="form.proveedor_id" required
                            class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Seleccione proveedor...</option>
                        <template x-for="p in proveedores" :key="p.id">
                            <option :value="p.id" x-text="`${p.razon_social || p.nombre} (${p.rfc || 'S/RFC'})`"></option>
                        </template>
                    </select>
                </div>

                {{-- Fecha de emisión --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Fecha Emisión *</label>
                    <input type="date" name="fecha_emision" value="{{ old('fecha_emision', date('Y-m-d')) }}" required
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Fecha esperada de entrega --}}
                <div class="md:col-span-2">
                    <label class="text-slate-300 block mb-1 font-medium">Fecha Esperada de Entrega</label>
                    <input type="date" name="fecha_entrega_esperada" value="{{ old('fecha_entrega_esperada') }}"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Notas / observaciones --}}
                <div class="md:col-span-2">
                    <label class="text-slate-300 block mb-1 font-medium">Notas / Observaciones</label>
                    <input type="text" name="notas" value="{{ old('notas') }}" placeholder="Instrucciones para el proveedor..."
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            {{-- ------------------------------------------------------------- --}}
            {{-- BLOQUE 2: PARTIDAS DE LA ORDEN Y DESGLOSE DE IVA INCLUIDO   --}}
            {{-- ------------------------------------------------------------- --}}
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider">Partidas del Pedido</h2>
                    <button type="button" @click="agregarPartida()"
                            class="px-3 py-1.5 bg-indigo-600/20 border border-indigo-500/30 hover:bg-indigo-600/30 text-indigo-300 rounded-lg text-xs font-medium transition">
                        + Agregar Producto
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-400 uppercase tracking-wider border-b border-slate-800">
                            <tr>
                                <th class="py-2 px-3 w-5/12">Artículo</th>
                                <th class="py-2 px-3 w-2/12">Cantidad</th>
                                <th class="py-2 px-3 w-2/12">Costo Unit. c/IVA ($)</th>
                                <th class="py-2 px-3 w-2/12 text-right">Importe c/IVA ($)</th>
                                <th class="py-2 px-3 w-1/12 text-center">Quitar</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-800/80">
                            <template x-for="(linea, index) in partidas" :key="index">
                                <tr>
                                    {{-- Selección de artículo del catálogo filtrado --}}
                                    <td class="py-2.5 px-3">
                                        <select :name="`detalles[${index}][producto_id]`"
                                                x-model.number="linea.producto_id"
                                                @change="actualizarCostoReferencia(index)"
                                                required
                                                class="w-full !bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white text-xs">
                                            <option value="">Seleccionar artículo...</option>
                                            <template x-for="prod in catalogoProductos" :key="prod.id">
                                                <option :value="prod.id" x-text="`[${prod.sku}] ${prod.nombre}`"></option>
                                            </template>
                                        </select>
                                    </td>

                                    {{-- Cantidad solicitada --}}
                                    <td class="py-2.5 px-3">
                                        <input type="number" step="0.0001" min="0.0001"
                                               :name="`detalles[${index}][cantidad]`"
                                               x-model.number="linea.cantidad"
                                               required
                                               class="w-full !bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-xs">
                                    </td>

                                    {{-- Costo unitario con IVA (16%) incluido --}}
                                    <td class="py-2.5 px-3">
                                        <input type="number" step="0.01" min="0"
                                               :name="`detalles[${index}][costo_unitario]`"
                                               x-model.number="linea.costo_unitario"
                                               required
                                               class="w-full !bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-xs">
                                    </td>

                                    {{-- Importe de la partida con IVA incluido --}}
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-200"
                                        x-text="`$${(Number(linea.cantidad || 0) * Number(linea.costo_unitario || 0)).toFixed(2)}`">
                                    </td>

                                    {{-- Botón para quitar partida --}}
                                    <td class="py-2.5 px-3 text-center">
                                        <button type="button" @click="quitarPartida(index)"
                                                class="text-rose-400 hover:text-rose-300 font-bold text-sm">
                                            &times;
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Resumen financiero con desglose de IVA (16%) incluido en el total --}}
                <div class="flex justify-end pt-4 border-t border-slate-800">
                    <div class="w-72 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Subtotal (sin IVA):</span>
                            <span class="font-mono text-white" x-text="`$${totales.subtotal.toFixed(2)}`"></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>IVA Desglosado (16%):</span>
                            <span class="font-mono text-white" x-text="`$${totales.iva.toFixed(2)}`"></span>
                        </div>
                        <div class="flex justify-between text-base font-bold text-white pt-2 border-t border-slate-800">
                            <span>Total Estimado (c/IVA):</span>
                            <span class="font-mono text-emerald-400 text-lg" x-text="`$${totales.total.toFixed(2)}`"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ------------------------------------------------------------- --}}
            {{-- BOTONES DE ACCIÓN                                             --}}
            {{-- ------------------------------------------------------------- --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('compras.ordenes.index') }}"
                   class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">
                    Cancelar
                </a>
                <button type="submit" :disabled="partidas.length === 0"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/20 transition">
                    Guardar Orden de Compra
                </button>
            </div>
        </form>
    </div>

    <script>
        /**
         * Componente Alpine.js para administrar sucursal, almacén,
         * proveedor, partidas y totales de una orden de compra.
         * El costo unitario ya incluye IVA (16%), por lo que subtotal e IVA
         * se desglosan hacia atrás a partir del total con IVA incluido.
         */
        function ordenCompraForm(config) {
            const sucursalInicial = config.sucursales.length > 0 ? config.sucursales[0].id : null;
            const almacenesIniciales = sucursalInicial
                ? config.almacenes.filter(a => a.sucursal_id === sucursalInicial)
                : config.almacenes;

            return {
                sucursales: config.sucursales,
                almacenes: config.almacenes,
                proveedores: config.proveedores,
                catalogoProductos: config.catalogoProductos,

                form: {
                    sucursal_id: sucursalInicial,
                    almacen_id: almacenesIniciales.length > 0 ? almacenesIniciales[0].id : null,
                    proveedor_id: config.proveedorPreseleccionado ? Number(config.proveedorPreseleccionado) : null
                },

                partidas: [
                    { producto_id: null, cantidad: 1, costo_unitario: 0 }
                ],

                get almacenesFiltrados() {
                    if (!this.form.sucursal_id) return this.almacenes;
                    return this.almacenes.filter(a => a.sucursal_id === this.form.sucursal_id);
                },

                sincronizarAlmacen() {
                    const disponibles = this.almacenesFiltrados;
                    const actualValido = disponibles.some(a => a.id === this.form.almacen_id);
                    if (!actualValido) {
                        this.form.almacen_id = disponibles.length > 0 ? disponibles[0].id : null;
                    }
                },

                agregarPartida() {
                    this.partidas.push({ producto_id: null, cantidad: 1, costo_unitario: 0 });
                },

                quitarPartida(index) {
                    if (this.partidas.length > 1) {
                        this.partidas.splice(index, 1);
                    }
                },

                actualizarCostoReferencia(index) {
                    const prodId = this.partidas[index].producto_id;
                    const prod = this.catalogoProductos.find(p => p.id === prodId);
                    if (prod) {
                        this.partidas[index].costo_unitario = Number(prod.costo_referencia || 0);
                    }
                },

                get totales() {
                    let totalConIva = 0;
                    this.partidas.forEach(p => {
                        totalConIva += (Number(p.cantidad || 0) * Number(p.costo_unitario || 0));
                    });

                    // Desglose hacia atrás considerando que el costo unitario ya incluye IVA del 16%
                    const subtotal = totalConIva / 1.16;
                    const iva = totalConIva - subtotal;

                    return {
                        subtotal: subtotal,
                        iva: iva,
                        total: totalConIva
                    };
                }
            };
        }
    </script>
</x-app-layout>
