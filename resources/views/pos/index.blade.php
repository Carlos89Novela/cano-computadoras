<x-app-layout>
    {{-- ===================================================================== --}}
    {{-- TÍTULO DE LA PESTAÑA DEL NAVEGADOR                                    --}}
    {{-- ===================================================================== --}}
    @section('title', 'Punto de Venta | ' . ($sucursal['nombre'] ?? 'La Fogata Bistro'))
    <x-slot name="title">
        Punto de Venta | {{ $sucursal['nombre'] ?? 'La Fogata Bistro' }}
    </x-slot>

    {{-- ===================================================================== --}}
    {{-- CONTENEDOR RAÍZ DEL PUNTO DE VENTA (ALPINE.JS)                        --}}
    {{-- ===================================================================== --}}
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
            comandasAbiertas: '{{ url('/pos/restaurante/comandas-abiertas') }}',
            alertasPrecuenta: '{{ route('pos.restaurante.alertas-precuenta') }}'
        },
        csrfToken: '{{ csrf_token() }}'
    })" x-init="initPos()" class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6">

        {{-- ===================================================================== --}}
        {{-- SECCIÓN 1: ENCABEZADO SUPERIOR, CONECTIVIDAD Y ACCIONES DE CAJA     --}}
        {{-- ===================================================================== --}}
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

            {{-- Controles Superiores de Caja y Restaurante --}}
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                {{-- Botón para activar y probar el timbre sonoro de precuenta en caja --}}
                <button type="button"
                        @click="probarTimbrePrecuenta()"
                        title="Activar y probar timbre de precuenta"
                        class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-amber-300 border border-slate-800 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer">
                    <span>🔔</span>
                    <span x-text="posAudioActivo ? 'Timbre OK' : 'Activar Audio'"></span>
                </button>

                {{-- Indicador de ventas pendientes por sincronizar en modo offline --}}
                <template x-if="offlineQueueCount > 0">
                    <button @click="sincronizarLoteOffline()" :disabled="!isOnline || isSyncing"
                            class="px-3 py-1.5 bg-amber-500/20 border border-amber-500/40 text-amber-300 rounded-lg text-xs font-medium flex items-center gap-2 hover:bg-amber-500/30 transition">
                        <svg class="w-4 h-4" :class="isSyncing ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span x-text="`${offlineQueueCount} ventas pendientes`"></span>
                    </button>
                </template>

                {{-- Botón para abrir turno cuando la caja está cerrada --}}
                <template x-if="!turnoActivo">
                    <button @click="modalApertura = true" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold shadow transition">
                        + Abrir Turno de Caja
                    </button>
                </template>

                {{-- Acciones operativas disponibles con turno de caja abierto --}}
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

        {{-- ===================================================================== --}}
        {{-- BANNER DE ALERTAS DE PRECUENTA SOLICITADAS DESDE EL COMANDERO       --}}
        {{-- ===================================================================== --}}
        <template x-if="alertasPrecuentaPos.length > 0 || mesasConCuentaPedida.length > 0">
            <div class="mt-3 p-3 rounded-xl bg-amber-500/15 border border-amber-400/50 flex flex-wrap items-center justify-between gap-2 shadow-lg">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase tracking-wider text-amber-300 animate-pulse">
                        🧾 Precuenta solicitada en comandero:
                    </span>

                    {{-- Alertas recibidas en caché desde el comandero --}}
                    <template x-for="alerta in alertasPrecuentaPos" :key="alerta.id">
                        <div class="px-2.5 py-1 rounded-lg bg-slate-950/90 border border-amber-400/50 text-xs font-mono font-bold text-white flex items-center gap-2">
                            <button type="button"
                                    @click="cargarMesaPorNombre(alerta.mesa)"
                                    class="text-amber-300 hover:underline cursor-pointer"
                                    x-text="alerta.mesa"></button>
                            <span class="text-[10px] text-slate-400" x-text="alerta.hora"></span>
                            <button type="button"
                                    @click="descartarAlertaPrecuenta(alerta.id)"
                                    class="text-rose-400 hover:text-rose-300 ml-1 cursor-pointer">&times;</button>
                        </div>
                    </template>

                    {{-- Mesas activas marcadas con cuenta pedida en comandas --}}
                    <template x-for="m in mesasConCuentaPedida" :key="'mesa_pedida_' + m.mesa">
                        <button type="button"
                                @click="cargarMesaAlCarrito(m)"
                                class="px-2.5 py-1 rounded-lg bg-cyan-950/90 border border-cyan-400/60 text-xs font-mono font-bold text-cyan-200 hover:bg-cyan-900 flex items-center gap-1.5 cursor-pointer">
                            <span>🔔</span>
                            <span x-text="m.mesa"></span>
                            <span class="text-emerald-400" x-text="'$' + Number(m.total || 0).toFixed(2)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- ===================================================================== --}}
        {{-- SECCIÓN 2: ALERTA DE CAJA CERRADA                                  --}}
        {{-- ===================================================================== --}}
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

        {{-- ===================================================================== --}}
        {{-- SECCIÓN 3: TERMINAL OPERATIVA (PLANO / CATÁLOGO A IZQUIERDA, TICKET)  --}}
        {{-- ===================================================================== --}}
        <template x-if="turnoActivo">
            <div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- Columna Izquierda: Plano de Mesas (Restaurante) o Catálogo (Comercio/Taller) --}}
                <div class="lg:col-span-7 space-y-4">
                    @if(isset($esRestaurante) && $esRestaurante)
                        {{-- Selector de Áreas y Actualización Manual --}}
                        <div class="flex items-center justify-between gap-2 bg-slate-900 border border-slate-800 p-2.5 rounded-xl">
                            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                                <template x-for="area in (areasMesas || [])" :key="area.id">
                                    <button type="button" @click="areaSeleccionadaId = area.id"
                                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition whitespace-nowrap cursor-pointer"
                                            :class="areaSeleccionadaId === area.id ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                                            x-text="area.nombre"></button>
                                </template>
                            </div>
                            <button type="button" @click="consultarMesasAbiertas()" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs transition flex items-center gap-1 shrink-0">
                                <span>🔄</span>
                            </button>
                        </div>

                        {{-- Leyenda de Estados de Mesa en el Plano --}}
                        <div class="flex items-center gap-3 text-[10px] text-slate-400">
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-slate-700"></span> Libre</span>
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Ocupada</span>
                            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400 animate-pulse"></span> Precuenta Pedida</span>
                        </div>

                        {{-- Lienzo Gráfico 2D de Mesas --}}
                        <div class="relative w-full h-[580px] bg-slate-950 border-2 border-slate-800/80 rounded-2xl overflow-hidden shadow-inner p-2 select-none"
                             style="background-image: radial-gradient(#1e293b 1px, transparent 1px); background-size: 24px 24px;">

                            <template x-if="!mesasAreaActual || mesasAreaActual.length === 0">
                                <div class="h-full flex flex-col items-center justify-center text-slate-500 text-xs">
                                    <span class="text-3xl mb-1">🪑</span>
                                    <span>No hay mesas cargadas para esta área.</span>
                                </div>
                            </template>

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

                        {{-- Barra Inferior para la Mesa Seleccionada --}}
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
                        {{-- Buscador y Catálogo para Comercio / Taller --}}
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

                {{-- Columna Derecha: Ticket de Venta y Panel de Cobro --}}
                <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span>🧾 Ticket de Venta</span>
                            <span x-show="origenComandaMesa" class="text-[10px] bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 px-2 py-0.5 rounded-full font-mono" x-text="origenComandaMesa"></span>
                        </h2>
                        <button @click="vaciarCarrito()" class="text-xs text-rose-400 hover:underline cursor-pointer">Vaciar</button>
                    </div>

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

                    {{-- Desglose de Importes e Impuestos --}}
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

                    {{-- Método de Pago y Botón de Cobro --}}
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

                        <button @click="procesarVenta()" :disabled="carrito.length === 0 || isProcessing"
                                class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-600/20 transition flex items-center justify-center gap-2 cursor-pointer">
                            <span x-show="!isProcessing">Cobrar e Imprimir Ticket</span>
                            <span x-show="isProcessing">Procesando y liberando mesa...</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- ===================================================================== --}}
        {{-- SECCIÓN 4: MODAL DE MESAS ABIERTAS CON CONSUMO (RESTAURANTE)      --}}
        {{-- ===================================================================== --}}
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

        {{-- ===================================================================== --}}
        {{-- SECCIÓN 5: MODAL DE DIVISIÓN DE CUENTA                                --}}
        {{-- ===================================================================== --}}
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

        {{-- ===================================================================== --}}
        {{-- SECCIÓN 6: MODALES DE CAJA, REIMPRESIÓN Y FACTURACIÓN CFDI 4.0      --}}
        {{-- ===================================================================== --}}
        {{-- Modal de Apertura de Turno --}}
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

        {{-- Modal de Corte Z y Cierre de Turno --}}
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

        {{-- Modal de Reimpresión de Tickets (Filtrado por Rol / Turno) --}}
        <div x-show="modalReimpresion" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalReimpresion = false" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-xl w-full space-y-4 shadow-2xl max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>🖨️ Reimprimir Ticket de Venta</span>
                    </h3>
                    <button type="button" @click="modalReimpresion = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <div class="flex-1 overflow-y-auto space-y-2 pr-1">
                    <template x-if="cargandoVentas">
                        <p class="text-center text-xs text-slate-400 py-8">Cargando últimas ventas...</p>
                    </template>

                    <template x-if="!cargandoVentas && ventasRecientes.length === 0">
                        <p class="text-center text-xs text-slate-500 py-8">No hay ventas recientes registradas en esta sucursal.</p>
                    </template>

                    <template x-for="v in ventasRecientes" :key="v.id">
                        <div class="p-3 bg-slate-950 border border-slate-800 rounded-xl flex items-center justify-between gap-3 text-xs">
                            <div>
                                <div class="font-bold text-white flex items-center gap-2">
                                    <span class="font-mono text-indigo-400" x-text="v.folio"></span>
                                    <span x-text="v.cliente_nombre || 'Público General'"></span>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-0.5" x-text="formatearFecha(v.created_at)"></div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-bold text-emerald-400 text-sm" x-text="`$${Number(v.total || 0).toFixed(2)}`"></span>
                                <button type="button"
                                        @click="lanzarImpresion(v.id)"
                                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition cursor-pointer">
                                    Imprimir
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="pt-3 border-t border-slate-800 flex justify-end">
                    <button type="button" @click="modalReimpresion = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-300">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        {{-- Modal de Facturación Electrónica SAT (CFDI 4.0) --}}
        <div x-show="modalFacturacion" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalFacturacion = false" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-2xl w-full space-y-4 shadow-2xl max-h-[88vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>📄 Facturación Electrónica SAT (CFDI 4.0)</span>
                        </h3>
                        <p class="text-[11px] text-slate-400">Asigna cliente fiscal si el ticket usa RFC genérico, timbra CFDI o descarga XML y PDF.</p>
                    </div>
                    <button type="button" @click="modalFacturacion = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                {{-- Mensaje de estado o confirmación de facturación / alta de cliente --}}
                <template x-if="mensajeFactura">
                    <div class="p-3 rounded-xl text-xs font-semibold border"
                         :class="mensajeErrorFactura ? 'bg-rose-500/15 border-rose-500/40 text-rose-300' : 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300'"
                         x-text="mensajeFactura"></div>
                </template>

                {{-- Listado de tickets recientes --}}
                <div class="flex-1 overflow-y-auto space-y-2.5 pr-1">
                    <template x-if="cargandoFacturacion">
                        <p class="text-center text-xs text-slate-400 py-8">Cargando tickets disponibles...</p>
                    </template>

                    <template x-if="!cargandoFacturacion && ventasFacturables.length === 0">
                        <p class="text-center text-xs text-slate-500 py-8">No hay tickets recientes disponibles para facturar.</p>
                    </template>

                    <template x-for="v in ventasFacturables" :key="'cfdi_' + v.id">
                        <div class="p-3.5 bg-slate-950 border border-slate-800 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-indigo-400" x-text="v.folio"></span>
                                    <span class="font-semibold text-white" x-text="v.cliente_nombre || 'Público General'"></span>
                                    <span class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-300"
                                          x-text="v.cliente_rfc || 'XAXX010101000'"></span>
                                </div>
                                <div class="text-[11px] text-slate-400 flex items-center gap-2">
                                    <span x-text="formatearFecha(v.created_at)"></span>
                                    <span>•</span>
                                    <span class="font-mono font-bold text-emerald-400" x-text="`$${Number(v.total || 0).toFixed(2)}`"></span>
                                </div>
                                <template x-if="v.estado_facturacion === 'facturada' && v.uuid_fiscal">
                                    <div class="text-[10px] font-mono text-emerald-400 truncate">
                                        UUID: <span x-text="v.uuid_fiscal"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <template x-if="v.estado_facturacion !== 'facturada'">
                                    <div class="flex items-center gap-2">
                                        {{-- Si el RFC está vacío o es genérico, mostrar botón para dar de alta al cliente primero --}}
                                        <template x-if="!v.cliente_rfc || v.cliente_rfc === 'XAXX010101000'">
                                            <button type="button"
                                                    @click="abrirAltaClienteDesdeTicket(v)"
                                                    class="px-3.5 py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-lg transition cursor-pointer">
                                                + Alta Cliente
                                            </button>
                                        </template>

                                        {{-- Si ya tiene un RFC distinto a XAXX010101000, habilitar timbrado directo --}}
                                        <template x-if="v.cliente_rfc && v.cliente_rfc !== 'XAXX010101000'">
                                            <button type="button"
                                                    @click="timbrarVenta(v)"
                                                    :disabled="timbrandoId === v.id"
                                                    class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold rounded-lg transition cursor-pointer">
                                                <span x-show="timbrandoId !== v.id">Timbrar CFDI</span>
                                                <span x-show="timbrandoId === v.id">Timbrando...</span>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="v.estado_facturacion === 'facturada'">
                                    <div class="flex items-center gap-2">
                                        <a :href="`/operacion/facturacion/ventas/${v.id}/descargar-xml`"
                                           target="_blank"
                                           class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-semibold rounded-lg transition">
                                            XML
                                        </a>
                                        <a :href="`/operacion/facturacion/ventas/${v.id}/descargar-pdf`"
                                           target="_blank"
                                           class="px-3 py-1.5 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 font-semibold rounded-lg transition">
                                            PDF
                                        </a>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
                    <a href="{{ route('facturacion.global.index') }}"
                       class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold">
                        Ir a Facturación Global →
                    </a>
                    <button type="button" @click="modalFacturacion = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-300">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        {{-- Modal Rápido de Alta de Cliente Fiscal para Facturación --}}
        <div x-show="modalAltaClienteCfdi" class="fixed inset-0 bg-black/85 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalAltaClienteCfdi = false" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-md w-full space-y-4 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-white">Alta de Cliente Fiscal</h3>
                        <p class="text-[11px] text-slate-400">
                            Ticket: <span class="font-mono text-indigo-400" x-text="ventaParaCliente?.folio || ''"></span>
                        </p>
                    </div>
                    <button type="button" @click="modalAltaClienteCfdi = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="text-slate-300 block mb-1 font-medium">Nombre o Razón Social *</label>
                        <input type="text" x-model="formClienteCfdi.nombre"
                               placeholder="Ej. Juan Pérez / Comercializadora SA"
                               class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-slate-300 block mb-1 font-medium">RFC *</label>
                            <input type="text" x-model="formClienteCfdi.rfc" maxlength="13"
                                   placeholder="XAXX010101000"
                                   class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white uppercase font-mono focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-slate-300 block mb-1 font-medium">Código Postal</label>
                            <input type="text" x-model="formClienteCfdi.codigo_postal" maxlength="5"
                                   placeholder="83000"
                                   class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="text-slate-300 block mb-1 font-medium">Teléfono / WhatsApp</label>
                        <input type="text" x-model="formClienteCfdi.telefono_whatsapp"
                               placeholder="Opcional"
                               class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <div class="flex gap-2 pt-2 border-t border-slate-800">
                    <button type="button" @click="modalAltaClienteCfdi = false"
                            class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold">
                        Cancelar
                    </button>
                    <button type="button" @click="guardarClienteCfdi()"
                            :disabled="guardandoClienteCfdi"
                            class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition cursor-pointer">
                        <span x-show="!guardandoClienteCfdi">Guardar y Habilitar Timbrado</span>
                        <span x-show="guardandoClienteCfdi">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 7: LÓGICA ALPINE.JS DEL POS, TIMBRE, PRECUENTAS Y CFDI      --}}
    {{-- ===================================================================== --}}
    <script>
        function posTerminal(config) {
            return {
                // Configuración de contexto operativo y rutas
                sucursal: config.sucursal || { id: 1, nombre: 'Sucursal Principal' },
                almacen: config.almacen || { id: 1, nombre: 'Almacén General' },
                turnoActivo: config.turnoInicial || null,
                areas: config.areasIniciales || [],
                areaSeleccionadaId: (config.areasIniciales && config.areasIniciales.length > 0) ? config.areasIniciales[0].id : null,
                routes: config.routes,
                csrf: config.csrfToken,

                // Conectividad y estado de procesos
                isOnline: navigator.onLine,
                isSyncing: false,
                isProcessing: false,
                offlineQueueCount: 0,

                // Modales y formularios de caja
                modalApertura: false,
                modalCierre: false,
                formApertura: { monto_inicial: 1000, observaciones: '' },
                formCierre: { monto_real_efectivo: 0, observaciones: '' },

                // Carrito de compras y cobro
                searchQuery: '',
                productos: [],
                carrito: [],
                cliente: { nombre: 'Público General', rfc: 'XAXX010101000' },
                formaPagoSat: '01',
                pagoEfectivoRecibido: 0,

                // Estado de mesas y comandas de restaurante
                promocionesVigentes: [],
                modalMesasPos: false,
                cargandoMesas: false,
                mesasAbiertas: [],
                origenComandaMesa: '',
                comandaIdsCobrando: [],
                esSubcuentaCobrando: false,
                itemsPagadosIds: [],
                mesaSeleccionadaActiva: null,

                // Estado de división de cuentas (Split Bill)
                modalidadPago: 'simple_efectivo',
                modalDividir: false,
                mesaDividiendo: null,
                partidasDivision: [],
                modoDivision: 'items',
                numeroPartesIguales: 2,

                // Estado de reimpresión de tickets y facturación CFDI
                modalReimpresion: false,
                cargandoVentas: false,
                ventasRecientes: [],
                modalFacturacion: false,
                cargandoFacturacion: false,
                ventasFacturables: [],
                timbrandoId: null,
                mensajeFactura: '',
                mensajeErrorFactura: false,
                db: null,

                // Audio y alertas de precuenta desde el comandero
                posAudioCtx: null,
                posAudioActivo: false,
                precuentasVistas: new Set(),
                alertasPrecuentaPos: [],

                // Sub-modal de alta rápida de cliente fiscal
                modalAltaClienteCfdi: false,
                guardandoClienteCfdi: false,
                ventaParaCliente: null,
                formClienteCfdi: {
                    nombre: '',
                    rfc: '',
                    codigo_postal: '',
                    telefono_whatsapp: ''
                },

                get hayCuentasPedidas() {
                    return (this.mesasAbiertas || []).some(m => Boolean(m.cuenta_pedida)) || this.alertasPrecuentaPos.length > 0;
                },

                get mesasConCuentaPedida() {
                    return (this.mesasAbiertas || []).filter(m => Boolean(m.cuenta_pedida));
                },

                // Inicializa título, audio, catálogo, mesas abiertas y escucha de precuentas
                async initPos() {
                    const tituloPos = `Punto de Venta | ${this.sucursal?.nombre || 'La Fogata Bistro'}`;
                    document.title = tituloPos;
                    setTimeout(() => { document.title = tituloPos; }, 100);

                    window.addEventListener('pointerdown', () => this.desbloquearAudioPos(), { once: true });
                    window.addEventListener('online', () => { this.isOnline = true; this.sincronizarLoteOffline(); });
                    window.addEventListener('offline', () => { this.isOnline = false; });

                    try { await this.initIndexedDB(); } catch (err) {}
                    await this.cargarCatalogo();
                    await this.cargarPromocionesVigentes();
                    await this.actualizarContadorOffline();

                    if (this.routes.comandasAbiertas) {
                        this.consultarMesasAbiertas();
                        setInterval(() => this.consultarMesasAbiertas(), 5000);
                    }

                    this.iniciarEscuchaPrecuentas();
                },

                // Normaliza nombres de mesa ("Mesa 1" <-> "1") para comparaciones consistentes
                normalizarMesa(nombre) {
                    return String(nombre || '')
                        .toLowerCase()
                        .replace(/^mesa\s+/i, '')
                        .trim();
                },

                // Desbloquea AudioContext del navegador tras interacción del usuario
                async desbloquearAudioPos() {
                    try {
                        if (!this.posAudioCtx) {
                            const AudioCtx = window.AudioContext || window.webkitAudioContext;
                            if (AudioCtx) {
                                this.posAudioCtx = new AudioCtx();
                            }
                        }
                        if (this.posAudioCtx && this.posAudioCtx.state === 'suspended') {
                            await this.posAudioCtx.resume();
                        }
                        if (this.posAudioCtx && this.posAudioCtx.state === 'running') {
                            this.posAudioActivo = true;
                        }
                    } catch (e) {
                        console.log('Audio POS no disponible:', e);
                    }
                },

                // Prueba manual del timbre de precuenta desde el encabezado
                probarTimbrePrecuenta() {
                    this.desbloquearAudioPos();
                    this.reproducirTimbrePrecuenta();
                },

                // Reproduce doble tono de alerta de precuenta mediante Web Audio API
                reproducirTimbrePrecuenta() {
                    try {
                        this.desbloquearAudioPos();
                        if (!this.posAudioCtx || this.posAudioCtx.state !== 'running') return;

                        const now = this.posAudioCtx.currentTime;
                        const osc = this.posAudioCtx.createOscillator();
                        const gain = this.posAudioCtx.createGain();

                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(1046.5, now);
                        osc.frequency.setValueAtTime(1318.5, now + 0.16);

                        gain.gain.setValueAtTime(0.22, now);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.75);

                        osc.connect(gain);
                        gain.connect(this.posAudioCtx.destination);

                        osc.start(now);
                        osc.stop(now + 0.8);
                    } catch (e) {
                        console.log('Error al reproducir timbre de precuenta:', e);
                    }
                },

                // Consulta periódicamente alertas de precuenta enviadas desde el comandero
                iniciarEscuchaPrecuentas() {
                    if (!this.routes.alertasPrecuenta) return;

                    const revisar = async () => {
                        if (!this.isOnline) return;
                        try {
                            const sucursalId = this.sucursal?.id || 1;
                            const res = await fetch(`${this.routes.alertasPrecuenta}?sucursal_id=${sucursalId}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            if (!res.ok) return;

                            const data = await res.json();
                            const alertas = Array.isArray(data) ? data : [];

                            alertas.forEach(alerta => {
                                if (!alerta?.id || this.precuentasVistas.has(alerta.id)) return;
                                this.precuentasVistas.add(alerta.id);
                                this.reproducirTimbrePrecuenta();
                                this.alertasPrecuentaPos.unshift({
                                    id: alerta.id,
                                    mesa: alerta.mesa || 'Mesa',
                                    hora: alerta.hora || new Date().toLocaleTimeString()
                                });
                                this.consultarMesasAbiertas();
                            });
                        } catch (e) {
                            console.error('Error consultando precuentas en POS:', e);
                        }
                    };

                    revisar();
                    setInterval(revisar, 3000);
                },

                // Carga el consumo de una mesa al hacer clic en una alerta de precuenta
                cargarMesaPorNombre(nombreMesa) {
                    const target = this.normalizarMesa(nombreMesa);
                    const mesaData = (this.mesasAbiertas || []).find(
                        m => this.normalizarMesa(m.mesa) === target
                    );
                    if (mesaData) {
                        this.cargarMesaAlCarrito(mesaData);
                    } else {
                        this.cliente.nombre = nombreMesa;
                        this.consultarMesasAbiertas();
                    }
                },

                descartarAlertaPrecuenta(alertaId) {
                    this.alertasPrecuentaPos = this.alertasPrecuentaPos.filter(a => a.id !== alertaId);
                },

                async cargarPromocionesVigentes() {
                    try {
                        const res = await fetch('/marketing/promociones/vigentes-api', { headers: { 'Accept': 'application/json' } });
                        if (res.ok) this.promocionesVigentes = await res.json();
                    } catch (e) {}
                },

                get mesasAreaActual() {
                    const area = this.areas.find(a => a.id === this.areaSeleccionadaId);
                    return area ? (area.mesas || []) : [];
                },

                obtenerDatosComandaMesa(mesa) {
                    if (!this.mesasAbiertas || !Array.isArray(this.mesasAbiertas) || !mesa) return null;
                    const target = this.normalizarMesa(mesa.numero_o_nombre || mesa.nombre || mesa.numero);
                    return this.mesasAbiertas.find(m => this.normalizarMesa(m.mesa) === target) || null;
                },

                mesaTieneConsumo(mesa) {
                    const data = this.obtenerDatosComandaMesa(mesa);
                    return data !== undefined && data !== null;
                },

                mesaTieneCuentaPedida(mesa) {
                    const data = this.obtenerDatosComandaMesa(mesa);
                    return data && Boolean(data.cuenta_pedida);
                },

                obtenerTotalConsumoMesa(mesa) {
                    const data = this.obtenerDatosComandaMesa(mesa);
                    return data ? (Number(data.total) || 0) : 0;
                },

                obtenerEstiloMesaPlano(mesa) {
                    const esPedida = this.mesaTieneCuentaPedida(mesa);
                    const tieneConsumo = this.mesaTieneConsumo(mesa);
                    const esFormaRedonda = mesa.forma === 'redonda';
                    let base = esFormaRedonda ? 'rounded-full w-24 h-24' : 'rounded-2xl w-24 h-24';
                    if (esPedida) return `${base} bg-cyan-950/85 border-cyan-400 text-cyan-200 ring-4 ring-cyan-500/50 animate-pulse`;
                    if (tieneConsumo) return `${base} bg-amber-950/70 border-amber-500/80 text-amber-200 ring-2 ring-amber-500/30`;
                    return `${base} bg-slate-900 border-slate-800 text-slate-400 opacity-80`;
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

                async consultarMesasAbiertas() {
                    if (!this.routes.comandasAbiertas || !this.isOnline) return;
                    try {
                        const res = await fetch(this.routes.comandasAbiertas, { headers: { 'Accept': 'application/json' } });
                        if (res.ok) {
                            const data = await res.json();
                            this.mesasAbiertas = Array.isArray(data) ? data : [];
                        }
                    } catch (e) {}
                },

                abrirModalMesasRestaurante() {
                    this.modalMesasPos = true;
                    this.consultarMesasAbiertas();
                },

                cargarMesaAlCarrito(mesaData) {
                    this.origenComandaMesa = `${mesaData.mesa} - Folios: ${mesaData.folio}`;
                    this.comandaIdsCobrando = mesaData.comanda_ids || [];
                    this.cliente.nombre = `${mesaData.mesa}`;
                    this.carrito = (mesaData.items || []).map(it => ({
                        producto_id: it.producto_id,
                        sku: it.sku || 'CMD',
                        nombre: it.nombre,
                        precio_unitario: Number(it.precio_unitario),
                        cantidad: Number(it.cantidad),
                        tasa_iva: 0.1600,
                        notas: it.notas || ''
                    }));
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                    this.modalMesasPos = false;
                },

                abrirModalDividirCuenta(mesaData) {
                    this.mesaDividiendo = mesaData;
                    this.partidasDivision = (mesaData.items || []).map(it => ({ ...it, seleccionado: true }));
                    this.modalMesasPos = false;
                    this.modalDividir = true;
                },

                calcularTotalSubcuenta() {
                    if (this.modoDivision === 'partes_iguales') {
                        const total = Number(this.mesaDividiendo?.total || 0);
                        const n = Math.max(1, Number(this.numeroPartesIguales || 2));
                        return total / n;
                    }
                    return (this.partidasDivision || []).filter(i => i.seleccionado).reduce((sum, i) => sum + ((Number(i.cantidad) || 0) * (Number(i.precio_unitario) || 0)), 0);
                },

                cargarSubcuentaAlPOS() {
                    const totalSub = this.calcularTotalSubcuenta();
                    if (totalSub <= 0) return;
                    this.esSubcuentaCobrando = true;
                    this.origenComandaMesa = `${this.mesaDividiendo?.mesa || 'Mesa'} (Subcuenta)`;
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
                            notas: `Abono cuenta dividida`
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
                    this.modalDividir = false;
                },

                get descuentoPromociones() { return 0; },
                get montoMonederoAplicado() { return 0; },

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

                incrementarCantidad(index) { this.carrito[index].cantidad += 1; this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2)); },
                decrementarCantidad(index) {
                    if (this.carrito[index].cantidad > 1) this.carrito[index].cantidad -= 1;
                    else this.removerDelCarrito(index);
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                },
                removerDelCarrito(index) { this.carrito.splice(index, 1); this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2)); },
                vaciarCarrito() {
                    this.carrito = [];
                    this.origenComandaMesa = '';
                    this.comandaIdsCobrando = [];
                    this.esSubcuentaCobrando = false;
                    this.itemsPagadosIds = [];
                    this.cliente = { nombre: 'Público General', rfc: 'XAXX010101000' };
                },

                async cargarCatalogo() {
                    const almacenId = this.almacen?.id || 1;
                    try {
                        const res = await fetch(`${this.routes.catalogo}?almacen_id=${almacenId}`, { headers: { 'Accept': 'application/json' } });
                        if (res.ok) {
                            const data = await res.json();
                            if (Array.isArray(data)) this.productos = data;
                        }
                    } catch (e) {}
                },

                get productosFiltrados() {
                    if (!this.searchQuery.trim()) return (this.productos || []).slice(0, 30);
                    const q = this.searchQuery.toLowerCase();
                    return (this.productos || []).filter(p => (p.nombre && p.nombre.toLowerCase().includes(q)) || (p.sku && p.sku.toLowerCase().includes(q)));
                },

                buscarYAgregarDirecto() {
                    const filtrados = this.productosFiltrados;
                    if (filtrados.length > 0) {
                        this.agregarAlCarrito(filtrados[0]);
                        this.searchQuery = '';
                    }
                },

                generarUUID() {
                    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                        const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                        return v.toString(16);
                    });
                },

                // Envía el cobro al servidor, abre el ticket térmico y refresca mesas abiertas
                async procesarVenta() {
                    if (this.carrito.length === 0) return;
                    this.isProcessing = true;
                    const uuidVenta = this.generarUUID();
                    const totalVenta = Number(this.totales.total.toFixed(2));
                    const recibido = Math.max(totalVenta, Number(this.pagoEfectivoRecibido) || 0);

                    const payloadVenta = {
                        uuid: uuidVenta,
                        sucursal_id: this.sucursal?.id || 1,
                        almacen_id: this.almacen?.id || 1,
                        caja_turno_id: this.turnoActivo?.id || null,
                        cliente_nombre: this.cliente.nombre,
                        cliente_rfc: this.cliente.rfc,
                        forma_pago_sat: this.formaPagoSat,
                        comanda_ids: this.comandaIdsCobrando || [],
                        es_subcuenta: this.esSubcuentaCobrando || false,
                        comanda_item_ids_pagados: this.itemsPagadosIds || [],
                        notas: this.origenComandaMesa ? `Comanda liquidada: ${this.origenComandaMesa}` : null,
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
                            cambio_devuelto: Math.max(0, recibido - totalVenta)
                        }]
                    };

                    try {
                        const res = await fetch(this.routes.ventaStore, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                            body: JSON.stringify(payloadVenta)
                        });
                        const data = await res.json();
                        if (res.ok) {
                            window.open(`/pos/ticket/${data.venta_id || uuidVenta}`, '_blank');
                            this.vaciarCarrito();
                            this.consultarMesasAbiertas();
                            this.isProcessing = false;
                            return;
                        }
                        alert(data.message || 'Error al procesar cobro.');
                    } catch (e) {
                        alert('Error de conexión con el servidor.');
                    } finally {
                        this.isProcessing = false;
                    }
                },

                async abrirTurnoCaja() {
                    const res = await fetch(this.routes.aperturaCaja, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ sucursal_id: this.sucursal?.id || 1, monto_inicial: this.formApertura.monto_inicial, observaciones: this.formApertura.observaciones })
                    });
                    const data = await res.json();
                    if (res.ok) { this.turnoActivo = data.turno; this.modalApertura = false; }
                    else alert(data.message || 'Error abriendo caja');
                },

                async cerrarTurnoCaja() {
                    if (!this.turnoActivo) return;
                    const turnoCerradoId = this.turnoActivo.id;
                    const res = await fetch(this.routes.cierreCaja, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ caja_turno_id: turnoCerradoId, monto_real_efectivo: this.formCierre.monto_real_efectivo, observaciones: this.formCierre.observaciones })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        window.open(`/pos/caja/${turnoCerradoId}/imprimir-corte?tipo=Z`, '_blank');
                        this.turnoActivo = null;
                        this.modalCierre = false;
                    } else alert(data.message || 'Error cerrando caja');
                },

                // Consulta tickets recientes filtrados por rol / turno en backend
                abrirModalReimpresion() { this.modalReimpresion = true; this.cargarVentasRecientes(); },
                async cargarVentasRecientes() {
                    this.cargandoVentas = true;
                    try {
                        const sucursalId = this.sucursal?.id || 1;
                        const turnoId = this.turnoActivo?.id || 0;
                        const res = await fetch(`${this.routes.ultimasVentas}?sucursal_id=${sucursalId}&caja_turno_id=${turnoId}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) this.ventasRecientes = await res.json();
                    } catch (e) {} finally { this.cargandoVentas = false; }
                },
                lanzarImpresion(id) { window.open(`/pos/ticket/${id}`, '_blank'); },

                // Abre el modal de facturación CFDI y carga los tickets recientes
                abrirModalFacturacion() {
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;
                    this.modalFacturacion = true;
                    this.cargarVentasParaFacturar();
                },

                async cargarVentasParaFacturar() {
                    this.cargandoFacturacion = true;
                    try {
                        const sucursalId = this.sucursal?.id || 1;
                        const turnoId = this.turnoActivo?.id || 0;
                        const res = await fetch(`${this.routes.ultimasVentas}?sucursal_id=${sucursalId}&caja_turno_id=${turnoId}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) this.ventasFacturables = await res.json();
                    } catch (e) {} finally {
                        this.cargandoFacturacion = false;
                    }
                },

                // Abre el sub-modal para registrar un cliente fiscal cuando el ticket usa RFC genérico
                abrirAltaClienteDesdeTicket(venta) {
                    this.ventaParaCliente = venta;
                    this.formClienteCfdi = {
                        nombre: venta.cliente_nombre && venta.cliente_nombre !== 'Público General' ? venta.cliente_nombre : '',
                        rfc: venta.cliente_rfc && venta.cliente_rfc !== 'XAXX010101000' ? venta.cliente_rfc : '',
                        codigo_postal: '',
                        telefono_whatsapp: ''
                    };
                    this.modalAltaClienteCfdi = true;
                },

                // Guarda el cliente fiscal y habilita el botón "Timbrar CFDI" en el ticket
                async guardarClienteCfdi() {
                    const nombre = (this.formClienteCfdi.nombre || '').trim();
                    const rfc = (this.formClienteCfdi.rfc || '').trim().toUpperCase();

                    if (!nombre || !rfc || rfc === 'XAXX010101000') {
                        this.mensajeFactura = 'Ingresa un nombre/razón social y un RFC válido distinto a XAXX010101000.';
                        this.mensajeErrorFactura = true;
                        this.modalAltaClienteCfdi = false;
                        return;
                    }

                    this.guardandoClienteCfdi = true;
                    try {
                        const res = await fetch('/marketing/promociones/clientes', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                nombre: nombre,
                                rfc: rfc,
                                codigo_postal: this.formClienteCfdi.codigo_postal || null,
                                telefono_whatsapp: this.formClienteCfdi.telefono_whatsapp || null
                            })
                        });

                        if (res.ok && this.ventaParaCliente) {
                            this.ventaParaCliente.cliente_nombre = nombre;
                            this.ventaParaCliente.cliente_rfc = rfc;
                            this.mensajeFactura = `Cliente "${nombre}" (${rfc}) dado de alta. Ya puedes timbrar el ticket.`;
                            this.mensajeErrorFactura = false;
                        } else {
                            const errData = await res.json().catch(() => ({}));
                            this.mensajeFactura = errData.message || 'No se pudo guardar el cliente.';
                            this.mensajeErrorFactura = true;
                        }
                    } catch (e) {
                        this.mensajeFactura = 'Error de conexión al guardar el cliente.';
                        this.mensajeErrorFactura = true;
                    } finally {
                        this.guardandoClienteCfdi = false;
                        this.modalAltaClienteCfdi = false;
                    }
                },

                // Solicita el timbrado CFDI 4.0 de una venta ya con cliente/RFC asignado
                async timbrarVenta(venta) {
                    if (!venta || this.timbrandoId) return;
                    this.timbrandoId = venta.id;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;

                    try {
                        const res = await fetch(`/operacion/facturacion/ventas/${venta.id}/timbrar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();

                        if (res.ok) {
                            this.mensajeFactura = data.message || `Factura timbrada (${data.uuid}).`;
                            this.mensajeErrorFactura = false;
                            await this.cargarVentasParaFacturar();
                        } else {
                            this.mensajeFactura = data.message || 'No se pudo timbrar la venta.';
                            this.mensajeErrorFactura = true;
                        }
                    } catch (e) {
                        this.mensajeFactura = 'Error de conexión al intentar timbrar.';
                        this.mensajeErrorFactura = true;
                    } finally {
                        this.timbrandoId = null;
                    }
                },

                formatearFecha(f) {
                    return f ? new Date(f).toLocaleString() : '';
                }
            };
        }
    </script>
</x-app-layout>
