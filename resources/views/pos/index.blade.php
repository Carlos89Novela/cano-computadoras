<x-app-layout>
    <div x-data="posTerminal({
        sucursal: {{ Js::from($sucursal ?? ['id' => 1, 'nombre' => 'Sucursal Principal']) }},
        almacen: {{ Js::from($almacen ?? ['id' => 1, 'nombre' => 'Almacén General']) }},
        turnoInicial: {{ Js::from($turnoActivo) }},
        routes: {
            catalogo: '{{ route('pos.catalogo') }}',
            aperturaCaja: '{{ route('pos.caja.apertura') }}',
            cierreCaja: '{{ route('pos.caja.cierre') }}',
            ventaStore: '{{ route('pos.ventas.store') }}',
            sincronizarOffline: '{{ route('pos.sincronizar.offline') }}',
            ultimasVentas: '{{ route('pos.ultimas-ventas') }}'
        },
        csrfToken: '{{ csrf_token() }}'
    })" x-init="initPos()" class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6">

        {{-- Encabezado de Estado y Conectividad --}}
        <header class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-600/20 text-indigo-400 rounded-xl border border-indigo-500/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-white flex items-center gap-2">
                        Punto de Venta
                        <span class="text-xs px-2.5 py-0.5 rounded-full border"
                              :class="isOnline ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20'">
                            <span class="inline-block w-1.5 h-1.5 rounded-full mr-1" :class="isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                            <span x-text="isOnline ? 'En línea' : 'Modo Desconectado (Offline)'"></span>
                        </span>
                    </h1>
                    <p class="text-xs text-slate-400">
                        Sucursal: <strong class="text-slate-200" x-text="sucursal?.nombre || 'Sucursal Principal'"></strong> |
                        Almacén: <strong class="text-slate-200" x-text="almacen?.nombre || 'Almacén Principal'"></strong>
                    </p>
                </div>
            </div>

            {{-- Controles de Caja y Cola Offline --}}
            <div class="flex items-center gap-3">
                <template x-if="offlineQueueCount > 0">
                    <button @click="sincronizarLoteOffline()" :disabled="!isOnline || isSyncing"
                            class="px-3 py-1.5 bg-amber-500/20 border border-amber-500/40 text-amber-300 rounded-lg text-xs font-medium flex items-center gap-2 hover:bg-amber-500/30 transition">
                        <svg class="w-4 h-4" :class="isSyncing ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span x-text="`${offlineQueueCount} ventas pendientes`"></span>
                    </button>
                </template>

                <template x-if="!turnoActivo">
                    <button @click="modalApertura = true" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold shadow transition">
                        + Abrir Turno de Caja
                    </button>
                </template>

                <template x-if="turnoActivo">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="abrirModalReimpresion()"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 shadow-sm transition">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Reimprimir Ticket
                        </button>

                        <button type="button"
                                @click="abrirModalFacturacion()"
                                class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Facturación CFDI
                        </button>

                        <button @click="modalCierre = true" class="px-4 py-2 bg-rose-600/20 border border-rose-500/30 hover:bg-rose-600/30 text-rose-300 rounded-lg text-sm font-medium transition">
                            Cerrar Turno #<span x-text="turnoActivo.id"></span>
                        </button>

                        @can('pos.devoluciones')
                            <button type="button"
                                    @click="modalDevolucion = true"
                                    class="px-3 py-1.5 bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                                Devolución
                            </button>
                        @endcan
                    </div>
                </template>
            </div>
        </header>

        {{-- Alerta de Caja Cerrada --}}
        <template x-if="!turnoActivo">
            <div class="mt-6 p-8 bg-slate-900 border border-slate-800 rounded-2xl text-center max-w-lg mx-auto">
                <div class="w-12 h-12 bg-amber-500/10 text-amber-400 rounded-full flex items-center justify-center mx-auto mb-3 border border-amber-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m11-5a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-white mb-1">Caja Cerrada</h2>
                <p class="text-sm text-slate-400 mb-5">Debes abrir un turno de caja con tu fondo inicial en efectivo para comenzar a registrar ventas.</p>
                <button @click="modalApertura = true" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-indigo-600/20 transition">
                    Abrir Caja Ahora
                </button>
            </div>
        </template>

        {{-- Terminal Operativa --}}
        <template x-if="turnoActivo">
            <div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- Catálogo y Búsqueda --}}
                <div class="lg:col-span-7 space-y-4">
                    <div class="relative">
                        <input type="text" x-model="searchQuery" @keydown.enter.prevent="buscarYAgregarDirecto()"
                               placeholder="Escanear código de barras o buscar por nombre / SKU..."
                               class="w-full !bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 pl-11 text-sm !text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <div class="absolute left-3.5 top-3.5 text-slate-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[620px] overflow-y-auto pr-1">
                        <template x-for="prod in productosFiltrados" :key="prod.id">
                            <div @click="agregarAlCarrito(prod)"
                                 class="p-3.5 bg-slate-900 hover:bg-slate-800/80 border border-slate-800 hover:border-slate-700 rounded-xl cursor-pointer transition flex flex-col justify-between group">
                                <div>
                                    <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 group-hover:text-indigo-400" x-text="prod.sku"></span>
                                    <h3 class="text-xs font-semibold text-white mt-1.5 line-clamp-2" x-text="prod.nombre"></h3>
                                </div>
                                <div class="mt-3 pt-2 border-t border-slate-800/80 flex items-center justify-between">
                                    <span class="text-sm font-bold text-emerald-400" x-text="`$${Number(prod.precio).toFixed(2)}`"></span>
                                    <span class="text-[11px]" :class="prod.stock_disponible > 0 ? 'text-slate-400' : 'text-rose-400 font-semibold'"
                                          x-text="`Stock: ${prod.stock_disponible}`"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Ticket y Carrito --}}
                <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Ticket de Venta</h2>
                        <button @click="vaciarCarrito()" class="text-xs text-rose-400 hover:underline">Vaciar</button>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <label class="text-slate-400 block mb-1">Cliente</label>
                            <input type="text" x-model="cliente.nombre" class="w-full !bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-slate-400 block mb-1">RFC Fiscal</label>
                            <input type="text" x-model="cliente.rfc" class="w-full !bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white uppercase font-mono focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="space-y-2 max-h-[260px] overflow-y-auto pr-1">
                        <template x-for="(item, index) in carrito" :key="item.producto_id">
                            <div class="p-2.5 bg-slate-950/70 border border-slate-800/80 rounded-xl flex items-center justify-between gap-2 text-xs">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-white truncate" x-text="item.nombre"></div>
                                    <div class="text-[11px] text-slate-400" x-text="`$${item.precio_unitario.toFixed(2)} c/u`"></div>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button @click="decrementarCantidad(index)" class="w-6 h-6 rounded bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center">-</button>
                                    <span class="w-8 text-center font-bold text-white" x-text="item.cantidad"></span>
                                    <button @click="incrementarCantidad(index)" class="w-6 h-6 rounded bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center">+</button>
                                </div>
                                <div class="text-right w-16">
                                    <div class="font-bold text-emerald-400" x-text="`$${(item.cantidad * item.precio_unitario).toFixed(2)}`"></div>
                                </div>
                                <button @click="removerDelCarrito(index)" class="text-rose-400 hover:text-rose-300 p-1">&times;</button>
                            </div>
                        </template>

                        <template x-if="carrito.length === 0">
                            <div class="py-8 text-center text-slate-500 text-xs">
                                Escanea o selecciona artículos para comenzar la venta.
                            </div>
                        </template>
                    </div>

                    <div class="pt-3 border-t border-slate-800 space-y-1.5 text-xs text-slate-400">
                        <div class="flex justify-between">
                            <span>Subtotal (sin IVA):</span>
                            <span class="text-white font-mono" x-text="`$${totales.subtotal.toFixed(2)}`"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>IVA Trasladado (16%):</span>
                            <span class="text-white font-mono" x-text="`$${totales.iva.toFixed(2)}`"></span>
                        </div>
                        <div class="flex justify-between text-base font-bold text-white pt-2 border-t border-slate-800">
                            <span>Total a Pagar:</span>
                            <span class="text-emerald-400 font-mono text-lg" x-text="`$${totales.total.toFixed(2)}`"></span>
                        </div>
                    </div>

                    <div class="pt-2 space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" @click="formaPagoSat = '01'"
                                    :class="formaPagoSat === '01' ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-950 text-slate-400 border-slate-800'"
                                    class="py-2 text-xs font-semibold rounded-lg border transition">Efectivo</button>
                            <button type="button" @click="formaPagoSat = '04'"
                                    :class="formaPagoSat === '04' ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-950 text-slate-400 border-slate-800'"
                                    class="py-2 text-xs font-semibold rounded-lg border transition">Tarjeta</button>
                            <button type="button" @click="formaPagoSat = '03'"
                                    :class="formaPagoSat === '03' ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-950 text-slate-400 border-slate-800'"
                                    class="py-2 text-xs font-semibold rounded-lg border transition">Transferencia</button>
                        </div>

                        <template x-if="formaPagoSat === '01'">
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <label class="text-slate-400 block mb-1">Monto Recibido</label>
                                    <input type="number" step="0.50" x-model.number="montoRecibido"
                                           class="w-full !bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-sm focus:outline-none focus:border-indigo-500">
                                </div>
                                <div>
                                    <label class="text-slate-400 block mb-1">Cambio</label>
                                    <div class="px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-emerald-400 font-mono text-sm font-bold"
                                         x-text="`$${Math.max(0, montoRecibido - totales.total).toFixed(2)}`"></div>
                                </div>
                            </div>
                        </template>

                        <button @click="procesarVenta()" :disabled="carrito.length === 0 || isProcessing"
                                class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-600/20 transition flex items-center justify-center gap-2">
                            <span x-show="!isProcessing">Cobrar e Imprimir Ticket</span>
                            <span x-show="isProcessing">Procesando...</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Modal: Apertura de Turno --}}
        <div x-show="modalApertura" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-sm w-full space-y-4 shadow-2xl">
                <h3 class="text-base font-bold text-white">Apertura de Turno de Caja</h3>
                <div>
                    <label class="text-xs text-slate-300 block mb-1 font-medium">Fondo Inicial en Efectivo ($)</label>
                    <input type="number" step="10" x-model.number="formApertura.monto_inicial"
                           style="background-color: #020617 !important; color: #ffffff !important; border-color: #334155 !important;"
                           class="w-full rounded-xl px-3 py-2 font-mono text-base focus:outline-none">
                </div>
                <div>
                    <label class="text-xs text-slate-300 block mb-1 font-medium">Observaciones</label>
                    <textarea x-model="formApertura.observaciones" rows="2"
                              placeholder="Notas opcionales de apertura..."
                              style="background-color: #020617 !important; color: #ffffff !important; border-color: #334155 !important;"
                              class="w-full rounded-xl px-3 py-2 text-xs focus:outline-none"></textarea>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" @click="modalApertura = false" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold transition">Cancelar</button>
                    <button type="button" @click="abrirTurnoCaja()" class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-600/30 transition">Abrir Caja</button>
                </div>
            </div>
        </div>

        {{-- Modal: Cierre de Turno --}}
        <div x-show="modalCierre" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-sm w-full space-y-4 shadow-2xl">
                <h3 class="text-base font-bold text-white">Corte y Cierre de Turno</h3>
                <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs space-y-1">
                    <div class="flex justify-between text-slate-400">
                        <span>Fondo Inicial:</span>
                        <span class="text-white font-mono" x-text="`$${Number(turnoActivo?.monto_inicial || 0).toFixed(2)}`"></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Ventas Efectivo:</span>
                        <span class="text-white font-mono" x-text="`$${Number(turnoActivo?.total_ventas_efectivo || 0).toFixed(2)}`"></span>
                    </div>
                    <div class="flex justify-between font-bold text-white pt-1 border-t border-slate-800">
                        <span>Efectivo Esperado en Caja:</span>
                        <span class="text-indigo-400 font-mono text-sm" x-text="`$${Number(turnoActivo?.monto_esperado_efectivo || 0).toFixed(2)}`"></span>
                    </div>
                </div>
                <div>
                    <label class="text-xs text-slate-300 block mb-1 font-medium">Efectivo Real Contado en Caja ($)</label>
                    <input type="number" step="0.50" x-model.number="formCierre.monto_real_efectivo"
                           style="background-color: #020617 !important; color: #ffffff !important; border-color: #334155 !important;"
                           class="w-full rounded-xl px-3 py-2 font-mono text-base focus:outline-none">
                </div>
                <div>
                    <label class="text-xs text-slate-300 block mb-1 font-medium">Notas de Cierre</label>
                    <textarea x-model="formCierre.observaciones" rows="2"
                              placeholder="Observaciones de arqueo..."
                              style="background-color: #020617 !important; color: #ffffff !important; border-color: #334155 !important;"
                              class="w-full rounded-xl px-3 py-2 text-xs focus:outline-none"></textarea>
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" @click="modalCierre = false" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold transition">Volver</button>
                    <button type="button" @click="cerrarTurnoCaja()" class="flex-1 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-rose-600/30 transition">Confirmar Cierre</button>
                </div>
            </div>
        </div>

        {{-- Modal de Reimpresión de Tickets --}}
        <div x-show="modalReimpresion" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalReimpresion = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Reimpresión de Ventas Recientes
                    </h3>
                    <button @click="modalReimpresion = false" class="text-slate-400 hover:text-white text-lg font-bold leading-none">&times;</button>
                </div>

                <div class="mt-4 max-h-80 overflow-y-auto space-y-2 pr-1">
                    <template x-if="cargandoVentas">
                        <p class="text-center text-xs text-slate-400 py-4">Cargando ventas del turno...</p>
                    </template>
                    <template x-if="!cargandoVentas && ventasRecientes.length === 0">
                        <p class="text-center text-xs text-slate-400 py-4">No hay ventas registradas en este turno.</p>
                    </template>
                    <template x-for="v in ventasRecientes" :key="v.id">
                        <div class="flex items-center justify-between p-3 bg-slate-950 border border-slate-800 rounded-xl hover:border-slate-700 transition">
                            <div>
                                <div class="font-bold text-emerald-400 text-xs" x-text="v.folio"></div>
                                <div class="text-[11px] text-slate-300" x-text="v.cliente_nombre"></div>
                                <div class="text-[10px] text-slate-500" x-text="formatearFecha(v.created_at)"></div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-white font-mono" x-text="'$' + Number(v.total).toFixed(2)"></span>
                                <button type="button" @click="lanzarImpresion(v.uuid || v.id)"
                                        class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold flex items-center gap-1 shadow">
                                    Imprimir
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-800 flex justify-end">
                    <button type="button" @click="modalReimpresion = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-xl">Cerrar</button>
                </div>
            </div>
        </div>

        {{-- Modal de Facturación CFDI 4.0 --}}
        <div x-show="modalFacturacion" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalFacturacion = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-4">
                <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                        Facturación Electrónica SAT (CFDI 4.0)
                    </h3>
                    <button @click="modalFacturacion = false" class="text-slate-400 hover:text-white text-lg font-bold leading-none">&times;</button>
                </div>

                <template x-if="mensajeFactura">
                    <div class="p-3 rounded-xl text-xs font-medium"
                         :class="mensajeErrorFactura ? 'bg-rose-500/10 border border-rose-500/20 text-rose-300' : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-300'"
                         x-text="mensajeFactura"></div>
                </template>

                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-slate-400">Ventas completadas del turno:</span>
                        <button type="button" @click="cargarVentasParaFacturar()" class="text-[11px] text-indigo-400 hover:underline">
                            🔄 Actualizar listado
                        </button>
                    </div>

                    <div class="max-h-80 overflow-y-auto space-y-2 pr-1">
                        <template x-if="cargandoFacturacion">
                            <p class="text-center text-xs text-slate-500 py-6">Consultando ventas...</p>
                        </template>

                        <template x-if="!cargandoFacturacion && ventasFacturables.length === 0">
                            <p class="text-center text-xs text-slate-500 py-6">No se encontraron ventas para facturar en este turno.</p>
                        </template>

                        <template x-for="v in ventasFacturables" :key="v.id">
                            <div class="p-3 bg-slate-950 border border-slate-800 rounded-xl flex items-center justify-between gap-3 hover:border-slate-700 transition">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs text-indigo-400 font-mono" x-text="v.folio"></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border"
                                              :class="{
                                                  'bg-emerald-500/10 text-emerald-400 border-emerald-500/20': v.estado_facturacion === 'facturada',
                                                  'bg-amber-500/10 text-amber-400 border-amber-500/20': v.estado_facturacion === 'pendiente',
                                                  'bg-slate-800 text-slate-400 border-slate-700': v.estado_facturacion === 'no_facturada'
                                              }"
                                              x-text="v.estado_facturacion === 'facturada' ? 'Timbrada' : (v.estado_facturacion === 'pendiente' ? 'Pendiente PAC' : 'Sin timbrar')">
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-300 mt-0.5" x-text="v.cliente_nombre"></div>
                                    <div class="text-[10px] text-slate-500 font-mono" x-text="'RFC: ' + (v.cliente_rfc || 'XAXX010101000')"></div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-white font-mono mr-2" x-text="'$' + Number(v.total).toFixed(2)"></span>

                                    <template x-if="v.estado_facturacion === 'no_facturada' || v.estado_facturacion === 'pendiente'">
                                        <button type="button" @click="timbrarVentaDirecta(v)" :disabled="timbrandoId === v.id"
                                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 disabled:bg-indigo-900 text-white rounded-lg text-xs font-semibold transition flex items-center gap-1 shadow">
                                            <span x-show="timbrandoId !== v.id">⚡ Timbrar CFDI</span>
                                            <span x-show="timbrandoId === v.id">Timbrando...</span>
                                        </button>
                                    </template>

                                    <template x-if="v.estado_facturacion === 'facturada'">
                                        <div class="flex items-center gap-1">
                                            <a :href="'/operacion/facturacion/ventas/' + v.id + '/descargar-xml'" target="_blank"
                                               class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-xs font-mono font-semibold transition">XML</a>
                                            <a :href="'/operacion/facturacion/ventas/' + v.id + '/descargar-pdf'" target="_blank"
                                               class="px-2 py-1 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 rounded text-xs font-semibold transition">PDF</a>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800 flex justify-between items-center">
                    <button type="button" @click="procesarLoteDesdePos()" class="text-xs text-amber-400 hover:text-amber-300 font-semibold flex items-center gap-1">
                        ⚡ Forzar procesamiento de pendientes en lote
                    </button>
                    <button type="button" @click="modalFacturacion = false" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-xl transition">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Script Reactivo Alpine.js --}}
    <script>
        function posTerminal(config) {
            return {
                sucursal: config.sucursal || { id: 1, nombre: 'Sucursal Principal' },
                almacen: config.almacen || { id: 1, nombre: 'Almacén Principal' },
                turnoActivo: config.turnoInicial || null,
                routes: config.routes,
                csrf: config.csrfToken,

                isOnline: navigator.onLine,
                isSyncing: false,
                isProcessing: false,
                offlineQueueCount: 0,

                modalApertura: false,
                modalCierre: false,
                formApertura: { monto_inicial: 1000, observaciones: '' },
                formCierre: { monto_real_efectivo: 0, observaciones: '' },

                searchQuery: '',
                productos: [],
                carrito: [],
                cliente: { nombre: 'Público General', rfc: 'XAXX010101000' },
                formaPagoSat: '01',
                montoRecibido: 0,

                modalReimpresion: false,
                cargandoVentas: false,
                ventasRecientes: [],

                modalDevolucion: false,
                formDevolucion: { venta_folio: '', motivo: '', detalles: [] },
                devolucionEnProceso: false,

                modalFacturacion: false,
                cargandoFacturacion: false,
                ventasFacturables: [],
                timbrandoId: null,
                mensajeFactura: '',
                mensajeErrorFactura: false,

                db: null,

                async initPos() {
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        this.sincronizarLoteOffline();
                    });
                    window.addEventListener('offline', () => {
                        this.isOnline = false;
                    });

                    try {
                        await this.initIndexedDB();
                    } catch (err) {
                        console.warn('IndexedDB no inicializado:', err);
                    }

                    await this.cargarCatalogo();
                    await this.actualizarContadorOffline();
                },

                async initIndexedDB() {
                    return new Promise((resolve, reject) => {
                        const request = indexedDB.open('POS_Offline_DB', 1);
                        request.onupgradeneeded = (e) => {
                            const db = e.target.result;
                            if (!db.objectStoreNames.contains('catalogo')) {
                                db.createObjectStore('catalogo', { keyPath: 'id' });
                            }
                            if (!db.objectStoreNames.contains('ventas_pendientes')) {
                                db.createObjectStore('ventas_pendientes', { keyPath: 'uuid' });
                            }
                        };
                        request.onsuccess = (e) => {
                            this.db = e.target.result;
                            resolve();
                        };
                        request.onerror = (e) => reject(e);
                    });
                },

                async cargarCatalogo() {
                    const almacenId = this.almacen?.id || 1;

                    if (this.isOnline) {
                        try {
                            const res = await fetch(`${this.routes.catalogo}?almacen_id=${almacenId}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (res.ok) {
                                const data = await res.json();
                                if (Array.isArray(data) && data.length > 0) {
                                    this.productos = data;

                                    if (this.db) {
                                        const tx = this.db.transaction('catalogo', 'readwrite');
                                        const store = tx.objectStore('catalogo');
                                        store.clear();
                                        data.forEach(p => store.put(p));
                                    }
                                    return;
                                }
                            }
                        } catch (e) {
                            console.warn('Falla conexión catálogo online, usando local:', e);
                        }
                    }

                    if (this.db) {
                        const tx = this.db.transaction('catalogo', 'readonly');
                        const store = tx.objectStore('catalogo');
                        const req = store.getAll();
                        req.onsuccess = () => {
                            if (req.result && req.result.length > 0) {
                                this.productos = req.result;
                            }
                        };
                    }
                },

                async abrirModalReimpresion() {
                    this.modalReimpresion = true;
                    this.cargandoVentas = true;
                    try {
                        const sucursalId = this.sucursal?.id || 1;
                        const res = await fetch(`${this.routes.ultimasVentas}?sucursal_id=${sucursalId}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            this.ventasRecientes = await res.json();
                        }
                    } catch (e) {
                        console.error('Error al cargar ventas recientes:', e);
                    } finally {
                        this.cargandoVentas = false;
                    }
                },

                lanzarImpresion(uuid) {
                    const url = `/pos/ventas/${uuid}/ticket`;
                    window.open(url, 'Ticket POS', 'width=380,height=600,top=100,left=100,toolbar=no,menubar=no,scrollbars=yes');
                },

                formatearFecha(fechaStr) {
                    if (!fechaStr) return '';
                    const f = new Date(fechaStr);
                    return f.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                },

                get productosFiltrados() {
                    if (!this.searchQuery.trim()) return this.productos.slice(0, 30);
                    const q = this.searchQuery.toLowerCase();
                    return this.productos.filter(p =>
                        (p.nombre && p.nombre.toLowerCase().includes(q)) ||
                        (p.sku && p.sku.toLowerCase().includes(q)) ||
                        (p.codigo_barras && p.codigo_barras.includes(q))
                    );
                },

                buscarYAgregarDirecto() {
                    const filtrados = this.productosFiltrados;
                    if (filtrados.length > 0) {
                        this.agregarAlCarrito(filtrados[0]);
                        this.searchQuery = '';
                    }
                },

                agregarAlCarrito(producto) {
                    const existente = this.carrito.find(item => item.producto_id === producto.id);
                    if (existente) {
                        existente.cantidad += 1;
                    } else {
                        this.carrito.push({
                            producto_id: producto.id,
                            sku: producto.sku,
                            nombre: producto.nombre,
                            precio_unitario: Number(producto.precio),
                            cantidad: 1,
                            tasa_iva: 0.1600
                        });
                    }
                },

                incrementarCantidad(index) {
                    this.carrito[index].cantidad += 1;
                },

                decrementarCantidad(index) {
                    if (this.carrito[index].cantidad > 1) {
                        this.carrito[index].cantidad -= 1;
                    } else {
                        this.removerDelCarrito(index);
                    }
                },

                removerDelCarrito(index) {
                    this.carrito.splice(index, 1);
                },

                vaciarCarrito() {
                    this.carrito = [];
                    this.montoRecibido = 0;
                },

                get totales() {
                    let totalConIva = 0;
                    this.carrito.forEach(item => {
                        totalConIva += (item.cantidad * item.precio_unitario);
                    });
                    const subtotal = totalConIva / 1.16;
                    const iva = totalConIva - subtotal;
                    return { subtotal, iva, total: totalConIva };
                },

                generarUUID() {
                    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                        const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                        return v.toString(16);
                    });
                },

                async procesarVenta() {
                    if (this.carrito.length === 0) return;
                    this.isProcessing = true;

                    const uuidVenta = this.generarUUID();
                    const totalVenta = Number(this.totales.total.toFixed(2));
                    const recibido = this.formaPagoSat === '01' ? Math.max(totalVenta, Number(this.montoRecibido)) : totalVenta;
                    const cambio = this.formaPagoSat === '01' ? Math.max(0, recibido - totalVenta) : 0;

                    const payloadVenta = {
                        uuid: uuidVenta,
                        sucursal_id: this.sucursal?.id || 1,
                        almacen_id: this.almacen?.id || 1,
                        caja_turno_id: this.turnoActivo?.id || null,
                        cliente_nombre: this.cliente.nombre,
                        cliente_rfc: this.cliente.rfc,
                        forma_pago_sat: this.formaPagoSat,
                        lineas: this.carrito.map(item => ({
                            producto_id: item.producto_id,
                            cantidad: item.cantidad,
                            precio_unitario: item.precio_unitario,
                            tasa_iva: item.tasa_iva
                        })),
                        pagos: [{
                            forma_pago_sat: this.formaPagoSat,
                            monto: totalVenta,
                            monto_recibido: recibido,
                            cambio_devuelto: cambio
                        }]
                    };

                    if (this.isOnline) {
                        try {
                            const res = await fetch(this.routes.ventaStore, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(payloadVenta)
                            });

                            const data = await res.json();
                            if (res.ok) {
                                const idTicket = data.venta?.uuid || data.venta_id || uuidVenta;
                                this.lanzarImpresion(idTicket);
                                this.vaciarCarrito();
                                this.isProcessing = false;
                                return;
                            }
                        } catch (e) {
                            console.warn('Error en conexión online, pasando a offline.');
                        }
                    }

                    await this.guardarVentaOffline(payloadVenta);
                    alert(`Venta guardada en modo desconectado. Folio/UUID: ${uuidVenta}.`);
                    this.vaciarCarrito();
                    this.isProcessing = false;
                },

                async guardarVentaOffline(payload) {
                    return new Promise((resolve) => {
                        const tx = this.db.transaction('ventas_pendientes', 'readwrite');
                        const store = tx.objectStore('ventas_pendientes');
                        store.put(payload);
                        tx.oncomplete = () => {
                            this.actualizarContadorOffline();
                            resolve();
                        };
                    });
                },

                async actualizarContadorOffline() {
                    if (!this.db) return;
                    const tx = this.db.transaction('ventas_pendientes', 'readonly');
                    const store = tx.objectStore('ventas_pendientes');
                    const req = store.count();
                    req.onsuccess = () => {
                        this.offlineQueueCount = req.result;
                    };
                },

                async sincronizarLoteOffline() {
                    if (!this.isOnline || this.isSyncing || !this.db) return;
                    this.isSyncing = true;

                    const tx = this.db.transaction('ventas_pendientes', 'readonly');
                    const store = tx.objectStore('ventas_pendientes');
                    const req = store.getAll();

                    req.onsuccess = async () => {
                        const ventasPendientes = req.result || [];
                        if (ventasPendientes.length === 0) {
                            this.isSyncing = false;
                            return;
                        }

                        try {
                            const res = await fetch(this.routes.sincronizarOffline, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ ventas: ventasPendientes })
                            });

                            if (res.ok) {
                                const data = await res.json();
                                const txClean = this.db.transaction('ventas_pendientes', 'readwrite');
                                const storeClean = txClean.objectStore('ventas_pendientes');
                                data.sincronizadas.forEach(s => storeClean.delete(s.uuid));
                                txClean.oncomplete = () => {
                                    this.actualizarContadorOffline();
                                    this.isSyncing = false;
                                };
                            }
                        } catch (e) {
                            console.error('Error sincronizando offline:', e);
                            this.isSyncing = false;
                        }
                    };
                },

                async abrirTurnoCaja() {
                    const res = await fetch(this.routes.aperturaCaja, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            sucursal_id: this.sucursal?.id || 1,
                            monto_inicial: this.formApertura.monto_inicial,
                            observaciones: this.formApertura.observaciones
                        })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        this.turnoActivo = data.turno;
                        this.modalApertura = false;
                    } else {
                        alert(data.message || 'Error abriendo caja');
                    }
                },

                async cerrarTurnoCaja() {
                    if (!this.turnoActivo) return;

                    const res = await fetch(this.routes.cierreCaja, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            caja_turno_id: this.turnoActivo.id,
                            monto_real_efectivo: this.formCierre.monto_real_efectivo,
                            observaciones: this.formCierre.observaciones
                        })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        alert(`Turno cerrado. Diferencia de efectivo: $${data.turno.diferencia_efectivo}`);
                        this.turnoActivo = null;
                        this.modalCierre = false;
                    } else {
                        alert(data.message || 'Error cerrando caja');
                    }
                },

                async abrirModalFacturacion() {
                    this.modalFacturacion = true;
                    this.mensajeFactura = '';
                    await this.cargarVentasParaFacturar();
                },

                async cargarVentasParaFacturar() {
                    this.cargandoFacturacion = true;
                    try {
                        const sucursalId = this.sucursal?.id || 1;
                        const res = await fetch(`/pos/ultimas-ventas?sucursal_id=${sucursalId}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            this.ventasFacturables = await res.json();
                        }
                    } catch (e) {
                        console.error('Error al cargar ventas para facturar:', e);
                    } finally {
                        this.cargandoFacturacion = false;
                    }
                },

                async timbrarVentaDirecta(venta) {
                    this.timbrandoId = venta.id;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;

                    try {
                        const res = await fetch(`/operacion/facturacion/ventas/${venta.id}/timbrar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf
                            }
                        });

                        const data = await res.json();

                        if (res.ok || res.status === 202) {
                            this.mensajeFactura = data.message || 'Procesado exitosamente.';
                            this.mensajeErrorFactura = false;
                            await this.cargarVentasParaFacturar();
                        } else {
                            this.mensajeFactura = data.message || 'Error al timbrar el comprobante fiscal.';
                            this.mensajeErrorFactura = true;
                        }
                    } catch (e) {
                        this.mensajeFactura = 'Error de conexión con el servicio de timbrado.';
                        this.mensajeErrorFactura = true;
                    } finally {
                        this.timbrandoId = null;
                    }
                },

                async procesarLoteDesdePos() {
                    this.mensajeFactura = 'Procesando facturas pendientes en lote...';
                    this.mensajeErrorFactura = false;
                    try {
                        const res = await fetch('/operacion/facturacion/lote-pendientes', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf
                            }
                        });
                        const data = await res.json();
                        this.mensajeFactura = data.message || 'Lote procesado.';
                        await this.cargarVentasParaFacturar();
                    } catch (e) {
                        this.mensajeFactura = 'Error al procesar el lote de pendientes.';
                        this.mensajeErrorFactura = true;
                    }
                }
            };
        }
    </script>
</x-app-layout>
