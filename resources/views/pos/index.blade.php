<x-app-layout>
    <div x-data="posTerminal({
        sucursal: {{ Js::from($sucursal ?? ['id' => 1, 'nombre' => 'Sucursal Principal']) }},
        almacen: {{ Js::from($almacen ?? ['id' => 1, 'nombre' => 'Almacén General']) }},
        turnoInicial: {{ Js::from($turnoActivo) }},
        areasIniciales: {{ Js::from($areasMesas ?? []) }},
        routes: {
            catalogo: '{{ route('pos.catalogo') }}',
            aperturaCaja: '{{ route('pos.caja.apertura') }}',
            cierreCaja: '{{ route('pos.caja.cierre') }}',
            ventaStore: '{{ route('pos.ventas.store') }}',
            sincronizarOffline: '{{ route('pos.sincronizar.offline') }}',
            ultimasVentas: '{{ route('pos.ultimas-ventas') }}',
            comandasAbiertas: '{{ url('/pos/restaurante/comandas-abiertas') }}'
        },
        csrfToken: '{{ csrf_token() }}'
    })" x-init="initPos()" class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6">

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 1: ENCABEZADO SUPERIOR, CONECTIVIDAD Y ACCIONES DE CAJA           --}}
        {{-- ========================================================================= --}}
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

            {{-- Controles Superiores --}}
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
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
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button"
                                @click="abrirModalMesasRestaurante()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 text-xs font-bold rounded-lg border border-amber-500/30 transition relative cursor-pointer">
                            <span>🪑 Mesas Abiertas</span>
                            <span class="px-1.5 py-0.2 bg-amber-500 text-slate-950 rounded-full font-black text-[10px]"
                                  :class="hayCuentasPedidas ? 'animate-bounce bg-rose-500 text-white' : ''"
                                  x-text="mesasAbiertas.length">0</span>
                        </button>

                        <button type="button"
                                @click="abrirModalReimpresion()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Reimprimir Ticket
                        </button>

                        <button type="button"
                                @click="abrirModalFacturacion()"
                                class="px-3 py-2 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Facturación CFDI
                        </button>

                        <button type="button" @click="abrirModalCorteParcial()"
                                class="px-3 py-2 bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30 rounded-lg text-xs font-bold transition cursor-pointer">
                            📊 Corte Parcial (X) / Retiros
                        </button>

                        <button @click="modalCierre = true" class="px-3 py-2 bg-rose-600/20 border border-rose-500/30 hover:bg-rose-600/30 text-rose-300 rounded-lg text-xs font-medium transition cursor-pointer">
                            Cerrar Turno #<span x-text="turnoActivo.id"></span>
                        </button>

                        @can('pos.devoluciones')
                            <button type="button"
                                    @click="modalDevolucion = true"
                                    class="px-3 py-2 bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition">
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

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 2: ALERTA DE CAJA CERRADA                                         --}}
        {{-- ========================================================================= --}}
        <template x-if="!turnoActivo">
            <div class="mt-8 p-8 bg-slate-900 border border-slate-800 rounded-2xl text-center max-w-lg mx-auto">
                <div class="w-12 h-12 bg-amber-500/10 text-amber-400 rounded-full flex items-center justify-center mx-auto mb-3 border border-amber-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m11-5a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-white mb-1">Caja Cerrada</h2>
                <p class="text-sm text-slate-400 mb-5">Debes abrir un turno de caja con tu fondo inicial en efectivo para comenzar a registrar ventas o cobrar comandas.</p>
                <button @click="modalApertura = true" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-indigo-600/20 transition">
                    Abrir Caja Ahora
                </button>
            </div>
        </template>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 3: TERMINAL OPERATIVA (CATÁLOGO / PLANO A IZQUIERDA, TICKET DER.) --}}
        {{-- ========================================================================= --}}
        <template x-if="turnoActivo">
            <div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- Columna Izquierda: Condicionada por Giro Comercial --}}
                <div class="lg:col-span-7 space-y-3">

                    @if ($esRestaurante ?? true)
                        {{-- MODO RESTAURANTE: PLANO VISUAL INTERACTIVO DE MESAS --}}
                        <div class="p-3 bg-slate-900 border border-slate-800 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-md">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">🗺️</span>
                                <h2 class="text-sm font-bold text-white tracking-wide uppercase">Plano de Mesas</h2>
                                <div class="flex items-center gap-1.5 ml-2">
                                    <template x-for="area in areas" :key="area.id">
                                        <button type="button" @click="areaSeleccionadaId = area.id"
                                                :class="areaSeleccionadaId === area.id ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-950 text-slate-400 hover:text-white border border-slate-800'"
                                                class="px-2.5 py-1 rounded-lg text-xs transition cursor-pointer"
                                                x-text="area.nombre"></button>
                                    </template>
                                </div>
                            </div>

                            {{-- Leyenda de Estados --}}
                            <div class="flex items-center gap-3 text-[10px] text-slate-400">
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-slate-700"></span> Libre</span>
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Ocupada</span>
                                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse"></span> Precuenta Pedida</span>
                            </div>
                        </div>

                        {{-- Lienzo Gráfico 2D de Piso --}}
                        <div class="relative w-full h-[580px] bg-slate-950 border-2 border-slate-800/80 rounded-2xl overflow-hidden shadow-inner p-2 select-none"
                             style="background-image: radial-gradient(#1e293b 1px, transparent 1px); background-size: 24px 24px;">

                            <template x-if="!mesasAreaActual || mesasAreaActual.length === 0">
                                <div class="h-full flex flex-col items-center justify-center text-slate-500 text-xs">
                                    <span class="text-3xl mb-1">🪑</span>
                                    <span>No hay mesas cargadas para esta área.</span>
                                </div>
                            </template>

                            {{-- Renderizado de Mesas Físicas --}}
                            <template x-for="mesa in mesasAreaActual" :key="mesa.id">
                                <div @click="seleccionarMesaPlano(mesa)"
                                     :style="`left: ${mesa.posicion_x}%; top: ${mesa.posicion_y}%;`"
                                     class="absolute -translate-x-1/2 -translate-y-1/2 p-3 flex flex-col items-center justify-center cursor-pointer transition-all duration-300 transform active:scale-95 shadow-xl border-2"
                                     :class="obtenerEstiloMesaPlano(mesa)">

                                    <div class="text-base mb-0.5">
                                        <span x-show="mesaTieneCuentaPedida(mesa)">🔔</span>
                                        <span x-show="!mesaTieneCuentaPedida(mesa) && mesaTieneConsumo(mesa)">🍽️</span>
                                        <span x-show="!mesaTieneConsumo(mesa)">🪑</span>
                                    </div>

                                    <div class="font-black text-xs text-white tracking-wide" x-text="mesa.numero_o_nombre"></div>

                                    <template x-if="mesaTieneConsumo(mesa)">
                                        <div class="mt-1 px-1.5 py-0.2 rounded font-mono font-black text-[10px] bg-black/60 text-emerald-400 border border-emerald-500/30"
                                             x-text="'$' + obtenerTotalConsumoMesa(mesa).toFixed(2)"></div>
                                    </template>

                                    <template x-if="mesaTieneCuentaPedida(mesa)">
                                        <span class="absolute -top-2 -right-2 bg-cyan-400 text-slate-950 font-black text-[9px] px-1.5 py-0.2 rounded-full shadow-lg animate-bounce">
                                            CUENTA
                                        </span>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Barra de Acciones para la Mesa Seleccionada --}}
                        <div x-show="mesaSeleccionadaActiva" x-cloak
                             class="p-3 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between text-xs transition">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-white text-sm" x-text="mesaSeleccionadaActiva?.numero_o_nombre"></span>
                                <span class="text-slate-400" x-text="mesaTieneConsumo(mesaSeleccionadaActiva) ? 'Consumo activo' : 'Mesa disponible'"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <template x-if="mesaTieneConsumo(mesaSeleccionadaActiva)">
                                    <div class="flex items-center gap-2">
                                        <a :href="`/pos/restaurante/mesa/${encodeURIComponent(mesaSeleccionadaActiva.numero_o_nombre)}/imprimir-precuenta`" target="_blank"
                                           class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-lg border border-slate-700 flex items-center gap-1 transition">
                                            <span>🖨️</span> Precuenta
                                        </a>
                                        <button type="button" @click="abrirModalDividirCuenta(obtenerDatosComandaMesa(mesaSeleccionadaActiva))"
                                                class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 font-bold rounded-lg border border-indigo-500/30 flex items-center gap-1 transition cursor-pointer">
                                            <span>➗</span> Dividir
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                    @else
                        {{-- MODO COMERCIO / TALLER: CATÁLOGO Y BUSCADOR POR CÓDIGO --}}
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
                                        <span class="text-sm font-bold text-emerald-400" x-text="`$${Number(prod.precio || 0).toFixed(2)}`"></span>
                                        <span class="text-[11px]" :class="prod.stock_disponible > 0 ? 'text-slate-400' : 'text-rose-400 font-semibold'"
                                              x-text="`Stock: ${prod.stock_disponible}`"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    @endif

                </div>

                {{-- Ticket de Venta y Cobro Directo --}}
                <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span>🧾 Ticket de Venta</span>
                            <span x-show="origenComandaMesa" class="text-[10px] bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 px-2 py-0.5 rounded-full font-mono" x-text="origenComandaMesa"></span>
                        </h2>
                        <button @click="vaciarCarrito()" class="text-xs text-rose-400 hover:underline cursor-pointer">Vaciar</button>
                    </div>

                    {{-- Datos del Cliente --}}
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <label class="text-slate-400 block mb-1">Cliente / Mesa</label>
                            <input type="text" x-model="cliente.nombre" id="cliente-nombre" class="w-full !bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-slate-400 block mb-1">RFC Fiscal</label>
                            <input type="text" x-model="cliente.rfc" class="w-full !bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white uppercase font-mono focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    {{-- Selector de Cliente Lealtad / Monedero Electrónico --}}
                    <div class="p-2.5 bg-slate-950 border border-slate-800 rounded-xl space-y-2 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <input type="text" x-model="busquedaCliente" @input.debounce.300ms="buscarClientesPos()"
                                   placeholder="⭐ Buscar cliente por nombre o WhatsApp (Puntos/Monedero)..."
                                   class="flex-1 bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                            <button type="button" x-show="clienteSeleccionado" @click="limpiarClienteLealtad()"
                                    class="text-rose-400 hover:text-rose-300 text-[11px] font-bold px-2 cursor-pointer">
                                Quitar
                            </button>
                        </div>

                        <div x-show="listaClientesPos.length > 0 && !clienteSeleccionado" class="max-h-28 overflow-y-auto divide-y divide-slate-800 bg-slate-900 rounded-lg border border-slate-800">
                            <template x-for="cli in listaClientesPos" :key="cli.id">
                                <button type="button" @click="seleccionarClienteLealtad(cli)"
                                        class="w-full text-left px-3 py-1.5 hover:bg-indigo-600/20 flex items-center justify-between cursor-pointer">
                                    <span class="font-bold text-white" x-text="cli.nombre"></span>
                                    <span class="text-[11px] text-emerald-400 font-mono" x-text="`Monedero: $${Number(cli.saldo_monedero).toFixed(2)} | ${cli.puntos_acumulados} pts`"></span>
                                </button>
                            </template>
                        </div>

                        <template x-if="clienteSeleccionado">
                            <div class="flex items-center justify-between pt-1 border-t border-slate-800/80">
                                <div>
                                    <span class="font-bold text-amber-300" x-text="`⭐ ${clienteSeleccionado.nombre}`"></span>
                                    <span class="block text-[10px] text-slate-400" x-text="`Saldo disponible: $${Number(clienteSeleccionado.saldo_monedero).toFixed(2)} (${clienteSeleccionado.puntos_acumulados} pts)`"></span>
                                </div>
                                <label x-show="Number(clienteSeleccionado.saldo_monedero) > 0" class="flex items-center gap-1.5 bg-emerald-950/40 border border-emerald-500/30 px-2.5 py-1 rounded-lg cursor-pointer">
                                    <input type="checkbox" x-model="usarSaldoMonedero" class="rounded text-emerald-500 bg-slate-900 border-slate-700">
                                    <span class="text-[11px] font-bold text-emerald-300">Usar Monedero</span>
                                </label>
                            </div>
                        </template>
                    </div>

                    {{-- Banner de Promociones Automáticas Detectadas --}}
                    <template x-if="promocionesAplicadas.length > 0">
                        <div class="p-2.5 bg-indigo-950/40 border border-indigo-500/40 rounded-xl text-xs space-y-1">
                            <div class="font-bold text-indigo-300 flex items-center justify-between">
                                <span>🎁 Promociones Aplicadas Automáticamente</span>
                                <span class="text-emerald-400 font-mono" x-text="`-$${descuentoPromociones.toFixed(2)}`"></span>
                            </div>
                            <template x-for="p in promocionesAplicadas" :key="p.id">
                                <div class="text-[11px] text-slate-300 flex justify-between">
                                    <span x-text="`• ${p.nombre}`"></span>
                                    <span class="text-emerald-400 font-mono" x-text="`-$${p.ahorro.toFixed(2)}`"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Lista de Partidas en el Carrito --}}
                    <div class="space-y-2 max-h-[260px] overflow-y-auto pr-1">
                        <template x-for="(item, index) in carrito" :key="index">
                            <div class="p-2.5 bg-slate-950/70 border border-slate-800/80 rounded-xl flex items-center justify-between gap-2 text-xs">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-white truncate" x-text="item.nombre"></div>
                                    <template x-if="item.notas">
                                        <div class="text-[10px] text-amber-300 font-medium truncate" x-text="'👉 ' + item.notas"></div>
                                    </template>
                                    <div class="text-[11px] text-slate-400" x-text="`$${Number(item.precio_unitario || 0).toFixed(2)} c/u`"></div>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button @click="decrementarCantidad(index)" class="w-6 h-6 rounded bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center">-</button>
                                    <span class="w-8 text-center font-bold text-white" x-text="item.cantidad"></span>
                                    <button @click="incrementarCantidad(index)" class="w-6 h-6 rounded bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center">+</button>
                                </div>
                                <div class="text-right w-16">
                                    <div class="font-bold text-emerald-400" x-text="`$${((item.cantidad || 0) * (item.precio_unitario || 0)).toFixed(2)}`"></div>
                                </div>
                                <button @click="removerDelCarrito(index)" class="text-rose-400 hover:text-rose-300 p-1">&times;</button>
                            </div>
                        </template>

                        <template x-if="carrito.length === 0">
                            <div class="py-8 text-center text-slate-500 text-xs">
                                Toca una mesa en el plano o presiona <strong>"🪑 Mesas Abiertas"</strong> para cargar un consumo.
                            </div>
                        </template>
                    </div>

                    {{-- Desglose Económico e Impuestos --}}
                    <div class="pt-3 border-t border-slate-800 space-y-1.5 text-xs text-slate-400">
                        <template x-if="descuentoPromociones > 0">
                            <div class="flex justify-between text-indigo-300 font-semibold">
                                <span>Descuento Promociones:</span>
                                <span class="font-mono" x-text="`-$${descuentoPromociones.toFixed(2)}`"></span>
                            </div>
                        </template>
                        <template x-if="montoMonederoAplicado > 0">
                            <div class="flex justify-between text-emerald-300 font-semibold">
                                <span>Canje Monedero Electrónico:</span>
                                <span class="font-mono" x-text="`-$${montoMonederoAplicado.toFixed(2)}`"></span>
                            </div>
                        </template>
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span class="text-white font-mono" x-text="`$${totales.subtotal.toFixed(2)}`"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>IVA Trasladado (16%):</span>
                            <span class="text-white font-mono" x-text="`$${totales.iva.toFixed(2)}`"></span>
                        </div>
                        <div class="flex justify-between text-base font-bold text-white pt-2 border-t border-slate-800">
                            <span>Total a Cobrar:</span>
                            <span class="text-emerald-400 font-mono text-lg" x-text="`$${totales.total.toFixed(2)}`"></span>
                        </div>
                    </div>

                    {{-- Selección de Modalidad de Pago (Simple, Mixto, Doble Tarjeta) --}}
                    <div class="pt-2 space-y-3">
                        <label class="text-[11px] text-slate-400 font-bold block uppercase tracking-wider">Modalidad de Pago</label>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 text-xs">
                            <button type="button" @click="cambiarModalidadPago('simple_efectivo')"
                                    :class="modalidadPago === 'simple_efectivo' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-950 text-slate-400 border border-slate-800'"
                                    class="py-2 px-1.5 rounded-lg text-center transition cursor-pointer">
                                💵 Efectivo
                            </button>
                            <button type="button" @click="cambiarModalidadPago('simple_tarjeta')"
                                    :class="modalidadPago === 'simple_tarjeta' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-950 text-slate-400 border border-slate-800'"
                                    class="py-2 px-1.5 rounded-lg text-center transition cursor-pointer">
                                💳 Tarjeta
                            </button>
                            <button type="button" @click="cambiarModalidadPago('mixto_efectivo_tarjeta')"
                                    :class="modalidadPago === 'mixto_efectivo_tarjeta' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-950 text-slate-400 border border-slate-800'"
                                    class="py-2 px-1.5 rounded-lg text-center transition cursor-pointer">
                                💵+💳 Mixto
                            </button>
                            <button type="button" @click="cambiarModalidadPago('doble_tarjeta')"
                                    :class="modalidadPago === 'doble_tarjeta' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-950 text-slate-400 border border-slate-800'"
                                    class="py-2 px-1.5 rounded-lg text-center transition cursor-pointer">
                                💳+💳 2 Tarjetas
                            </button>
                        </div>

                        {{-- CASO 1: Efectivo Simple --}}
                        <template x-if="modalidadPago === 'simple_efectivo'">
                            <div class="grid grid-cols-2 gap-2 text-xs p-3 bg-slate-950 rounded-xl border border-slate-800">
                                <div>
                                    <label class="text-slate-400 block mb-1">Monto Recibido</label>
                                    <input type="number" step="0.50" x-model.number="pagoEfectivoRecibido"
                                           class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label class="text-slate-400 block mb-1">Cambio a Devolver</label>
                                    <div class="px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-emerald-400 font-mono text-sm font-bold"
                                         x-text="`$${Math.max(0, (Number(pagoEfectivoRecibido) || 0) - totales.total).toFixed(2)}`"></div>
                                </div>
                            </div>
                        </template>

                        {{-- CASO 2: Tarjeta o Transferencia Simple --}}
                        <template x-if="modalidadPago === 'simple_tarjeta'">
                            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Tipo:</span>
                                    <select x-model="tipoTarjetaSimple" class="!bg-slate-900 border border-slate-800 rounded px-2 py-1 text-xs text-white">
                                        <option value="04">04 - Tarjeta de Crédito</option>
                                        <option value="28">28 - Tarjeta de Débito</option>
                                        <option value="03">03 - Transferencia Bancaria</option>
                                    </select>
                                </div>
                                <div>
                                    <input type="text" x-model="referenciaTarjeta1" placeholder="Referencia / Voucher bancario (opcional)..."
                                           class="w-full !bg-slate-900 border border-slate-800 rounded px-2.5 py-1.5 text-xs text-white">
                                </div>
                            </div>
                        </template>

                        {{-- CASO 3: Mixto (Efectivo + Tarjeta/Transferencia) --}}
                        <template x-if="modalidadPago === 'mixto_efectivo_tarjeta'">
                            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs space-y-2.5">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-slate-400 block mb-1">💵 Monto en Efectivo ($)</label>
                                        <input type="number" step="0.50" x-model.number="montoMixtoEfectivo" @input="recalcularRestoMixto()"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 !text-white font-mono text-xs">
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">💳 Resto en Tarjeta/Transf.</label>
                                        <div class="px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-indigo-400 font-mono text-xs font-bold"
                                             x-text="`$${Number(montoMixtoTarjeta || 0).toFixed(2)}`"></div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-slate-400 block mb-1">Efectivo Entregado</label>
                                        <input type="number" step="0.50" x-model.number="pagoEfectivoRecibido"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 !text-white font-mono text-xs">
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">Cambio Efectivo</label>
                                        <div class="px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-emerald-400 font-mono text-xs font-bold"
                                             x-text="`$${Math.max(0, (Number(pagoEfectivoRecibido) || 0) - (Number(montoMixtoEfectivo) || 0)).toFixed(2)}`"></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <select x-model="tipoTarjetaMixto" class="!bg-slate-900 border border-slate-800 rounded px-2 py-1 text-xs text-white">
                                        <option value="04">Tarjeta Crédito (04)</option>
                                        <option value="28">Tarjeta Débito (28)</option>
                                        <option value="03">Transferencia (03)</option>
                                    </select>
                                    <input type="text" x-model="referenciaTarjeta1" placeholder="Ref. voucher..."
                                           class="flex-1 !bg-slate-900 border border-slate-800 rounded px-2 py-1 text-xs text-white">
                                </div>
                            </div>
                        </template>

                        {{-- CASO 4: Combinación de 2 Tarjetas Diferentes --}}
                        <template x-if="modalidadPago === 'doble_tarjeta'">
                            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs space-y-2.5">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-slate-400 block mb-1">💳 Tarjeta 1 ($)</label>
                                        <input type="number" step="0.50" x-model.number="montoTarjeta1" @input="recalcularRestoDobleTarjeta()"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 !text-white font-mono text-xs">
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">💳 Tarjeta 2 ($)</label>
                                        <div class="px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-indigo-400 font-mono text-xs font-bold"
                                             x-text="`$${Number(montoTarjeta2 || 0).toFixed(2)}`"></div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="referenciaTarjeta1" placeholder="Ref. Tarjeta 1..."
                                           class="!bg-slate-900 border border-slate-800 rounded px-2 py-1 text-xs text-white">
                                    <input type="text" x-model="referenciaTarjeta2" placeholder="Ref. Tarjeta 2..."
                                           class="!bg-slate-900 border border-slate-800 rounded px-2 py-1 text-xs text-white">
                                </div>
                            </div>
                        </template>

                        <button @click="procesarVenta()" :disabled="carrito.length === 0 || isProcessing"
                                class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-600/20 transition flex items-center justify-center gap-2 cursor-pointer">
                            <span x-show="!isProcessing">Cobrar e Imprimir Ticket</span>
                            <span x-show="isProcessing">Procesando y liberando mesa...</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 4: MODAL DE MESAS ABIERTAS CON CONSUMO (RESTAURANTE)              --}}
        {{-- ========================================================================= --}}
        <div x-show="modalMesasPos" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalMesasPos = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-4 max-h-[88vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800 shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🪑</span>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Cuentas y Mesas Activas</h3>
                    </div>
                    <button @click="modalMesasPos = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                    <template x-if="cargandoMesas">
                        <p class="text-center text-xs text-slate-400 py-8">Consultando comandas abiertas...</p>
                    </template>

                    <template x-if="!cargandoMesas && mesasAbiertas.length === 0">
                        <div class="text-center py-12 text-slate-500 text-xs">
                            <span class="text-3xl block mb-2">🍽️</span>
                            No hay mesas con cuentas abiertas en este momento.
                        </div>
                    </template>

                    <template x-for="m in mesasAbiertas" :key="m.mesa">
                        <div class="p-4 bg-slate-950 border rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 transition"
                             :class="m.cuenta_pedida ? 'border-amber-500/60 bg-amber-950/10' : 'border-slate-800'">

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="text-sm font-black text-white" x-text="m.mesa"></h4>
                                    <template x-if="m.cuenta_pedida">
                                        <span class="bg-rose-500/20 text-rose-300 border border-rose-500/40 px-2 py-0.5 rounded text-[10px] font-bold animate-pulse">
                                            🔔 CUENTA PEDIDA
                                        </span>
                                    </template>
                                    <template x-if="!m.cuenta_pedida">
                                        <span class="bg-slate-800 text-slate-400 px-2 py-0.5 rounded text-[10px]">
                                            Consumiendo
                                        </span>
                                    </template>
                                    <span class="text-[10px] text-slate-500 font-mono" x-text="m.folio"></span>
                                </div>
                                <p class="text-xs text-slate-400">
                                    <span x-text="`${(m.items || []).length} partidas`"></span> • Atiende: <strong class="text-slate-300" x-text="m.mesero"></strong>
                                </p>
                            </div>

                            <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                                <div class="text-right">
                                    <span class="text-[10px] text-slate-500 block">Total Cuenta</span>
                                    <span class="text-sm font-black text-emerald-400" x-text="'$' + Number(m.total || 0).toFixed(2)"></span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <a :href="`/pos/restaurante/mesa/${encodeURIComponent(m.mesa)}/imprimir-precuenta`" target="_blank"
                                       class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-lg border border-slate-700 transition flex items-center gap-1">
                                        <span>🖨️</span> Precuenta
                                    </a>

                                    <button type="button" @click="cargarMesaAlCarrito(m)"
                                            class="px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1 shadow-lg shadow-emerald-950/40 cursor-pointer">
                                        <span>💳</span> Cobrar
                                    </button>

                                    <button type="button" @click="abrirModalDividirCuenta(m)"
                                            class="px-2.5 py-2 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-bold rounded-lg transition flex items-center gap-1 cursor-pointer">
                                        <span>➗</span> Dividir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="pt-3 border-t border-slate-800 flex justify-end shrink-0">
                    <button type="button" @click="modalMesasPos = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-300">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- MODAL DE DIVISIÓN DE CUENTA (SPLIT BILL POR COMENSAL / PLATILLOS)         --}}
        {{-- ========================================================================= --}}
        <div x-show="modalDividir" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalDividir = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full p-5 shadow-2xl space-y-4 max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-sm font-bold text-white flex items-center gap-2">
                            <span>➗ Dividir Cuenta:</span>
                            <span class="text-emerald-400" x-text="mesaDividiendo?.mesa || 'Mesa'"></span>
                        </h3>
                        <p class="text-[11px] text-slate-400">Selecciona qué platillos pagará este comensal o divide por partes iguales.</p>
                    </div>
                    <button @click="modalDividir = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <div class="flex gap-2">
                    <button type="button" @click="modoDivision = 'items'"
                            :class="modoDivision === 'items' ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400 border border-slate-800'"
                            class="flex-1 py-1.5 text-xs font-bold rounded-lg transition">
                        Por Platillos Consumidos
                    </button>
                    <button type="button" @click="modoDivision = 'partes_iguales'"
                            :class="modoDivision === 'partes_iguales' ? 'bg-indigo-600 text-white' : 'bg-slate-950 text-slate-400 border border-slate-800'"
                            class="flex-1 py-1.5 text-xs font-bold rounded-lg transition">
                        Dividir en Partes Iguales
                    </button>
                </div>

                <div x-show="modoDivision === 'items'" class="flex-1 overflow-y-auto space-y-2 pr-1 max-h-60">
                    <template x-for="(it, idx) in partidasDivision" :key="idx">
                        <div class="p-2.5 bg-slate-950 border border-slate-800 rounded-xl flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" x-model="it.seleccionado" class="rounded border-slate-700 text-indigo-600 focus:ring-0">
                                <div>
                                    <div class="font-bold text-white" x-text="it.nombre"></div>
                                    <div class="text-[10px] text-slate-400" x-text="`$${Number(it.precio_unitario || 0).toFixed(2)} c/u`"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-emerald-400" x-text="`$${((it.cantidad || 0) * (it.precio_unitario || 0)).toFixed(2)}`"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="modoDivision === 'partes_iguales'" class="p-4 bg-slate-950 border border-slate-800 rounded-xl text-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-300">Total mesa acumulado:</span>
                        <span class="font-bold text-white font-mono" x-text="`$${Number(mesaDividiendo?.total || 0).toFixed(2)}`"></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="text-slate-400">Dividir entre cuántas personas:</label>
                        <input type="number" min="2" max="20" x-model.number="numeroPartesIguales"
                               class="w-20 !bg-slate-900 border border-slate-800 rounded px-2 py-1 text-white text-center font-bold">
                    </div>
                    <div class="p-3 bg-indigo-950/40 border border-indigo-500/30 rounded-lg flex justify-between items-center">
                        <span class="text-indigo-300">Monto por cada persona:</span>
                        <span class="text-base font-black text-emerald-400 font-mono"
                              x-text="`$${(Number(mesaDividiendo?.total || 0) / Math.max(1, Number(numeroPartesIguales || 2))).toFixed(2)}`"></span>
                    </div>
                </div>

                <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
                    <span class="text-slate-400">Importe de esta subcuenta:</span>
                    <span class="text-base font-black text-emerald-400 font-mono" x-text="`$${calcularTotalSubcuenta().toFixed(2)}`"></span>
                </div>

                <div class="flex gap-2 pt-2 border-t border-slate-800">
                    <button type="button" @click="modalDividir = false" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold">
                        Cancelar
                    </button>
                    <button type="button" @click="cargarSubcuentaAlPOS()"
                            :disabled="calcularTotalSubcuenta() <= 0"
                            class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-950/40 cursor-pointer">
                        Cobrar Esta Subcuenta en Caja →
                    </button>
                </div>
            </div>
        </div>

        {{-- Modales Auxiliares (Apertura, Corte Parcial X, Cierre Z, Reimpresión, CFDI) --}}
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

        {{-- Modal: Corte Parcial (X) y Retiro de Efectivo --}}
        <div x-show="modalCorteParcial" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalCorteParcial = false" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-md w-full space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-white">📊 Corte Parcial (X) y Retiros de Caja</h3>
                    <button type="button" @click="modalCorteParcial = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="p-3.5 bg-slate-950 rounded-xl border border-slate-800 text-xs space-y-1.5 font-mono" x-show="datosCorteParcial">
                    <div class="flex justify-between text-slate-400">
                        <span>Tickets Emitidos:</span>
                        <span class="text-white font-bold" x-text="datosCorteParcial?.cantidad_tickets || 0"></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Ventas Efectivo:</span>
                        <span class="text-emerald-400 font-bold" x-text="`$${Number(datosCorteParcial?.ventas_efectivo || 0).toFixed(2)}`"></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Ventas Tarjeta / Transf.:</span>
                        <span class="text-indigo-400" x-text="`$${(Number(datosCorteParcial?.ventas_tarjeta || 0) + Number(datosCorteParcial?.ventas_transferencia || 0)).toFixed(2)}`"></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Retiros Realizados:</span>
                        <span class="text-rose-400" x-text="`-$${Number(datosCorteParcial?.total_retiros || 0).toFixed(2)}`"></span>
                    </div>
                    <div class="flex justify-between font-bold text-white pt-2 border-t border-slate-800 text-sm">
                        <span>Efectivo Teórico en Gaveta:</span>
                        <span class="text-amber-400" x-text="`$${Number(datosCorteParcial?.efectivo_esperado || 0).toFixed(2)}`"></span>
                    </div>
                </div>

                <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl space-y-2.5 text-xs">
                    <span class="font-bold text-slate-300 block">Registrar Retiro de Efectivo / Caja Chica</span>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" x-model="formMovimientoCaja.concepto" placeholder="Concepto (ej. Compra hielo)"
                               class="bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white">
                        <input type="number" step="0.50" x-model.number="formMovimientoCaja.monto" placeholder="Monto ($)"
                               class="bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono">
                    </div>
                    <input type="text" x-model="formMovimientoCaja.motivo" placeholder="Motivo o folio de comprobante..."
                           class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white">
                    <button type="button" @click="guardarRetiroCaja()"
                            class="w-full py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-lg transition cursor-pointer">
                        💸 Registrar Retiro de Efectivo
                    </button>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" @click="modalCorteParcial = false" class="flex-1 py-2.5 bg-slate-800 text-slate-300 rounded-xl text-xs font-semibold cursor-pointer">
                        Cerrar
                    </button>
                    <a :href="`/pos/caja/${turnoActivo?.id}/imprimir-corte?tipo=X`" target="_blank"
                       class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold text-center shadow-lg transition">
                        🖨️ Imprimir Corte Parcial (X)
                    </a>
                </div>
            </div>
        </div>

        <div x-show="modalCierre" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-sm w-full space-y-4 shadow-2xl">
                <h3 class="text-base font-bold text-white">Corte Final (Z) y Cierre de Turno</h3>
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
                    <button type="button" @click="cerrarTurnoCaja()" class="flex-1 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-rose-600/30 transition cursor-pointer">Confirmar Corte Z</button>
                </div>
            </div>
        </div>

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
                                <span class="text-xs font-bold text-white font-mono" x-text="'$' + Number(v.total || 0).toFixed(2)"></span>
                                <button type="button" @click="lanzarImpresion(v.id)"
                                        class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold flex items-center gap-1 shadow cursor-pointer">
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
                                    <span class="text-xs font-bold text-white font-mono mr-2" x-text="'$' + Number(v.total || 0).toFixed(2)"></span>

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

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 6: LÓGICA REACTIVA JAVASCRIPT CON ALPINE.JS                        --}}
    {{-- ========================================================================= --}}
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

                // Estados de Modales
                modalApertura: false,
                modalCierre: false,
                formApertura: { monto_inicial: 1000, observaciones: '' },
                formCierre: { monto_real_efectivo: 0, observaciones: '' },

                // Catálogo y Carrito
                searchQuery: '',
                productos: [],
                carrito: [],
                cliente: { nombre: 'Público General', rfc: 'XAXX010101000' },
                formaPagoSat: '01',
                montoRecibido: 0,

                // Promociones, Puntos y Monedero Electrónico
                promocionesVigentes: [],
                busquedaCliente: '',
                listaClientesPos: [],
                clienteSeleccionado: null,
                usarSaldoMonedero: false,

                // Control de Cuentas y Mesas de Restaurante
                modalMesasPos: false,
                cargandoMesas: false,
                mesasAbiertas: [],
                origenComandaMesa: '',
                comandaIdsCobrando: [],
                esSubcuentaCobrando: false,
                itemsPagadosIds: [],

                // Modalidades de Pago Mixto y Doble Tarjeta
                modalidadPago: 'simple_efectivo',
                pagoEfectivoRecibido: 0,
                tipoTarjetaSimple: '04',
                tipoTarjetaMixto: '04',
                montoMixtoEfectivo: 0,
                montoMixtoTarjeta: 0,
                montoTarjeta1: 0,
                montoTarjeta2: 0,
                referenciaTarjeta1: '',
                referenciaTarjeta2: '',

                // Modal Split Bill (División de Cuentas)
                modalDividir: false,
                mesaDividiendo: null,
                partidasDivision: [],
                modoDivision: 'items',
                numeroPartesIguales: 2,

                // Reimpresión y Facturación
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

                // Áreas y Plano de Mesas
                areas: config.areasIniciales || [],
                areaSeleccionadaId: (config.areasIniciales && config.areasIniciales.length > 0) ? config.areasIniciales[0].id : null,
                mesaSeleccionadaActiva: null,

                modalCorteParcial: false,
                datosCorteParcial: null,
                formMovimientoCaja: { tipo: 'retiro', concepto: '', monto: 0, motivo: '' },

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
                    await this.cargarPromocionesVigentes();
                    await this.actualizarContadorOffline();

                    if (this.routes.comandasAbiertas) {
                        this.consultarMesasAbiertas();
                        setInterval(() => this.consultarMesasAbiertas(), 5000);
                    }
                },

                async cargarPromocionesVigentes() {
                    try {
                        const res = await fetch('/marketing/promociones/vigentes-api', {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            this.promocionesVigentes = await res.json();
                        }
                    } catch (e) {}
                },

                async buscarClientesPos() {
                    if (this.busquedaCliente.trim().length < 2) {
                        this.listaClientesPos = [];
                        return;
                    }
                    try {
                        const res = await fetch(`/pos/clientes-lealtad?q=${encodeURIComponent(this.busquedaCliente)}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            this.listaClientesPos = await res.json();
                        }
                    } catch (e) {}
                },

                seleccionarClienteLealtad(cli) {
                    this.clienteSeleccionado = cli;
                    this.cliente.nombre = cli.nombre;
                    if (cli.rfc) this.cliente.rfc = cli.rfc;
                    this.listaClientesPos = [];
                },

                limpiarClienteLealtad() {
                    this.clienteSeleccionado = null;
                    this.usarSaldoMonedero = false;
                    this.busquedaCliente = '';
                },

                get promocionesAplicadas() {
                    const aplicadas = [];
                    if (!this.promocionesVigentes.length || !this.carrito.length) return aplicadas;

                    this.promocionesVigentes.forEach(promo => {
                        if (promo.solo_clientes_destacados && this.clienteSeleccionado?.nivel_lealtad !== 'destacado') {
                            return;
                        }

                        const idsParticipantes = (promo.items || []).map(i => Number(i.producto_id)).filter(Boolean);
                        const lineasElegibles = idsParticipantes.length > 0
                            ? this.carrito.filter(item => idsParticipantes.includes(Number(item.producto_id)))
                            : this.carrito;

                        if (lineasElegibles.length === 0) return;

                        const totalUnidades = lineasElegibles.reduce((acc, i) => acc + Number(i.cantidad), 0);
                        let ahorro = 0;

                        if (promo.tipo === '2x1' && totalUnidades >= 2) {
                            const pares = Math.floor(totalUnidades / 2);
                            const precioMenor = Math.min(...lineasElegibles.map(i => Number(i.precio_unitario)));
                            ahorro = pares * precioMenor;
                        } else if (promo.tipo === 'segundo_al_porcentaje' && totalUnidades >= 2) {
                            const pares = Math.floor(totalUnidades / 2);
                            const precioMenor = Math.min(...lineasElegibles.map(i => Number(i.precio_unitario)));
                            ahorro = pares * (precioMenor * (Number(promo.valor_descuento) / 100));
                        } else if (promo.tipo === 'descuento_porcentaje') {
                            const subtotalElegible = lineasElegibles.reduce((acc, i) => acc + (Number(i.precio_unitario) * Number(i.cantidad)), 0);
                            ahorro = subtotalElegible * (Number(promo.valor_descuento) / 100);
                        } else if (promo.tipo === 'precio_fijo_combo' && totalUnidades >= idsParticipantes.length && idsParticipantes.length > 0) {
                            const sumaOriginal = lineasElegibles.reduce((acc, i) => acc + Number(i.precio_unitario), 0);
                            if (sumaOriginal > Number(promo.precio_combo)) {
                                ahorro = sumaOriginal - Number(promo.precio_combo);
                            }
                        }

                        if (ahorro > 0) {
                            aplicadas.push({ id: promo.id, nombre: promo.nombre, ahorro: Math.round(ahorro * 100) / 100 });
                        }
                    });

                    return aplicadas;
                },

                get descuentoPromociones() {
                    return this.promocionesAplicadas.reduce((acc, p) => acc + p.ahorro, 0);
                },

                get montoMonederoAplicado() {
                    if (!this.usarSaldoMonedero || !this.clienteSeleccionado) return 0;
                    let base = 0;
                    (this.carrito || []).forEach(item => {
                        base += ((Number(item.cantidad) || 0) * (Number(item.precio_unitario) || 0));
                    });
                    const totalAntesMonedero = Math.max(0, base - this.descuentoPromociones);
                    return Math.min(Number(this.clienteSeleccionado.saldo_monedero), totalAntesMonedero);
                },

                async abrirModalCorteParcial() {
                    if (!this.turnoActivo) return;
                    this.modalCorteParcial = true;
                    const res = await fetch(`/pos/caja/${this.turnoActivo.id}/resumen-parcial`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        this.datosCorteParcial = await res.json();
                        this.turnoActivo.monto_esperado_efectivo = this.datosCorteParcial.efectivo_esperado;
                        this.turnoActivo.total_ventas_efectivo = this.datosCorteParcial.ventas_efectivo;
                    }
                },

                async guardarRetiroCaja() {
                    if (!this.formMovimientoCaja.concepto || this.formMovimientoCaja.monto <= 0) {
                        alert('Ingresa concepto y monto válido.');
                        return;
                    }
                    const res = await fetch('/pos/caja/movimiento', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            caja_turno_id: this.turnoActivo.id,
                            tipo: 'retiro',
                            concepto: this.formMovimientoCaja.concepto,
                            monto: this.formMovimientoCaja.monto,
                            motivo: this.formMovimientoCaja.motivo || this.formMovimientoCaja.concepto
                        })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        this.formMovimientoCaja = { tipo: 'retiro', concepto: '', monto: 0, motivo: '' };
                        await this.abrirModalCorteParcial();
                        alert('✓ Retiro registrado en bitácora.');
                    } else {
                        alert(data.message || 'Error al registrar retiro.');
                    }
                },

                get hayCuentasPedidas() {
                    return Array.isArray(this.mesasAbiertas) && this.mesasAbiertas.some(m => m.cuenta_pedida);
                },

                abrirModalMesasRestaurante() {
                    this.modalMesasPos = true;
                    this.consultarMesasAbiertas();
                },

                async consultarMesasAbiertas() {
                    if (!this.routes.comandasAbiertas || !this.isOnline) return;
                    try {
                        const res = await fetch(this.routes.comandasAbiertas, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            const data = await res.json();
                            this.mesasAbiertas = Array.isArray(data) ? data : [];
                        }
                    } catch (e) {
                        console.error('Error al consultar mesas en POS:', e);
                    }
                },

                cargarMesaAlCarrito(mesaData) {
                    this.origenComandaMesa = `${mesaData.mesa} - Folios: ${mesaData.folio}`;
                    this.comandaIdsCobrando = mesaData.comanda_ids || [];
                    this.cliente.nombre = `${mesaData.mesa}`;

                    this.carrito = mesaData.items.map(it => ({
                        producto_id: it.producto_id,
                        sku: it.sku || 'CMD',
                        nombre: it.nombre,
                        precio_unitario: Number(it.precio_unitario),
                        cantidad: Number(it.cantidad),
                        tasa_iva: 0.1600,
                        notas: it.notas || ''
                    }));

                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                    this.montoRecibido = this.pagoEfectivoRecibido;
                    this.modalMesasPos = false;
                },

                cambiarModalidadPago(modo) {
                    this.modalidadPago = modo;
                    const total = Number(this.totales.total.toFixed(2));
                    this.pagoEfectivoRecibido = total;

                    if (modo === 'mixto_efectivo_tarjeta') {
                        this.montoMixtoEfectivo = Math.floor(total / 2);
                        this.montoMixtoTarjeta = Math.max(0, total - this.montoMixtoEfectivo);
                    } else if (modo === 'doble_tarjeta') {
                        this.montoTarjeta1 = Math.floor(total / 2);
                        this.montoTarjeta2 = Math.max(0, total - this.montoTarjeta1);
                    }
                },

                recalcularRestoMixto() {
                    const total = Number(this.totales.total.toFixed(2));
                    this.montoMixtoTarjeta = Math.max(0, total - (Number(this.montoMixtoEfectivo) || 0));
                },

                recalcularRestoDobleTarjeta() {
                    const total = Number(this.totales.total.toFixed(2));
                    this.montoTarjeta2 = Math.max(0, total - (Number(this.montoTarjeta1) || 0));
                },

                abrirModalDividirCuenta(mesaData) {
                    this.mesaDividiendo = mesaData;
                    this.partidasDivision = (mesaData.items || []).map(it => ({
                        ...it,
                        seleccionado: true
                    }));
                    this.modalMesasPos = false;
                    this.modalDividir = true;
                },

                calcularTotalSubcuenta() {
                    if (this.modoDivision === 'partes_iguales') {
                        const total = Number(this.mesaDividiendo?.total || 0);
                        const n = Math.max(1, Number(this.numeroPartesIguales || 2));
                        return total / n;
                    }
                    return (this.partidasDivision || [])
                        .filter(i => i.seleccionado)
                        .reduce((sum, i) => sum + ((Number(i.cantidad) || 0) * (Number(i.precio_unitario) || 0)), 0);
                },

                cargarSubcuentaAlPOS() {
                    const totalSub = this.calcularTotalSubcuenta();
                    if (totalSub <= 0) return;

                    this.esSubcuentaCobrando = true;
                    this.origenComandaMesa = `${this.mesaDividiendo?.mesa || 'Mesa'} (Subcuenta Fracción)`;
                    this.comandaIdsCobrando = this.mesaDividiendo?.comanda_ids || [];
                    this.cliente.nombre = `${this.mesaDividiendo?.mesa || 'Mesa'} - Subcuenta`;

                    if (this.modoDivision === 'partes_iguales') {
                        this.itemsPagadosIds = [];
                        this.carrito = [{
                            producto_id: this.partidasDivision[0]?.producto_id || 1,
                            sku: 'SPLIT',
                            nombre: `Consumo Fracción (${this.mesaDividiendo?.mesa || 'Mesa'})`,
                            precio_unitario: Number(totalSub.toFixed(2)),
                            cantidad: 1,
                            tasa_iva: 0.1600,
                            notas: `Abono de cuenta dividida`
                        }];
                    } else {
                        const seleccionados = (this.partidasDivision || []).filter(i => i.seleccionado);
                        this.itemsPagadosIds = seleccionados.map(i => i.id);
                        this.carrito = seleccionados.map(it => ({
                            producto_id: it.producto_id,
                            sku: it.sku || 'CMD',
                            nombre: it.nombre,
                            precio_unitario: Number(it.precio_unitario || 0),
                            cantidad: Number(it.cantidad || 1),
                            tasa_iva: 0.1600,
                            notas: it.notas || ''
                        }));
                    }

                    this.cambiarModalidadPago('simple_efectivo');
                    this.modalDividir = false;
                },

                get mesasAreaActual() {
                    const area = this.areas.find(a => a.id === this.areaSeleccionadaId);
                    return area ? (area.mesas || []) : [];
                },

                obtenerDatosComandaMesa(mesa) {
                    if (!this.mesasAbiertas || !Array.isArray(this.mesasAbiertas)) return null;
                    return this.mesasAbiertas.find(m => m.mesa === mesa.numero_o_nombre || m.mesa === `Mesa ${mesa.numero_o_nombre}`);
                },

                mesaTieneConsumo(mesa) {
                    return this.obtenerDatosComandaMesa(mesa) !== undefined && this.obtenerDatosComandaMesa(mesa) !== null;
                },

                mesaTieneCuentaPedida(mesa) {
                    const data = this.obtenerDatosComandaMesa(mesa);
                    return data && Boolean(data.cuenta_pedida);
                },

                calcularDescuentoParaItems(items) {
                    if (!this.promocionesVigentes.length || !Array.isArray(items) || !items.length) return 0;

                    let descuentoTotal = 0;
                    this.promocionesVigentes.forEach(promo => {
                        if (promo.solo_clientes_destacados && this.clienteSeleccionado?.nivel_lealtad !== 'destacado') {
                            return;
                        }

                        const idsParticipantes = (promo.items || []).map(i => Number(i.producto_id)).filter(Boolean);
                        const lineasElegibles = idsParticipantes.length > 0
                            ? items.filter(item => idsParticipantes.includes(Number(item.producto_id)))
                            : items;

                        if (lineasElegibles.length === 0) return;

                        const totalUnidades = lineasElegibles.reduce((acc, i) => acc + Number(i.cantidad), 0);
                        let ahorro = 0;

                        if (promo.tipo === '2x1' && totalUnidades >= 2) {
                            const pares = Math.floor(totalUnidades / 2);
                            const precioMenor = Math.min(...lineasElegibles.map(i => Number(i.precio_unitario)));
                            ahorro = pares * precioMenor;
                        } else if (promo.tipo === 'segundo_al_porcentaje' && totalUnidades >= 2) {
                            const pares = Math.floor(totalUnidades / 2);
                            const precioMenor = Math.min(...lineasElegibles.map(i => Number(i.precio_unitario)));
                            ahorro = pares * (precioMenor * (Number(promo.valor_descuento) / 100));
                        } else if (promo.tipo === 'descuento_porcentaje') {
                            const subtotalElegible = lineasElegibles.reduce((acc, i) => acc + (Number(i.precio_unitario) * Number(i.cantidad)), 0);
                            ahorro = subtotalElegible * (Number(promo.valor_descuento) / 100);
                        } else if (promo.tipo === 'precio_fijo_combo' && totalUnidades >= idsParticipantes.length && idsParticipantes.length > 0) {
                            const sumaOriginal = lineasElegibles.reduce((acc, i) => acc + Number(i.precio_unitario), 0);
                            if (sumaOriginal > Number(promo.precio_combo)) {
                                ahorro = sumaOriginal - Number(promo.precio_combo);
                            }
                        }

                        descuentoTotal += Math.round(ahorro * 100) / 100;
                    });

                    return descuentoTotal;
                },

                obtenerTotalConsumoMesa(mesa) {
                    const data = this.obtenerDatosComandaMesa(mesa);
                    if (!data) return 0;

                    if (Array.isArray(data.items) && data.items.length > 0 && data.total !== undefined) {
                        const bruto = Number(data.total) || 0;
                        const descuento = this.calcularDescuentoParaItems(data.items);
                        return Math.max(0, bruto - descuento);
                    }
                    return 0;
                },

                obtenerEstiloMesaPlano(mesa) {
                    const esPedida = this.mesaTieneCuentaPedida(mesa);
                    const tieneConsumo = this.mesaTieneConsumo(mesa);
                    const esFormaRedonda = mesa.forma === 'redonda';

                    let base = esFormaRedonda ? 'rounded-full w-24 h-24' : 'rounded-2xl w-24 h-24';

                    if (esPedida) {
                        return `${base} bg-cyan-950/80 border-cyan-400 text-cyan-300 ring-4 ring-cyan-500/50 shadow-cyan-500/50 animate-pulse`;
                    }
                    if (tieneConsumo) {
                        return `${base} bg-amber-950/70 border-amber-500/80 text-amber-200 ring-2 ring-amber-500/30 hover:border-amber-400`;
                    }
                    return `${base} bg-slate-900 border-slate-800 text-slate-400 hover:border-slate-700 opacity-80`;
                },

                seleccionarMesaPlano(mesa) {
                    this.mesaSeleccionadaActiva = mesa;
                    const comandaData = this.obtenerDatosComandaMesa(mesa);

                    if (comandaData) {
                        this.cargarMesaAlCarrito(comandaData);
                    } else {
                        this.vaciarCarrito();
                        this.cliente.nombre = mesa.numero_o_nombre;
                    }
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

                get productosFiltrados() {
                    if (!this.searchQuery.trim()) return (this.productos || []).slice(0, 30);
                    const q = this.searchQuery.toLowerCase();
                    return (this.productos || []).filter(p =>
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
                            precio_unitario: Number(producto.precio || 0),
                            cantidad: 1,
                            tasa_iva: 0.1600,
                            notas: ''
                        });
                    }
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                },

                incrementarCantidad(index) {
                    this.carrito[index].cantidad += 1;
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                },

                decrementarCantidad(index) {
                    if (this.carrito[index].cantidad > 1) {
                        this.carrito[index].cantidad -= 1;
                    } else {
                        this.removerDelCarrito(index);
                    }
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                },

                removerDelCarrito(index) {
                    this.carrito.splice(index, 1);
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                },

                vaciarCarrito() {
                    this.carrito = [];
                    this.montoRecibido = 0;
                    this.origenComandaMesa = '';
                    this.comandaIdsCobrando = [];
                    this.esSubcuentaCobrando = false;
                    this.itemsPagadosIds = [];
                    this.limpiarClienteLealtad();
                    this.cliente = { nombre: 'Público General', rfc: 'XAXX010101000' };
                    this.cambiarModalidadPago('simple_efectivo');
                },

                get totales() {
                    let bruto = 0;
                    (this.carrito || []).forEach(item => {
                        bruto += ((Number(item.cantidad) || 0) * (Number(item.precio_unitario) || 0));
                    });
                    const totalConIva = Math.max(0, bruto - this.descuentoPromociones - this.montoMonederoAplicado);
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

                    let pagosArray = [];

                    if (this.modalidadPago === 'simple_efectivo') {
                        const recibido = Math.max(totalVenta, Number(this.pagoEfectivoRecibido) || 0);
                        pagosArray.push({
                            forma_pago_sat: '01',
                            monto: totalVenta,
                            monto_recibido: recibido,
                            cambio_devuelto: Math.max(0, recibido - totalVenta)
                        });
                    } else if (this.modalidadPago === 'simple_tarjeta') {
                        pagosArray.push({
                            forma_pago_sat: this.tipoTarjetaSimple,
                            monto: totalVenta,
                            monto_recibido: totalVenta,
                            cambio_devuelto: 0,
                            referencia_bancaria: this.referenciaTarjeta1 || null
                        });
                    } else if (this.modalidadPago === 'mixto_efectivo_tarjeta') {
                        const montoEf = Number(this.montoMixtoEfectivo) || 0;
                        const montoTar = Number(this.montoMixtoTarjeta) || 0;
                        const recibidoEf = Math.max(montoEf, Number(this.pagoEfectivoRecibido) || 0);

                        pagosArray.push({
                            forma_pago_sat: '01',
                            monto: montoEf,
                            monto_recibido: recibidoEf,
                            cambio_devuelto: Math.max(0, recibidoEf - montoEf)
                        });
                        pagosArray.push({
                            forma_pago_sat: this.tipoTarjetaMixto,
                            monto: montoTar,
                            monto_recibido: montoTar,
                            cambio_devuelto: 0,
                            referencia_bancaria: this.referenciaTarjeta1 || null
                        });
                    } else if (this.modalidadPago === 'doble_tarjeta') {
                        const m1 = Number(this.montoTarjeta1) || 0;
                        const m2 = Number(this.montoTarjeta2) || 0;

                        pagosArray.push({
                            forma_pago_sat: '04',
                            monto: m1,
                            monto_recibido: m1,
                            cambio_devuelto: 0,
                            referencia_bancaria: this.referenciaTarjeta1 || 'Tarjeta 1'
                        });
                        pagosArray.push({
                            forma_pago_sat: '28',
                            monto: m2,
                            monto_recibido: m2,
                            cambio_devuelto: 0,
                            referencia_bancaria: this.referenciaTarjeta2 || 'Tarjeta 2'
                        });
                    }

                    const payloadVenta = {
                        uuid: uuidVenta,
                        sucursal_id: this.sucursal?.id || 1,
                        almacen_id: this.almacen?.id || 1,
                        caja_turno_id: this.turnoActivo?.id || null,
                        cliente_id: this.clienteSeleccionado?.id || null,
                        cliente_nombre: this.cliente.nombre,
                        cliente_rfc: this.cliente.rfc,
                        forma_pago_sat: pagosArray[0]?.forma_pago_sat || '01',
                        comanda_ids: this.comandaIdsCobrando || [],
                        es_subcuenta: this.esSubcuentaCobrando || false,
                        comanda_item_ids_pagados: this.itemsPagadosIds || [],
                        descuento_promociones: this.descuentoPromociones,
                        monto_monedero_usado: this.montoMonederoAplicado,
                        promociones_aplicadas_ids: this.promocionesAplicadas.map(p => p.id),
                        notas: this.origenComandaMesa ? `Comanda liquidada: ${this.origenComandaMesa}` : null,
                        lineas: this.carrito.map(item => ({
                            producto_id: item.producto_id,
                            cantidad: item.cantidad,
                            precio_unitario: item.precio_unitario,
                            tasa_iva: item.tasa_iva
                        })),
                        pagos: pagosArray
                    };

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
                            this.consultarMesasAbiertas();
                            return;
                        } else {
                            alert(data.message || 'Error al procesar la venta en caja.');
                        }
                    } catch (e) {
                        console.error('Falla en petición:', e);
                        alert('Error de conexión con el servidor al registrar el cobro.');
                    } finally {
                        this.isProcessing = false;
                    }
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
                    const turnoCerradoId = this.turnoActivo.id;

                    const res = await fetch(this.routes.cierreCaja, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            caja_turno_id: turnoCerradoId,
                            monto_real_efectivo: this.formCierre.monto_real_efectivo,
                            observaciones: this.formCierre.observaciones
                        })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        window.open(`/pos/caja/${turnoCerradoId}/imprimir-corte?tipo=Z`, '_blank');
                        alert(`Turno cerrado. Diferencia de efectivo: $${data.turno.diferencia_efectivo}`);
                        this.turnoActivo = null;
                        this.modalCierre = false;
                    } else {
                        alert(data.message || 'Error cerrando caja');
                    }
                },

                async abrirModalReimpresion() {
                    this.modalReimpresion = true;
                    this.cargandoVentas = true;
                    try {
                        const sucursalId = this.sucursal?.id || 1;
                        const turnoId = this.turnoActivo?.id || '';
                        const res = await fetch(`${this.routes.ultimasVentas}?sucursal_id=${sucursalId}&caja_turno_id=${turnoId}`, {
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

                async abrirModalFacturacion() {
                    this.modalFacturacion = true;
                    this.mensajeFactura = '';
                    await this.cargarVentasParaFacturar();
                },

                async cargarVentasParaFacturar() {
                    this.cargandoFacturacion = true;
                    try {
                        const sucursalId = this.sucursal?.id || 1;
                        const turnoId = this.turnoActivo?.id || '';
                        const res = await fetch(`/pos/ultimas-ventas?sucursal_id=${sucursalId}&caja_turno_id=${turnoId}`, {
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