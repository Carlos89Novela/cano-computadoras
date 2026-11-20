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
                        {{-- Abre el modal de mesas con consumo abierto --}}
                        <button type="button"
                                @click="abrirModalMesasRestaurante()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 text-xs font-bold rounded-lg border border-amber-500/30 transition relative cursor-pointer">
                            <span>🪑 Mesas Abiertas</span>
                            <span class="px-1.5 py-0.2 bg-amber-500 text-slate-950 rounded-full font-black text-[10px]"
                                  :class="hayCuentasPedidas ? 'animate-bounce bg-rose-500 text-white' : ''"
                                  x-text="mesasAbiertas.length">0</span>
                        </button>

                        {{-- Abre el modal de reimpresión de tickets --}}
                        <button type="button"
                                @click="abrirModalReimpresion()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Reimprimir Ticket
                        </button>

                        {{-- Abre el modal de facturación electrónica SAT CFDI 4.0 --}}
                        <button type="button"
                                @click="abrirModalFacturacion()"
                                class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Facturación CFDI
                        </button>

                        {{-- Abre el modal de Corte X (arqueo por denominaciones sin cerrar turno) --}}
                        <button type="button"
                                @click="abrirModalCorteX()"
                                class="px-3 py-2 bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer">
                            <span>🧮</span>
                            <span>Corte X</span>
                        </button>

                        {{-- Abre el modal de corte Z y cierre de turno --}}
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

                    {{-- ================================================================= --}}
                    {{-- MÉTODOS DE PAGO (SIMPLE, MIXTO EFECTIVO+TARJETA/TRANSF Y 2 TARJETAS) --}}
                    {{-- ================================================================= --}}
                    <div class="pt-2 space-y-3">
                        {{-- Botones de pago simple --}}
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button"
                                    @click="activarPagoSimple('01')"
                                    :class="modoCobroPos === 'simple' && formaPagoSat === '01' ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-950 text-slate-400 border-slate-800'"
                                    class="py-2 text-xs font-semibold rounded-lg border transition cursor-pointer">
                                Efectivo
                            </button>
                            <button type="button"
                                    @click="activarPagoSimple('04')"
                                    :class="modoCobroPos === 'simple' && (formaPagoSat === '04' || formaPagoSat === '28') ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-950 text-slate-400 border-slate-800'"
                                    class="py-2 text-xs font-semibold rounded-lg border transition cursor-pointer">
                                Tarjeta
                            </button>
                            <button type="button"
                                    @click="activarPagoSimple('03')"
                                    :class="modoCobroPos === 'simple' && formaPagoSat === '03' ? 'bg-indigo-600 text-white border-indigo-500' : 'bg-slate-950 text-slate-400 border-slate-800'"
                                    class="py-2 text-xs font-semibold rounded-lg border transition cursor-pointer">
                                Transferencia
                            </button>
                        </div>

                        {{-- Botones para Pago Mixto y Pago con Dos Tarjetas --}}
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                    @click="activarPagoMixto()"
                                    :class="modoCobroPos === 'mixto' ? 'bg-amber-600 text-white border-amber-500' : 'bg-slate-950 text-amber-300/90 border-slate-800 hover:border-amber-500/40'"
                                    class="py-2 text-xs font-bold rounded-lg border transition cursor-pointer">
                                🔀 Pago Mixto
                            </button>
                            <button type="button"
                                    @click="activarDosTarjetas()"
                                    :class="modoCobroPos === 'dos_tarjetas' ? 'bg-cyan-600 text-white border-cyan-500' : 'bg-slate-950 text-cyan-300/90 border-slate-800 hover:border-cyan-500/40'"
                                    class="py-2 text-xs font-bold rounded-lg border transition cursor-pointer">
                                💳💳 2 Tarjetas
                            </button>
                        </div>

                        {{-- Campos para Pago Simple en Efectivo --}}
                        <template x-if="modoCobroPos === 'simple' && formaPagoSat === '01'">
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <label class="text-slate-400 block mb-1">Monto Recibido</label>
                                    <input type="number" step="0.50" x-model.number="pagoEfectivoRecibido"
                                           class="w-full !bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-sm focus:outline-none">
                                </div>
                                <div>
                                    <label class="text-slate-400 block mb-1">Cambio a Devolver</label>
                                    <div class="px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-emerald-400 font-mono text-sm font-bold"
                                         x-text="`$${Math.max(0, (Number(pagoEfectivoRecibido) || 0) - totales.total).toFixed(2)}`"></div>
                                </div>
                            </div>
                        </template>

                        {{-- Campos para Pago Simple con Tarjeta (Débito / Crédito) --}}
                        <template x-if="modoCobroPos === 'simple' && (formaPagoSat === '04' || formaPagoSat === '28')">
                            <div class="p-3 bg-slate-950/80 border border-slate-800 rounded-xl space-y-2 text-xs">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-slate-400 block mb-1">Tipo de Tarjeta</label>
                                        <select x-model="formaPagoSat"
                                                class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white text-xs focus:outline-none">
                                            <option value="04">04 - Tarjeta de Crédito</option>
                                            <option value="28">28 - Tarjeta de Débito</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">Referencia / Últimos 4</label>
                                        <input type="text" x-model="referenciaPago1" maxlength="40"
                                               placeholder="Opcional"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-xs focus:outline-none">
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Campos para Pago Mixto: Efectivo + Tarjeta (Débito/Crédito) o Transferencia --}}
                        <template x-if="modoCobroPos === 'mixto'">
                            <div class="p-3 bg-slate-950/90 border border-amber-500/30 rounded-xl space-y-2.5 text-xs">
                                <div class="flex items-center justify-between text-[11px] text-amber-300 font-bold">
                                    <span>🔀 Desglose Pago Mixto</span>
                                    <span class="font-mono" x-text="`Faltante: $${Math.max(0, totales.total - ((Number(montoMixtoEfectivo) || 0) + (Number(montoMixtoSecundario) || 0))).toFixed(2)}`"></span>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-slate-400 block mb-1">Monto en Efectivo ($)</label>
                                        <input type="number" step="0.50" min="0"
                                               x-model.number="montoMixtoEfectivo"
                                               @input="ajustarMontoMixtoDesdeEfectivo()"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-xs focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">Segundo Medio</label>
                                        <select x-model="medioMixtoSecundario"
                                                class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white text-xs focus:outline-none">
                                            <option value="04">Tarjeta de Crédito (04)</option>
                                            <option value="28">Tarjeta de Débito (28)</option>
                                            <option value="03">Transferencia (03)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-slate-400 block mb-1">Monto Tarjeta / Transf. ($)</label>
                                        <input type="number" step="0.50" min="0"
                                               x-model.number="montoMixtoSecundario"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-xs focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">Referencia (Opcional)</label>
                                        <input type="text" x-model="referenciaPago1" maxlength="40"
                                               placeholder="Auth / Folio"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-xs focus:outline-none">
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Campos para Pago con Dos Tarjetas (Débito / Crédito) --}}
                        <template x-if="modoCobroPos === 'dos_tarjetas'">
                            <div class="p-3 bg-slate-950/90 border border-cyan-500/30 rounded-xl space-y-2.5 text-xs">
                                <div class="flex items-center justify-between text-[11px] text-cyan-300 font-bold">
                                    <span>💳💳 Cobro con Dos Tarjetas</span>
                                    <span class="font-mono" x-text="`Restante: $${Math.max(0, totales.total - ((Number(montoTarjeta1) || 0) + (Number(montoTarjeta2) || 0))).toFixed(2)}`"></span>
                                </div>

                                {{-- Tarjeta 1 --}}
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="text-slate-400 block mb-1">Tarjeta 1</label>
                                        <select x-model="tipoTarjeta1"
                                                class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 !text-white text-xs focus:outline-none">
                                            <option value="04">Crédito (04)</option>
                                            <option value="28">Débito (28)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">Monto T1 ($)</label>
                                        <input type="number" step="0.50" min="0"
                                               x-model.number="montoTarjeta1"
                                               @input="ajustarTarjeta2DesdeTarjeta1()"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-xs focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">Ref. T1</label>
                                        <input type="text" x-model="referenciaPago1" maxlength="40"
                                               placeholder="Opcional"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 !text-white font-mono text-xs focus:outline-none">
                                    </div>
                                </div>

                                {{-- Tarjeta 2 --}}
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="text-slate-400 block mb-1">Tarjeta 2</label>
                                        <select x-model="tipoTarjeta2"
                                                class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 !text-white text-xs focus:outline-none">
                                            <option value="28">Débito (28)</option>
                                            <option value="04">Crédito (04)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">Monto T2 ($)</label>
                                        <input type="number" step="0.50" min="0"
                                               x-model.number="montoTarjeta2"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 !text-white font-mono text-xs focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="text-slate-400 block mb-1">Ref. T2</label>
                                        <input type="text" x-model="referenciaPago2" maxlength="40"
                                               placeholder="Opcional"
                                               class="w-full !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 !text-white font-mono text-xs focus:outline-none">
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Botón principal de cobro e impresión de ticket --}}
                        <button @click="procesarVenta()" :disabled="carrito.length === 0 || isProcessing || !puedeCobrarConPagos"
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
        {{-- SECCIÓN 6: MODALES DE CAJA (CORTE X / Z), REIMPRESIÓN Y CFDI 4.0   --}}
        {{-- ===================================================================== --}}

        {{-- 6.0 Modal de Corte X (Conteo de Monedas y Billetes por Denominación) --}}
        <div x-show="modalCorteX" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalCorteX = false"
                 class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-2xl w-full space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>🧮 Corte X — Arqueo por Denominaciones</span>
                        </h3>
                        <p class="text-[11px] text-slate-400">
                            Turno #<span x-text="turnoActivo?.id || ''"></span> • El turno permanece abierto
                        </p>
                    </div>
                    <button type="button" @click="modalCorteX = false" class="text-slate-400 hover:text-white text-lg font-bold cursor-pointer">&times;</button>
                </div>

                {{-- Resumen de referencia del turno y cálculo automático --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                    <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                        <span class="text-slate-400 block text-[11px]">Efectivo Esperado</span>
                        <span class="font-mono font-bold text-indigo-300 text-sm"
                              x-text="`$${Number(turnoActivo?.monto_esperado_efectivo || 0).toFixed(2)}`"></span>
                    </div>
                    <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                        <span class="text-slate-400 block text-[11px]">Total Contado</span>
                        <span class="font-mono font-black text-emerald-400 text-sm"
                              x-text="`$${totalContadoCorteX.toFixed(2)}`"></span>
                    </div>
                    <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                        <span class="text-slate-400 block text-[11px]">Diferencia</span>
                        <span class="font-mono font-bold text-sm"
                              :class="diferenciaCorteX === 0 ? 'text-slate-200' : (diferenciaCorteX > 0 ? 'text-emerald-400' : 'text-rose-400')"
                              x-text="`${diferenciaCorteX >= 0 ? '+' : ''}$${diferenciaCorteX.toFixed(2)}`"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    {{-- Columna Monedas --}}
                    <div class="p-3.5 bg-slate-950/80 border border-slate-800 rounded-xl space-y-2.5">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <span class="font-bold text-amber-300 uppercase tracking-wider text-[11px]">🪙 Monedas</span>
                            <span class="font-mono font-bold text-amber-300" x-text="`$${subtotalMonedasCorteX.toFixed(2)}`"></span>
                        </div>

                        <template x-for="mon in denominacionesMonedas" :key="'mon_' + mon.valor">
                            <div class="grid grid-cols-12 items-center gap-2">
                                <span class="col-span-4 font-mono font-bold text-slate-200" x-text="mon.etiqueta"></span>
                                <input type="number" min="0" step="1"
                                       x-model.number="conteoDenominaciones[mon.valor]"
                                       placeholder="0"
                                       class="col-span-4 !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 !text-white font-mono text-xs text-right focus:outline-none focus:border-amber-500">
                                <span class="col-span-4 font-mono text-right text-slate-300"
                                      x-text="`$${((Number(conteoDenominaciones[mon.valor]) || 0) * mon.valor).toFixed(2)}`"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Columna Billetes --}}
                    <div class="p-3.5 bg-slate-950/80 border border-slate-800 rounded-xl space-y-2.5">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <span class="font-bold text-emerald-300 uppercase tracking-wider text-[11px]">💵 Billetes</span>
                            <span class="font-mono font-bold text-emerald-300" x-text="`$${subtotalBilletesCorteX.toFixed(2)}`"></span>
                        </div>

                        <template x-for="bil in denominacionesBilletes" :key="'bil_' + bil.valor">
                            <div class="grid grid-cols-12 items-center gap-2">
                                <span class="col-span-4 font-mono font-bold text-slate-200" x-text="bil.etiqueta"></span>
                                <input type="number" min="0" step="1"
                                       x-model.number="conteoDenominaciones[bil.valor]"
                                       placeholder="0"
                                       class="col-span-4 !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 !text-white font-mono text-xs text-right focus:outline-none focus:border-emerald-500">
                                <span class="col-span-4 font-mono text-right text-slate-300"
                                      x-text="`$${((Number(conteoDenominaciones[bil.valor]) || 0) * bil.valor).toFixed(2)}`"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-800 flex-wrap">
                    <button type="button"
                            @click="limpiarConteoCorteX()"
                            class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition cursor-pointer">
                        Limpiar Conteo
                    </button>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="modalCorteX = false"
                                class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition cursor-pointer">
                            Cerrar
                        </button>
                        <button type="button"
                                @click="imprimirCorteXConConteo()"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/20 transition cursor-pointer">
                            🖨️ Imprimir Corte X
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================================== --}}
        {{-- 6.1 MODAL DE APERTURA DE TURNO (CON CONTEO POR DENOMINACIÓN)          --}}
        {{-- ===================================================================== --}}
        <div x-show="modalApertura" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalApertura = false"
                class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-xl w-full space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-white">Apertura de Turno de Caja</h3>
                        <p class="text-[11px] text-slate-400">Captura el fondo inicial directo o cuenta monedas y billetes (no imprime ticket)</p>
                    </div>
                    <button type="button" @click="modalApertura = false" class="text-slate-400 hover:text-white text-lg font-bold cursor-pointer">&times;</button>
                </div>

                {{-- Fondo Inicial en Efectivo (se actualiza automáticamente al contar denominaciones) --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs text-slate-300 font-medium">Fondo Inicial en Efectivo ($)</label>
                        <button type="button"
                                @click="mostrarConteoApertura = !mostrarConteoApertura"
                                class="text-[11px] font-bold text-amber-300 hover:text-amber-200 cursor-pointer">
                            <span x-text="mostrarConteoApertura ? '▲ Ocultar Denominaciones' : '🧮 Contar Monedas y Billetes'"></span>
                        </button>
                    </div>
                    <input type="number" step="0.50" min="0"
                        x-model.number="formApertura.monto_inicial"
                        style="background-color: #020617 !important; color: #ffffff !important; border-color: #334155 !important;"
                        class="w-full rounded-xl px-3 py-2 font-mono text-base focus:outline-none">
                </div>

                {{-- Desglose opcional de monedas y billetes para calcular el fondo inicial automáticamente --}}
                <div x-show="mostrarConteoApertura" x-cloak class="p-3.5 bg-slate-950/90 border border-slate-800 rounded-xl space-y-3 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                        <span class="font-bold text-emerald-300">Total Contado en Denominaciones:</span>
                        <span class="font-mono font-black text-emerald-400 text-sm" x-text="`$${totalContadoApertura.toFixed(2)}`"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {{-- Monedas --}}
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-[11px] font-bold text-amber-300">
                                <span>🪙 Monedas</span>
                                <span class="font-mono" x-text="`$${subtotalMonedasApertura.toFixed(2)}`"></span>
                            </div>
                            <template x-for="mon in denominacionesMonedas" :key="'ap_mon_' + mon.valor">
                                <div class="grid grid-cols-12 items-center gap-1.5">
                                    <span class="col-span-4 font-mono text-slate-300" x-text="mon.etiqueta"></span>
                                    <input type="number" min="0" step="1"
                                        x-model.number="conteoApertura[mon.valor]"
                                        @input="sincronizarFondoApertura()"
                                        placeholder="0"
                                        class="col-span-4 !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 !text-white font-mono text-xs text-right focus:outline-none focus:border-amber-500">
                                    <span class="col-span-4 font-mono text-right text-slate-400"
                                        x-text="`$${((Number(conteoApertura[mon.valor]) || 0) * mon.valor).toFixed(2)}`"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Billetes --}}
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-[11px] font-bold text-emerald-300">
                                <span>💵 Billetes</span>
                                <span class="font-mono" x-text="`$${subtotalBilletesApertura.toFixed(2)}`"></span>
                            </div>
                            <template x-for="bil in denominacionesBilletes" :key="'ap_bil_' + bil.valor">
                                <div class="grid grid-cols-12 items-center gap-1.5">
                                    <span class="col-span-4 font-mono text-slate-300" x-text="bil.etiqueta"></span>
                                    <input type="number" min="0" step="1"
                                        x-model.number="conteoApertura[bil.valor]"
                                        @input="sincronizarFondoApertura()"
                                        placeholder="0"
                                        class="col-span-4 !bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 !text-white font-mono text-xs text-right focus:outline-none focus:border-emerald-500">
                                    <span class="col-span-4 font-mono text-right text-slate-400"
                                        x-text="`$${((Number(conteoApertura[bil.valor]) || 0) * bil.valor).toFixed(2)}`"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex justify-end pt-1">
                        <button type="button"
                                @click="limpiarConteoApertura()"
                                class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-[11px] font-semibold cursor-pointer">
                            Limpiar Denominaciones
                        </button>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-slate-300 block mb-1 font-medium">Observaciones</label>
                    <textarea x-model="formApertura.observaciones" rows="2"
                            placeholder="Notas opcionales de apertura..."
                            style="background-color: #020617 !important; color: #ffffff !important; border-color: #334155 !important;"
                            class="w-full rounded-xl px-3 py-2 text-xs focus:outline-none"></textarea>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" @click="modalApertura = false" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold transition cursor-pointer">Cancelar</button>
                    <button type="button" @click="abrirTurnoCaja()" class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-600/30 transition cursor-pointer">Abrir Caja</button>
                </div>
            </div>
        </div>

        {{-- 6.2 Modal de Corte Z y Cierre de Turno --}}
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

        {{-- 6.3 Modal de Reimpresión de Tickets (Filtrado por Rol / Turno) --}}
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

        {{-- 6.4 Modal Principal de Facturación Electrónica SAT (CFDI 4.0) --}}
        <div x-show="modalFacturacion" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalFacturacion = false" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-2xl w-full space-y-4 shadow-2xl max-h-[88vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>📄 Facturación Electrónica SAT (CFDI 4.0)</span>
                        </h3>
                        <p class="text-[11px] text-slate-400">Asigna cliente fiscal si el ticket usa RFC genérico, timbra CFDI, descarga XML/PDF o envía por correo.</p>
                    </div>
                    <button type="button" @click="modalFacturacion = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                {{-- Mensaje de estado o confirmación de facturación / cliente --}}
                <template x-if="mensajeFactura">
                    <div class="p-3 rounded-xl text-xs font-semibold border"
                         :class="mensajeErrorFactura ? 'bg-rose-500/15 border-rose-500/40 text-rose-300' : 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300'"
                         x-text="mensajeFactura"></div>
                </template>

                {{-- Listado de tickets recientes elegibles para facturación --}}
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
                                {{-- Acciones previas al timbrado --}}
                                <template x-if="v.estado_facturacion !== 'facturada'">
                                    <div class="flex items-center gap-2 flex-wrap justify-end">
                                        <button type="button"
                                                @click="abrirBuscarClienteDesdeTicket(v)"
                                                class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-cyan-300 border border-cyan-500/30 font-bold rounded-lg transition cursor-pointer">
                                            🔍 Buscar Cliente
                                        </button>

                                        <template x-if="!v.cliente_rfc || v.cliente_rfc === 'XAXX010101000'">
                                            <button type="button"
                                                    @click="abrirAltaClienteDesdeTicket(v)"
                                                    class="px-3 py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-lg transition cursor-pointer">
                                                + Alta Cliente
                                            </button>
                                        </template>

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

                                {{-- Acciones posteriores al timbrado (XML, PDF, Correo y Regenerar en el mismo mes) --}}
                                <template x-if="v.estado_facturacion === 'facturada'">
                                    <div class="flex items-center gap-2 flex-wrap justify-end">
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

                                        <button type="button"
                                                @click="enviarFacturaCorreo(v)"
                                                :disabled="enviandoCorreoId === v.id"
                                                class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 font-semibold rounded-lg transition cursor-pointer">
                                            <span x-show="enviandoCorreoId !== v.id">✉️ Correo</span>
                                            <span x-show="enviandoCorreoId === v.id">Enviando...</span>
                                        </button>

                                        {{-- Visible solo si la factura pertenece al mes calendario actual --}}
                                        <template x-if="esFacturaDelMesActual(v)">
                                            <button type="button"
                                                    @click="regenerarFacturaVenta(v)"
                                                    :disabled="regenerandoId === v.id"
                                                    class="px-3 py-1.5 bg-amber-600/20 hover:bg-amber-600/35 disabled:opacity-50 text-amber-300 border border-amber-500/30 font-semibold rounded-lg transition cursor-pointer">
                                                <span x-show="regenerandoId !== v.id">🔄 Regenerar</span>
                                                <span x-show="regenerandoId === v.id">Regenerando...</span>
                                            </button>
                                        </template>
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

        {{-- 6.5 Modal de Alta / Edición de Cliente Fiscal para Facturación --}}
        <div x-show="modalAltaClienteCfdi" class="fixed inset-0 bg-black/85 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalAltaClienteCfdi = false" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-lg w-full space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-white" x-text="formClienteCfdi.id ? 'Ver / Editar Cliente Fiscal' : 'Alta de Cliente Fiscal'"></h3>
                        <p class="text-[11px] text-slate-400">
                            Ticket: <span class="font-mono text-indigo-400" x-text="ventaParaCliente?.folio || ''"></span>
                        </p>
                    </div>
                    <button type="button" @click="modalAltaClienteCfdi = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                {{-- Mensaje de error o confirmación dentro del modal de alta/edición --}}
                <template x-if="mensajeFactura">
                    <div class="p-3 rounded-xl text-xs font-semibold border"
                         :class="mensajeErrorFactura ? 'bg-rose-500/15 border-rose-500/40 text-rose-300' : 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300'"
                         x-text="mensajeFactura"></div>
                </template>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="text-slate-300 block mb-1 font-medium">Nombre o Razón Social *</label>
                        <input type="text" x-model="formClienteCfdi.nombre"
                               placeholder="Ej. Juan Pérez / Comercializadora SA"
                               class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-slate-300 block mb-1 font-medium">RFC *</label>
                            <input type="text" x-model="formClienteCfdi.rfc" maxlength="13"
                                   placeholder="Ej. GOME800101AAA"
                                   class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white uppercase font-mono focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-slate-300 block mb-1 font-medium">Código Postal Fiscal</label>
                            <input type="text" x-model="formClienteCfdi.codigo_postal" maxlength="5"
                                   placeholder="83000"
                                   class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    {{-- Catálogos completos SAT: Régimen Fiscal y Uso de CFDI --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-slate-300 block mb-1 font-medium">Régimen Fiscal (SAT)</label>
                            <select x-model="formClienteCfdi.regimen_fiscal"
                                    class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                                <optgroup label="Personas Morales">
                                    <option value="601">601 - General de Ley Personas Morales</option>
                                    <option value="603">603 - Personas Morales con Fines no Lucrativos</option>
                                    <option value="620">620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos</option>
                                    <option value="622">622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras</option>
                                    <option value="623">623 - Opcional para Grupos de Sociedades</option>
                                    <option value="624">624 - Coordinados</option>
                                </optgroup>

                                <optgroup label="Personas Físicas">
                                    <option value="605">605 - Sueldos y Salarios e Ingresos Asimilados a Salarios</option>
                                    <option value="606">606 - Arrendamiento</option>
                                    <option value="607">607 - Régimen de Enajenación o Adquisición de Bienes</option>
                                    <option value="608">608 - Demás ingresos</option>
                                    <option value="611">611 - Ingresos por Dividendos (socios y accionistas)</option>
                                    <option value="612">612 - Personas Físicas con Actividades Empresariales y Profesionales</option>
                                    <option value="614">614 - Ingresos por intereses</option>
                                    <option value="615">615 - Régimen de los ingresos por obtención de premios</option>
                                    <option value="621">621 - Incorporación Fiscal</option>
                                    <option value="625">625 - Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas</option>
                                    <option value="626">626 - Régimen Simplificado de Confianza (RESICO - Personas Físicas)</option>
                                </optgroup>

                                <optgroup label="Mixtos y Especiales">
                                    <option value="610">610 - Residentes en el Extranjero sin Establecimiento Permanente en México</option>
                                    <option value="616">616 - Sin obligaciones fiscales</option>
                                    <option value="626_PM">626 - Régimen Simplificado de Confianza (RESICO - Personas Morales)</option>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label class="text-slate-300 block mb-1 font-medium">Uso de CFDI (SAT)</label>
                            <select x-model="formClienteCfdi.uso_cfdi"
                                    class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                                <optgroup label="Gastos Generales (Físicas y Morales)">
                                    <option value="G01">G01 - Adquisición de mercancías</option>
                                    <option value="G02">G02 - Devoluciones, descuentos o bonificaciones</option>
                                    <option value="G03">G03 - Gastos en general</option>
                                </optgroup>

                                <optgroup label="Inversiones (Físicas y Morales)">
                                    <option value="I01">I01 - Construcciones</option>
                                    <option value="I02">I02 - Mobiliario y equipo de oficina por inversiones</option>
                                    <option value="I03">I03 - Equipo de transporte</option>
                                    <option value="I04">I04 - Equipo de cómputo y accesorios</option>
                                    <option value="I05">I05 - Dados, troqueles, moldes, matrices y herramental</option>
                                    <option value="I06">I06 - Comunicaciones telefónicas</option>
                                    <option value="I07">I07 - Comunicaciones satelitales</option>
                                    <option value="I08">I08 - Otra maquinaria y equipo</option>
                                </optgroup>

                                <optgroup label="Deducciones Personales (Solo Personas Físicas)">
                                    <option value="D01">D01 - Honorarios médicos, dentales y gastos hospitalarios</option>
                                    <option value="D02">D02 - Gastos médicos por incapacidad o discapacidad</option>
                                    <option value="D03">D03 - Gastos funerales</option>
                                    <option value="D04">D04 - Donativos</option>
                                    <option value="D05">D05 - Intereses reales efectivamente pagados por créditos hipotecarios (casa habitación)</option>
                                    <option value="D06">D06 - Aportaciones complementarias de retiro / planes de retiro</option>
                                    <option value="D07">D07 - Primas por seguros de gastos médicos</option>
                                    <option value="D08">D08 - Colegiaturas en instituciones educativas privadas</option>
                                    <option value="D09">D09 - Transporte escolar obligatorio</option>
                                    <option value="D10">D10 - Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones</option>
                                </optgroup>

                                <optgroup label="Claves Especiales">
                                    <option value="S01">S01 - Sin efectos fiscales</option>
                                    <option value="CP01">CP01 - Pagos</option>
                                    <option value="CN01">CN01 - Nómina</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-slate-300 block mb-1 font-medium">Correo Electrónico (Factura)</label>
                            <input type="email" x-model="formClienteCfdi.email"
                                   placeholder="cliente@correo.com"
                                   class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-slate-300 block mb-1 font-medium">Teléfono / WhatsApp</label>
                            <input type="text" x-model="formClienteCfdi.telefono_whatsapp"
                                   placeholder="Opcional"
                                   class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                </div>

                {{-- Pie único del modal de alta/edición con opción de regresar y guardar --}}
                <div class="flex gap-2 pt-2 border-t border-slate-800 flex-wrap">
                    <button type="button"
                            @click="regresarDesdeAltaEdicionCliente()"
                            class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition cursor-pointer">
                        ← Regresar
                    </button>

                    <button type="button"
                            @click="guardarClienteCfdi()"
                            :disabled="guardandoClienteCfdi"
                            class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition cursor-pointer">
                        <span x-show="!guardandoClienteCfdi">Guardar y Habilitar Timbrado</span>
                        <span x-show="guardandoClienteCfdi">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- 6.6 Modal para Buscar, Editar, Homologar o Eliminar Clientes Registrados --}}
        <div x-show="modalBuscarClienteCfdi" class="fixed inset-0 bg-black/85 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalBuscarClienteCfdi = false" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-2xl w-full space-y-4 shadow-2xl max-h-[86vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-white">Buscar Cliente Registrado</h3>
                        <p class="text-[11px] text-slate-400">
                            Ticket: <span class="font-mono text-indigo-400" x-text="ventaParaCliente?.folio || ''"></span>
                        </p>
                    </div>
                    <button type="button" @click="modalBuscarClienteCfdi = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                {{-- Mensaje de error o aviso dentro del modal de búsqueda --}}
                <template x-if="mensajeFactura">
                    <div class="p-3 rounded-xl text-xs font-semibold border"
                         :class="mensajeErrorFactura ? 'bg-rose-500/15 border-rose-500/40 text-rose-300' : 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300'"
                         x-text="mensajeFactura"></div>
                </template>

                <div>
                    <input type="text"
                           x-model="filtroClienteCfdi"
                           @input.debounce.250ms="buscarClientesExistentes()"
                           placeholder="Buscar por nombre, RFC o teléfono..."
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs !text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500">
                </div>

                {{-- Contenedor desplazable de tarjetas de clientes con layout en 2 filas para lectura clara --}}
                <div class="flex-1 overflow-y-auto space-y-2.5 pr-1">
                    <template x-if="cargandoClientesCfdi">
                        <p class="text-center text-xs text-slate-400 py-6">Buscando clientes...</p>
                    </template>

                    <template x-if="!cargandoClientesCfdi && clientesEncontradosCfdi.length === 0">
                        <div class="text-center py-6 space-y-2">
                            <p class="text-xs text-slate-500">No se encontraron clientes con ese criterio.</p>
                            <button type="button"
                                    @click="modalBuscarClienteCfdi = false; abrirAltaClienteDesdeTicket(ventaParaCliente)"
                                    class="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-lg cursor-pointer">
                                + Dar de Alta Nuevo Cliente
                            </button>
                        </div>
                    </template>

                    <template x-for="cli in clientesEncontradosCfdi" :key="cli.id">
                        <div class="p-3.5 bg-slate-950 border border-slate-800 rounded-xl space-y-2.5 text-xs">
                            {{-- Fila superior: Datos completos del cliente --}}
                            <div class="space-y-1">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <span class="font-bold text-white text-sm break-words" x-text="cli.nombre || 'Cliente sin nombre'"></span>
                                    <span class="font-mono text-[11px] px-2 py-0.5 rounded bg-cyan-950/70 border border-cyan-500/30 text-cyan-300"
                                          x-text="cli.rfc || 'SIN RFC'"></span>
                                </div>
                                <div class="text-[11px] text-slate-400 break-all"
                                     x-text="[cli.email, cli.telefono_whatsapp, cli.codigo_postal ? 'CP ' + cli.codigo_postal : ''].filter(Boolean).join(' • ') || 'Sin correo / teléfono'"></div>
                            </div>

                            {{-- Fila inferior: Botones de acción bien espaciados --}}
                            <div class="flex items-center justify-end gap-2 flex-wrap pt-2 border-t border-slate-800/80">
                                <button type="button"
                                        @click="verOEditarClienteExistente(cli)"
                                        class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-amber-300 border border-amber-500/30 font-semibold rounded-lg transition cursor-pointer">
                                    👁️ Ver / Editar
                                </button>

                                <button type="button"
                                        @click="seleccionarClienteExistente(cli)"
                                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg transition cursor-pointer">
                                    Seleccionar
                                </button>

                                <button type="button"
                                        @click="abrirModalHomologarCliente(cli)"
                                        class="px-2.5 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/35 text-indigo-300 border border-indigo-500/30 font-semibold rounded-lg transition cursor-pointer">
                                    🔗 Homologar
                                </button>

                                <button type="button"
                                        @click="eliminarClienteExistente(cli)"
                                        :disabled="eliminandoClienteId === cli.id"
                                        class="px-2.5 py-1.5 bg-rose-600/20 hover:bg-rose-600/35 disabled:opacity-50 text-rose-300 border border-rose-500/30 font-semibold rounded-lg transition cursor-pointer">
                                    <span x-show="eliminandoClienteId !== cli.id">🗑️ Eliminar</span>
                                    <span x-show="eliminandoClienteId === cli.id">...</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Pie único del modal de búsqueda con navegación de regreso a Facturación --}}
                <div class="pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
                    <button type="button"
                            @click="regresarAFacturacionDesdeBuscador()"
                            class="px-4 py-2 bg-indigo-600/20 hover:bg-indigo-600/35 text-indigo-300 border border-indigo-500/30 text-xs font-bold rounded-xl transition cursor-pointer">
                        ← Regresar a Facturación
                    </button>

                    <button type="button"
                            @click="modalBuscarClienteCfdi = false"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-300 cursor-pointer">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        {{-- 6.7 Modal para Homologar Cliente Duplicado o Inactivo hacia un Cliente Destino --}}
        <div x-show="modalHomologarCliente" class="fixed inset-0 bg-black/85 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
            <div @click.away="modalHomologarCliente = false" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-md w-full space-y-4 shadow-2xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-white">Homologar Cliente</h3>
                        <p class="text-[11px] text-slate-400">
                            Origen: <strong class="text-amber-300" x-text="clienteOrigenHomologar?.nombre || ''"></strong>
                        </p>
                    </div>
                    <button type="button" @click="modalHomologarCliente = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                {{-- Mensaje de error o validación dentro del modal de homologación --}}
                <template x-if="mensajeFactura">
                    <div class="p-3 rounded-xl text-xs font-semibold border"
                         :class="mensajeErrorFactura ? 'bg-rose-500/15 border-rose-500/40 text-rose-300' : 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300'"
                         x-text="mensajeFactura"></div>
                </template>

                <div class="space-y-2 text-xs">
                    <label class="text-slate-300 block font-medium">Selecciona el cliente destino al cual homologar:</label>
                    <select x-model="clienteDestinoHomologarId"
                            class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                        <option value="">-- Elige un cliente destino --</option>
                        <template x-for="dest in (clientesEncontradosCfdi || []).filter(c => c.id !== clienteOrigenHomologar?.id)" :key="'dest_' + dest.id">
                            <option :value="dest.id" x-text="`${dest.nombre} (${dest.rfc || 'SIN RFC'})`"></option>
                        </template>
                    </select>
                </div>

                {{-- Pie único del modal de homologación con regreso al buscador --}}
                <div class="flex gap-2 pt-2 border-t border-slate-800">
                    <button type="button"
                            @click="regresarABuscadorDesdeHomologar()"
                            class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition cursor-pointer">
                        ← Regresar
                    </button>

                    <button type="button"
                            @click="confirmarHomologacionCliente()"
                            :disabled="!clienteDestinoHomologarId || homologandoCliente"
                            class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition cursor-pointer">
                        <span x-show="!homologandoCliente">Confirmar Homologación</span>
                        <span x-show="homologandoCliente">Homologando...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 7: LÓGICA ALPINE.JS DEL POS, TIMBRE, PRECUENTAS Y CFDI      --}}
    {{-- ===================================================================== --}}
    <script>
        /**
- Componente principal de Alpine.js para el Punto de Venta (POS).
- Gestiona turno de caja, plano de mesas, catálogo, cobro, precuentas,
- apertura con conteo por denominaciones (sin imprimir), corte X por denominaciones,
- corte Z, reimpresión y facturación CFDI 4.0.
         */
        function posTerminal(config) {
            return {
                // =====================================================================
                // CONFIGURACIÓN INICIAL, RUTAS Y SEGURIDAD
                // =====================================================================
                sucursal: config.sucursal || { id: 1, nombre: 'Sucursal Principal' },
                almacen: config.almacen || { id: 1, nombre: 'Almacén General' },
                turnoActivo: config.turnoInicial || null,
                areas: config.areasIniciales || [],
                areaSeleccionadaId: (config.areasIniciales && config.areasIniciales.length > 0) ? config.areasIniciales[0].id : null,
                routes: config.routes,
                csrf: config.csrfToken,

                // =====================================================================
                // CONECTIVIDAD, SINCRONIZACIÓN OFFLINE Y ESTADO DE PROCESOS
                // =====================================================================
                isOnline: navigator.onLine,
                isSyncing: false,
                isProcessing: false,
                offlineQueueCount: 0,
                db: null,

                // =====================================================================
                // MODALES Y FORMULARIOS DE APERTURA / CORTE X / CIERRE (CORTE Z)
                // =====================================================================
                modalApertura: false,
                modalCierre: false,
                modalCorteX: false,
                formApertura: { monto_inicial: 1000, observaciones: '' },
                formCierre: { monto_real_efectivo: 0, observaciones: '' },

                // Control de desglose de denominaciones en la apertura de turno (sin imprimir)
                mostrarConteoApertura: false,
                conteoApertura: {
                    0.5: 0,
                    1: 0,
                    2: 0,
                    5: 0,
                    10: 0,
                    20: 0,
                    50: 0,
                    100: 0,
                    200: 0,
                    500: 0,
                    1000: 0
                },

                // Denominaciones de monedas y billetes compartidas para apertura y Corte X
                denominacionesMonedas: [
                    { valor: 0.5, etiqueta: '$0.50' },
                    { valor: 1, etiqueta: '$1.00' },
                    { valor: 2, etiqueta: '$2.00' },
                    { valor: 5, etiqueta: '$5.00' },
                    { valor: 10, etiqueta: '$10.00' }
                ],
                denominacionesBilletes: [
                    { valor: 20, etiqueta: '$20' },
                    { valor: 50, etiqueta: '$50' },
                    { valor: 100, etiqueta: '$100' },
                    { valor: 200, etiqueta: '$200' },
                    { valor: 500, etiqueta: '$500' },
                    { valor: 1000, etiqueta: '$1,000' }
                ],
                conteoDenominaciones: {
                    0.5: 0,
                    1: 0,
                    2: 0,
                    5: 0,
                    10: 0,
                    20: 0,
                    50: 0,
                    100: 0,
                    200: 0,
                    500: 0,
                    1000: 0
                },

                // =====================================================================
                // CATÁLOGO, CARRITO DE VENTA Y MÉTODOS DE PAGO
                // =====================================================================
                searchQuery: '',
                productos: [],
                carrito: [],
                cliente: { nombre: 'Público General', rfc: 'XAXX010101000' },
                formaPagoSat: '01',
                pagoEfectivoRecibido: 0,

                // =====================================================================
                // MESAS, COMANDAS ABIERTAS Y DIVISIÓN DE CUENTA (RESTAURANTE)
                // =====================================================================
                promocionesVigentes: [],
                modalMesasPos: false,
                cargandoMesas: false,
                mesasAbiertas: [],
                origenComandaMesa: '',
                comandaIdsCobrando: [],
                esSubcuentaCobrando: false,
                itemsPagadosIds: [],
                mesaSeleccionadaActiva: null,

                modalidadPago: 'simple_efectivo',
                modalDividir: false,
                mesaDividiendo: null,
                partidasDivision: [],
                modoDivision: 'items',
                numeroPartesIguales: 2,

                // =====================================================================
                // REIMPRESIÓN DE TICKETS Y FACTURACIÓN CFDI 4.0
                // =====================================================================
                modalReimpresion: false,
                cargandoVentas: false,
                ventasRecientes: [],

                modalFacturacion: false,
                cargandoFacturacion: false,
                ventasFacturables: [],
                timbrandoId: null,
                enviandoCorreoId: null,
                regenerandoId: null,
                mensajeFactura: '',
                mensajeErrorFactura: false,

                // =====================================================================
                // AUDIO Y ALERTAS DE PRECUENTA DESDE COMANDERO
                // =====================================================================
                posAudioCtx: null,
                posAudioActivo: false,
                precuentasVistas: new Set(),
                alertasPrecuentaPos: [],

                // =====================================================================
                // ALTA, EDICIÓN, BÚSQUEDA Y HOMOLOGACIÓN DE CLIENTES FISCALES
                // =====================================================================
                modalAltaClienteCfdi: false,
                guardandoClienteCfdi: false,
                eliminandoClienteId: null,
                origenAltaDesdeBuscador: false,
                ventaParaCliente: null,
                formClienteCfdi: {
                    id: null,
                    nombre: '',
                    rfc: '',
                    nivel_lealtad: 'regular',
                    regimen_fiscal: '616',
                    uso_cfdi: 'G03',
                    codigo_postal: '',
                    email: '',
                    telefono_whatsapp: ''
                },

                modalBuscarClienteCfdi: false,
                cargandoClientesCfdi: false,
                filtroClienteCfdi: '',
                clientesEncontradosCfdi: [],

                modalHomologarCliente: false,
                clienteOrigenHomologar: null,
                clienteDestinoHomologarId: '',
                homologandoCliente: false,

                // =====================================================================
                // ESTADOS DE COBRO SIMPLE, MIXTO Y DOS TARJETAS
                // =====================================================================
                modoCobroPos: 'simple', // 'simple' | 'mixto' | 'dos_tarjetas'
                montoMixtoEfectivo: 0,
                medioMixtoSecundario: '04', // '04' Crédito, '28' Débito, '03' Transferencia
                montoMixtoSecundario: 0,
                tipoTarjeta1: '04',
                montoTarjeta1: 0,
                tipoTarjeta2: '28',
                montoTarjeta2: 0,
                referenciaPago1: '',
                referenciaPago2: '',

                // =====================================================================
                // PROPIEDADES COMPUTADAS (GETTERS)
                // =====================================================================
                // Subtotal calculado de monedas en Apertura de Turno
                get subtotalMonedasApertura() {
                    return this.denominacionesMonedas.reduce((sum, m) => {
                        const cant = Math.max(0, Number(this.conteoApertura[m.valor]) || 0);
                        return sum + (cant * m.valor);
                    }, 0);
                },

                // Subtotal calculado de billetes en Apertura de Turno
                get subtotalBilletesApertura() {
                    return this.denominacionesBilletes.reduce((sum, b) => {
                        const cant = Math.max(0, Number(this.conteoApertura[b.valor]) || 0);
                        return sum + (cant * b.valor);
                    }, 0);
                },

                // Total contado por denominación al abrir turno
                get totalContadoApertura() {
                    return Number((this.subtotalMonedasApertura + this.subtotalBilletesApertura).toFixed(2));
                },

                // Subtotal calculado de monedas en Corte X
                get subtotalMonedasCorteX() {
                    return this.denominacionesMonedas.reduce((sum, m) => {
                        const cant = Math.max(0, Number(this.conteoDenominaciones[m.valor]) || 0);
                        return sum + (cant * m.valor);
                    }, 0);
                },

                // Subtotal calculado de billetes en Corte X
                get subtotalBilletesCorteX() {
                    return this.denominacionesBilletes.reduce((sum, b) => {
                        const cant = Math.max(0, Number(this.conteoDenominaciones[b.valor]) || 0);
                        return sum + (cant * b.valor);
                    }, 0);
                },

                // Total general contado en Corte X (monedas + billetes)
                get totalContadoCorteX() {
                    return Number((this.subtotalMonedasCorteX + this.subtotalBilletesCorteX).toFixed(2));
                },

                // Diferencia entre total contado en Corte X y efectivo esperado del turno
                get diferenciaCorteX() {
                    const esperado = Number(this.turnoActivo?.monto_esperado_efectivo || 0);
                    return Number((this.totalContadoCorteX - esperado).toFixed(2));
                },

                // Indica si existe alguna precuenta solicitada pendiente de atención
                get hayCuentasPedidas() {
                    return (this.mesasAbiertas || []).some(m => Boolean(m.cuenta_pedida)) || this.alertasPrecuentaPos.length > 0;
                },

                // Filtra únicamente las mesas activas que tienen cuenta pedida
                get mesasConCuentaPedida() {
                    return (this.mesasAbiertas || []).filter(m => Boolean(m.cuenta_pedida));
                },

                // Expone las áreas de mesas para compatibilidad con la plantilla Blade
                get areasMesas() {
                    return this.areas || [];
                },

                // Devuelve las mesas pertenecientes al área seleccionada en el plano
                get mesasAreaActual() {
                    const area = this.areas.find(a => a.id === this.areaSeleccionadaId);
                    return area ? (area.mesas || []) : [];
                },

                // Filtra productos del catálogo por nombre o SKU (máximo 30 sin búsqueda)
                get productosFiltrados() {
                    if (!this.searchQuery.trim()) return (this.productos || []).slice(0, 30);
                    const q = this.searchQuery.toLowerCase();
                    return (this.productos || []).filter(p =>
                        (p.nombre && p.nombre.toLowerCase().includes(q)) ||
                        (p.sku && p.sku.toLowerCase().includes(q))
                    );
                },

                // Descuento promocional (reservado para reglas activas)
                get descuentoPromociones() {
                    return 0;
                },

                // Monto canjeado de monedero electrónico (reservado para reglas activas)
                get montoMonederoAplicado() {
                    return 0;
                },

                // Calcula subtotal, IVA (16%) y total a cobrar a partir del carrito
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

                // Valida si los montos capturados en el modo de cobro activo cubren el total del ticket
                get puedeCobrarConPagos() {
                    const total = Number(this.totales.total.toFixed(2));
                    if (total <= 0) return false;

                    if (this.modoCobroPos === 'simple') {
                        if (this.formaPagoSat === '01') {
                            return (Number(this.pagoEfectivoRecibido) || 0) >= total;
                        }
                        return true;
                    }

                    if (this.modoCobroPos === 'mixto') {
                        const ef = Number(this.montoMixtoEfectivo) || 0;
                        const sec = Number(this.montoMixtoSecundario) || 0;
                        return (ef + sec) >= (total - 0.01);
                    }

                    if (this.modoCobroPos === 'dos_tarjetas') {
                        const t1 = Number(this.montoTarjeta1) || 0;
                        const t2 = Number(this.montoTarjeta2) || 0;
                        return t1 > 0 && t2 > 0 && ((t1 + t2) >= (total - 0.01));
                    }

                    return true;
                },

                // =====================================================================
                // MÉTODOS DE APERTURA POR DENOMINACIÓN Y CORTE X
                // =====================================================================
                // Sincroniza automáticamente el fondo inicial de apertura con el total contado
                sincronizarFondoApertura() {
                    this.formApertura.monto_inicial = this.totalContadoApertura;
                },

                // Reinicia el conteo de denominaciones de apertura y deja el fondo inicial en 0
                limpiarConteoApertura() {
                    Object.keys(this.conteoApertura).forEach(k => {
                        this.conteoApertura[k] = 0;
                    });
                    this.formApertura.monto_inicial = 0;
                },

                // Abre el modal de Corte X para realizar arqueo por denominaciones
                abrirModalCorteX() {
                    this.modalCorteX = true;
                },

                // Reinicia todas las cantidades de monedas y billetes del Corte X a 0
                limpiarConteoCorteX() {
                    Object.keys(this.conteoDenominaciones).forEach(k => {
                        this.conteoDenominaciones[k] = 0;
                    });
                },

                // Abre el ticket de impresión del Corte X enviando efectivo contado, diferencia y denominaciones
                imprimirCorteXConConteo() {
                    if (!this.turnoActivo?.id) return;

                    // Reunir únicamente las denominaciones (monedas y billetes) con cantidad mayor a 0
                    const detalleDenominaciones = {};
                    [...this.denominacionesMonedas, ...this.denominacionesBilletes].forEach(d => {
                        const cant = Math.max(0, Number(this.conteoDenominaciones[d.valor]) || 0);
                        if (cant > 0) {
                            detalleDenominaciones[String(d.valor)] = cant;
                        }
                    });

                    const params = new URLSearchParams({
                        tipo: 'X',
                        contado: this.totalContadoCorteX.toFixed(2),
                        diferencia: this.diferenciaCorteX.toFixed(2),
                        denominaciones: JSON.stringify(detalleDenominaciones)
                    });

                    window.open(`/pos/caja/${this.turnoActivo.id}/imprimir-corte?${params.toString()}`, '_blank');
                },

                // =====================================================================
                // MÉTODOS DE COBRO SIMPLE, MIXTO Y DOS TARJETAS
                // =====================================================================
                // Cambia a pago simple (Efectivo '01', Tarjeta '04'/'28' o Transferencia '03')
                activarPagoSimple(formaSat) {
                    this.modoCobroPos = 'simple';
                    this.formaPagoSat = formaSat;
                    this.referenciaPago1 = '';
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                },

                // Inicializa pago mixto dividiendo de inicio la mitad en efectivo y el resto en segundo medio
                activarPagoMixto() {
                    const total = Number(this.totales.total.toFixed(2));
                    const mitad = Number((total / 2).toFixed(2));
                    this.modoCobroPos = 'mixto';
                    this.montoMixtoEfectivo = mitad;
                    this.montoMixtoSecundario = Number(Math.max(0, total - mitad).toFixed(2));
                    this.referenciaPago1 = '';
                },

                // Calcula automáticamente el segundo monto del pago mixto al editar el efectivo
                ajustarMontoMixtoDesdeEfectivo() {
                    const total = Number(this.totales.total.toFixed(2));
                    const ef = Math.max(0, Number(this.montoMixtoEfectivo) || 0);
                    this.montoMixtoSecundario = Number(Math.max(0, total - ef).toFixed(2));
                },

                // Inicializa cobro con dos tarjetas repartiendo el total inicial entre ambas
                activarDosTarjetas() {
                    const total = Number(this.totales.total.toFixed(2));
                    const mitad = Number((total / 2).toFixed(2));
                    this.modoCobroPos = 'dos_tarjetas';
                    this.montoTarjeta1 = mitad;
                    this.montoTarjeta2 = Number(Math.max(0, total - mitad).toFixed(2));
                    this.referenciaPago1 = '';
                    this.referenciaPago2 = '';
                },

                // Calcula automáticamente el monto de la tarjeta 2 al editar el de la tarjeta 1
                ajustarTarjeta2DesdeTarjeta1() {
                    const total = Number(this.totales.total.toFixed(2));
                    const t1 = Math.max(0, Number(this.montoTarjeta1) || 0);
                    this.montoTarjeta2 = Number(Math.max(0, total - t1).toFixed(2));
                },

                // Construye el arreglo de pagos según el modo seleccionado (simple, mixto o dos tarjetas) y procesa la venta
                async procesarVenta() {
                    if (this.carrito.length === 0 || !this.puedeCobrarConPagos) return;
                    this.isProcessing = true;
                    const uuidVenta = this.generarUUID();
                    const totalVenta = Number((parseFloat(this.totales.total) || 0).toFixed(2));

                    let pagosPayload = [];
                    let formaPrincipalSat = this.formaPagoSat || '01';

                    if (this.modoCobroPos === 'simple') {
                        // Pago único: Efectivo ('01'), Tarjeta ('04' / '28') o Transferencia ('03')
                        const recibido = this.formaPagoSat === '01'
                            ? Math.max(totalVenta, parseFloat(this.pagoEfectivoRecibido) || 0)
                            : totalVenta;

                        pagosPayload = [{
                            forma_pago_sat: formaPrincipalSat,
                            monto: totalVenta,
                            monto_recibido: Number(recibido.toFixed(2)),
                            cambio_devuelto: this.formaPagoSat === '01'
                                ? Math.max(0, Number((recibido - totalVenta).toFixed(2)))
                                : 0,
                            referencia_bancaria: formaPrincipalSat !== '01'
                                ? (String(this.referenciaPago1 || '').trim() || null)
                                : null
                        }];
                    } else if (this.modoCobroPos === 'mixto') {
                        // Pago mixto: Efectivo ('01') + segundo medio ('04' Crédito, '28' Débito o '03' Transferencia)
                        const ef = Math.max(0, parseFloat(this.montoMixtoEfectivo) || 0);
                        const sec = Math.max(0, parseFloat(this.montoMixtoSecundario) || 0);
                        const medioSec = this.medioMixtoSecundario || '04';
                        formaPrincipalSat = ef >= sec ? '01' : medioSec;

                        const montoSecFinal = Number(Math.min(totalVenta, sec).toFixed(2));
                        const montoEfFinal = Number(Math.max(0, totalVenta - montoSecFinal).toFixed(2));
                        const cambioMixto = Math.max(0, Number(((ef + sec) - totalVenta).toFixed(2)));

                        if (montoEfFinal > 0 || ef > 0) {
                            pagosPayload.push({
                                forma_pago_sat: '01',
                                monto: montoEfFinal,
                                monto_recibido: Number(ef.toFixed(2)),
                                cambio_devuelto: cambioMixto,
                                referencia_bancaria: null
                            });
                        }
                        if (montoSecFinal > 0) {
                            pagosPayload.push({
                                forma_pago_sat: medioSec,
                                monto: montoSecFinal,
                                monto_recibido: montoSecFinal,
                                cambio_devuelto: 0,
                                referencia_bancaria: String(this.referenciaPago1 || '').trim() || null
                            });
                        }
                    } else if (this.modoCobroPos === 'dos_tarjetas') {
                        // Pago con dos tarjetas (Crédito '04' y/o Débito '28')
                        const t1 = Math.max(0, parseFloat(this.montoTarjeta1) || 0);
                        const t2 = Math.max(0, parseFloat(this.montoTarjeta2) || 0);
                        const tipo1 = this.tipoTarjeta1 || '04';
                        const tipo2 = this.tipoTarjeta2 || '28';
                        formaPrincipalSat = tipo1;

                        if (t1 > 0) {
                            pagosPayload.push({
                                forma_pago_sat: tipo1,
                                monto: Number(t1.toFixed(2)),
                                monto_recibido: Number(t1.toFixed(2)),
                                cambio_devuelto: 0,
                                referencia_bancaria: String(this.referenciaPago1 || '').trim() || null
                            });
                        }
                        if (t2 > 0) {
                            pagosPayload.push({
                                forma_pago_sat: tipo2,
                                monto: Number(t2.toFixed(2)),
                                monto_recibido: Number(t2.toFixed(2)),
                                cambio_devuelto: 0,
                                referencia_bancaria: String(this.referenciaPago2 || '').trim() || null
                            });
                        }
                    }

                    // Filtrar partidas con monto > 0 para evitar registros vacíos en venta_pagos
                    pagosPayload = pagosPayload.filter(p => Number(p.monto) > 0);

                    // Respaldo de seguridad por si el arreglo quedara vacío
                    if (pagosPayload.length === 0) {
                        pagosPayload = [{
                            forma_pago_sat: formaPrincipalSat || '01',
                            monto: totalVenta,
                            monto_recibido: totalVenta,
                            cambio_devuelto: 0,
                            referencia_bancaria: null
                        }];
                    }

                    const payloadVenta = {
                        uuid: uuidVenta,
                        sucursal_id: this.sucursal?.id || 1,
                        almacen_id: this.almacen?.id || 1,
                        caja_turno_id: this.turnoActivo?.id || null,
                        cliente_nombre: this.cliente.nombre,
                        cliente_rfc: this.cliente.rfc,
                        forma_pago_sat: formaPrincipalSat,
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
                        pagos: pagosPayload
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
                            window.open(`/pos/ticket/${data.venta_id || uuidVenta}`, '_blank');
                            this.vaciarCarrito();
                            this.modoCobroPos = 'simple';
                            this.formaPagoSat = '01';
                            this.montoMixtoEfectivo = 0;
                            this.montoMixtoSecundario = 0;
                            this.montoTarjeta1 = 0;
                            this.montoTarjeta2 = 0;
                            this.referenciaPago1 = '';
                            this.referenciaPago2 = '';
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

                // =====================================================================
                // INICIALIZACIÓN Y SOPORTE OFFLINE
                // =====================================================================
                async initPos() {
                    const tituloPos = `Punto de Venta | ${this.sucursal?.nombre || 'La Fogata Bistro'}`;
                    document.title = tituloPos;
                    setTimeout(() => { document.title = tituloPos; }, 100);

                    window.addEventListener('pointerdown', () => this.desbloquearAudioPos(), { once: true });
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        this.sincronizarLoteOffline();
                    });
                    window.addEventListener('offline', () => {
                        this.isOnline = false;
                    });

                    try {
                        await this.initIndexedDB();
                        await this.actualizarContadorOffline();
                    } catch (err) {
                        this.offlineQueueCount = 0;
                    }

                    await this.cargarCatalogo();
                    await this.cargarPromocionesVigentes();

                    if (this.routes.comandasAbiertas) {
                        this.consultarMesasAbiertas();
                        setInterval(() => this.consultarMesasAbiertas(), 5000);
                    }

                    this.iniciarEscuchaPrecuentas();
                },

                async initIndexedDB() {
                    return Promise.resolve();
                },

                async actualizarContadorOffline() {
                    this.offlineQueueCount = 0;
                },

                async sincronizarLoteOffline() {
                    this.isSyncing = false;
                },

                // =====================================================================
                // AUDIO Y ALERTAS DE PRECUENTA
                // =====================================================================
                normalizarMesa(nombre) {
                    return String(nombre || '')
                        .toLowerCase()
                        .replace(/^mesa\s+/i, '')
                        .trim();
                },

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

                probarTimbrePrecuenta() {
                    this.desbloquearAudioPos();
                    this.reproducirTimbrePrecuenta();
                },

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

                // =====================================================================
                // PLANO DE MESAS, COMANDAS Y DIVISIÓN DE CUENTA
                // =====================================================================
                async cargarPromocionesVigentes() {
                    try {
                        const res = await fetch('/marketing/promociones/vigentes-api', { headers: { 'Accept': 'application/json' } });
                        if (res.ok) this.promocionesVigentes = await res.json();
                    } catch (e) {}
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
                    const base = esFormaRedonda ? 'rounded-full w-24 h-24' : 'rounded-2xl w-24 h-24';
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
                    return (this.partidasDivision || [])
                        .filter(i => i.seleccionado)
                        .reduce((sum, i) => sum + ((Number(i.cantidad) || 0) * (Number(i.precio_unitario) || 0)), 0);
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
                            notas: 'Abono cuenta dividida'
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
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                    this.modalDividir = false;
                },

                // =====================================================================
                // OPERACIONES DE CARRITO Y CATÁLOGO
                // =====================================================================
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
                    if (this.carrito[index].cantidad > 1) this.carrito[index].cantidad -= 1;
                    else this.removerDelCarrito(index);
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                },

                removerDelCarrito(index) {
                    this.carrito.splice(index, 1);
                    this.pagoEfectivoRecibido = Number(this.totales.total.toFixed(2));
                },

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

                // =====================================================================
                // APERTURA Y CIERRE DE TURNO (SIN IMPRIMIR EN APERTURA / CORTE Z)
                // =====================================================================
                // Abre un nuevo turno de caja con el fondo inicial capturado (sin abrir ventana de impresión)
                async abrirTurnoCaja() {
                    const res = await fetch(this.routes.aperturaCaja, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({
                            sucursal_id: this.sucursal?.id || 1,
                            monto_inicial: Number(this.formApertura.monto_inicial) || 0,
                            observaciones: this.formApertura.observaciones
                        })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        this.turnoActivo = data.turno;
                        this.limpiarConteoApertura();
                        this.mostrarConteoApertura = false;
                        this.formApertura.observaciones = '';
                        this.modalApertura = false;
                    } else {
                        alert(data.message || 'Error abriendo caja');
                    }
                },

                // Cierra el turno activo, imprime el Corte Z y limpia el turno en sesión
                async cerrarTurnoCaja() {
                    if (!this.turnoActivo) return;
                    const turnoCerradoId = this.turnoActivo.id;
                    const res = await fetch(this.routes.cierreCaja, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({
                            caja_turno_id: turnoCerradoId,
                            monto_real_efectivo: this.formCierre.monto_real_efectivo,
                            observaciones: this.formCierre.observaciones
                        })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        window.open(`/pos/caja/${turnoCerradoId}/imprimir-corte?tipo=Z`, '_blank');
                        this.turnoActivo = null;
                        this.modalCierre = false;
                    } else {
                        alert(data.message || 'Error cerrando caja');
                    }
                },

                // =====================================================================
                // REIMPRESIÓN DE TICKETS Y FACTURACIÓN CFDI 4.0
                // =====================================================================
                abrirModalReimpresion() {
                    this.modalReimpresion = true;
                    this.cargarVentasRecientes();
                },

                async cargarVentasRecientes() {
                    this.cargandoVentas = true;
                    try {
                        const sucursalId = this.sucursal?.id || 1;
                        const turnoId = this.turnoActivo?.id || 0;
                        const res = await fetch(`${this.routes.ultimasVentas}?sucursal_id=${sucursalId}&caja_turno_id=${turnoId}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) this.ventasRecientes = await res.json();
                    } catch (e) {} finally {
                        this.cargandoVentas = false;
                    }
                },

                lanzarImpresion(id) {
                    window.open(`/pos/ticket/${id}`, '_blank');
                },

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

                abrirAltaClienteDesdeTicket(venta) {
                    this.ventaParaCliente = venta;
                    this.origenAltaDesdeBuscador = false;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;
                    this.formClienteCfdi = {
                        id: null,
                        nombre: venta.cliente_nombre && venta.cliente_nombre !== 'Público General' ? venta.cliente_nombre : '',
                        rfc: venta.cliente_rfc && venta.cliente_rfc !== 'XAXX010101000' ? venta.cliente_rfc : '',
                        nivel_lealtad: 'regular',
                        regimen_fiscal: '616',
                        uso_cfdi: 'G03',
                        codigo_postal: '',
                        email: '',
                        telefono_whatsapp: ''
                    };
                    this.modalFacturacion = false;
                    this.modalAltaClienteCfdi = true;
                },

                abrirBuscarClienteDesdeTicket(venta) {
                    this.ventaParaCliente = venta;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;
                    this.filtroClienteCfdi = '';
                    this.clientesEncontradosCfdi = [];
                    this.modalFacturacion = false;
                    this.modalBuscarClienteCfdi = true;
                    this.buscarClientesExistentes();
                },

                verOEditarClienteExistente(cli) {
                    if (!cli) return;

                    const registroActual = cli.id
                        ? ((this.clientesEncontradosCfdi || []).find(item => Number(item.id) === Number(cli.id)) || cli)
                        : cli;

                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;
                    this.origenAltaDesdeBuscador = true;

                    this.formClienteCfdi = {
                        id: registroActual.id || null,
                        nombre: registroActual.nombre || '',
                        rfc: registroActual.rfc ? String(registroActual.rfc).trim().toUpperCase() : '',
                        nivel_lealtad: registroActual.nivel_lealtad || 'regular',
                        regimen_fiscal: registroActual.regimen_fiscal ? String(registroActual.regimen_fiscal) : '616',
                        uso_cfdi: registroActual.uso_cfdi ? String(registroActual.uso_cfdi) : 'G03',
                        codigo_postal: registroActual.codigo_postal || registroActual.cp || '',
                        email: registroActual.email || registroActual.correo || '',
                        telefono_whatsapp: registroActual.telefono_whatsapp || registroActual.telefono || ''
                    };

                    this.modalBuscarClienteCfdi = false;
                    this.modalAltaClienteCfdi = true;
                },

                regresarAFacturacionDesdeBuscador() {
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;
                    this.modalBuscarClienteCfdi = false;
                    this.modalFacturacion = true;
                },

                regresarDesdeAltaEdicionCliente() {
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;
                    this.modalAltaClienteCfdi = false;
                    if (this.origenAltaDesdeBuscador) {
                        this.modalBuscarClienteCfdi = true;
                        this.buscarClientesExistentes();
                    } else {
                        this.modalFacturacion = true;
                    }
                },

                regresarABuscadorDesdeHomologar() {
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;
                    this.modalHomologarCliente = false;
                    this.modalBuscarClienteCfdi = true;
                },

                async buscarClientesExistentes() {
                    this.cargandoClientesCfdi = true;
                    try {
                        const q = encodeURIComponent((this.filtroClienteCfdi || '').trim());
                        const antiCache = Date.now();
                        const res = await fetch(`/pos/clientes-lealtad?q=${q}&_t=${antiCache}`, {
                            cache: 'no-store',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Cache-Control': 'no-cache'
                            }
                        });

                        if (res.ok) {
                            const raw = await res.json();
                            const lista = Array.isArray(raw)
                                ? raw
                                : (Array.isArray(raw?.data) ? raw.data : (Array.isArray(raw?.clientes) ? raw.clientes : []));

                            this.clientesEncontradosCfdi = lista.map(item => ({
                                id: item.id,
                                nombre: item.nombre || item.razon_social || item.nombre_completo || 'Sin nombre',
                                rfc: item.rfc ? String(item.rfc).trim().toUpperCase() : '',
                                nivel_lealtad: item.nivel_lealtad || 'regular',
                                regimen_fiscal: item.regimen_fiscal ? String(item.regimen_fiscal) : '616',
                                uso_cfdi: item.uso_cfdi || 'G03',
                                codigo_postal: item.codigo_postal || item.cp || '',
                                email: item.email || item.correo || '',
                                telefono_whatsapp: item.telefono_whatsapp || item.telefono || ''
                            }));
                        } else {
                            this.clientesEncontradosCfdi = [];
                        }
                    } catch (e) {
                        this.clientesEncontradosCfdi = [];
                    } finally {
                        this.cargandoClientesCfdi = false;
                    }
                },

                async persistirClienteEnVenta(ventaId, nombre, rfc) {
                    const res = await fetch(`/pos/ventas/${ventaId}/asignar-cliente`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            cliente_nombre: nombre,
                            cliente_rfc: rfc
                        })
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const detalleError = data.errors
                            ? Object.values(data.errors).flat().join(' ')
                            : (data.message || 'No se pudo asignar el cliente al ticket.');
                        throw new Error(detalleError);
                    }
                    return data;
                },

                async seleccionarClienteExistente(cli) {
                    if (!this.ventaParaCliente || !cli) return;
                    const rfcLimpio = String(cli.rfc || '').trim().toUpperCase();

                    if (rfcLimpio && rfcLimpio !== 'XAXX010101000' && !/^X+$/.test(rfcLimpio)) {
                        try {
                            await this.persistirClienteEnVenta(this.ventaParaCliente.id, cli.nombre, rfcLimpio);
                            await this.cargarVentasParaFacturar();
                            this.modalBuscarClienteCfdi = false;
                            this.modalFacturacion = true;
                            this.mensajeFactura = `Cliente "${cli.nombre}" (${rfcLimpio}) asignado al ticket. Ya puedes timbrar CFDI.`;
                            this.mensajeErrorFactura = false;
                        } catch (err) {
                            this.mensajeFactura = err.message || 'No se pudo asignar el cliente al ticket.';
                            this.mensajeErrorFactura = true;
                        }
                    } else {
                        this.ventaParaCliente.cliente_nombre = cli.nombre;
                        this.ventaParaCliente.cliente_rfc = rfcLimpio;
                        this.modalBuscarClienteCfdi = false;
                        this.modalFacturacion = true;
                        this.mensajeFactura = `El cliente "${cli.nombre}" no tiene un RFC fiscal distinto a XAXX010101000. Edítalo o da de alta uno válido.`;
                        this.mensajeErrorFactura = true;
                    }
                },

                async guardarClienteCfdi() {
                    const nombre = (this.formClienteCfdi.nombre || '').trim();
                    const rfc = (this.formClienteCfdi.rfc || '').trim().toUpperCase();

                    const regimenFiscalEnviar = this.formClienteCfdi.regimen_fiscal === '626_PM'
                        ? '626'
                        : (this.formClienteCfdi.regimen_fiscal || '616');

                    if (!nombre || !rfc || rfc === 'XAXX010101000' || /^X+$/.test(rfc)) {
                        this.mensajeFactura = 'Ingresa un nombre/razón social y un RFC fiscal válido (distinto a XAXX010101000 o XXXXX).';
                        this.mensajeErrorFactura = true;
                        return;
                    }

                    this.guardandoClienteCfdi = true;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;

                    try {
                        const res = await fetch('/marketing/promociones/clientes', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                id: this.formClienteCfdi.id || null,
                                nombre: nombre,
                                rfc: rfc,
                                nivel_lealtad: this.formClienteCfdi.nivel_lealtad || 'regular',
                                regimen_fiscal: regimenFiscalEnviar,
                                uso_cfdi: this.formClienteCfdi.uso_cfdi || 'G03',
                                codigo_postal: this.formClienteCfdi.codigo_postal || null,
                                email: this.formClienteCfdi.email || null,
                                telefono_whatsapp: this.formClienteCfdi.telefono_whatsapp || null
                            })
                        });

                        const data = await res.json().catch(() => ({}));

                        if (res.ok) {
                            const cliServer = data.cliente || {};
                            const clienteActualizado = {
                                id: cliServer.id || this.formClienteCfdi.id || null,
                                nombre: cliServer.nombre || nombre,
                                rfc: cliServer.rfc || rfc,
                                nivel_lealtad: cliServer.nivel_lealtad || this.formClienteCfdi.nivel_lealtad || 'regular',
                                regimen_fiscal: cliServer.regimen_fiscal ? String(cliServer.regimen_fiscal) : regimenFiscalEnviar,
                                uso_cfdi: cliServer.uso_cfdi || this.formClienteCfdi.uso_cfdi || 'G03',
                                codigo_postal: cliServer.codigo_postal ?? (this.formClienteCfdi.codigo_postal || ''),
                                email: cliServer.email ?? cliServer.correo ?? (this.formClienteCfdi.email || ''),
                                telefono_whatsapp: cliServer.telefono_whatsapp ?? (this.formClienteCfdi.telefono_whatsapp || '')
                            };

                            if (clienteActualizado.id) {
                                const idx = (this.clientesEncontradosCfdi || []).findIndex(c => c.id === clienteActualizado.id);
                                if (idx !== -1) {
                                    this.clientesEncontradosCfdi[idx] = clienteActualizado;
                                }
                            }

                            if (this.ventaParaCliente) {
                                await this.persistirClienteEnVenta(this.ventaParaCliente.id, clienteActualizado.nombre, clienteActualizado.rfc);
                                await this.cargarVentasParaFacturar();
                            }

                            this.mensajeFactura = `Cliente "${clienteActualizado.nombre}" (${clienteActualizado.rfc}) guardado y asignado al ticket.`;
                            this.mensajeErrorFactura = false;
                            this.modalAltaClienteCfdi = false;

                            if (this.origenAltaDesdeBuscador) {
                                this.modalBuscarClienteCfdi = true;
                                await this.buscarClientesExistentes();
                            } else {
                                this.modalFacturacion = true;
                            }
                        } else {
                            const detalleError = data.errors
                                ? Object.values(data.errors).flat().join(' ')
                                : (data.message || 'No se pudo guardar el cliente.');
                            this.mensajeFactura = detalleError;
                            this.mensajeErrorFactura = true;
                        }
                    } catch (e) {
                        this.mensajeFactura = e.message || 'Error de conexión al guardar el cliente.';
                        this.mensajeErrorFactura = true;
                    } finally {
                        this.guardandoClienteCfdi = false;
                    }
                },

                async eliminarClienteExistente(cli) {
                    if (!cli || !cli.id || this.eliminandoClienteId) return;

                    const confirmado = confirm(`¿Seguro que deseas eliminar al cliente "${cli.nombre || 'Sin nombre'}" (${cli.rfc || 'SIN RFC'})?`);
                    if (!confirmado) return;

                    this.eliminandoClienteId = cli.id;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;

                    try {
                        const res = await fetch(`/pos/clientes-lealtad/${cli.id}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        const data = await res.json().catch(() => ({}));

                        if (res.ok) {
                            this.clientesEncontradosCfdi = (this.clientesEncontradosCfdi || []).filter(
                                item => item.id !== cli.id
                            );
                            if (this.ventaParaCliente && this.ventaParaCliente.cliente_nombre === cli.nombre) {
                                this.ventaParaCliente.cliente_nombre = 'Público General';
                                this.ventaParaCliente.cliente_rfc = 'XAXX010101000';
                                await this.cargarVentasParaFacturar();
                            }
                            this.mensajeFactura = data.message || `Cliente "${cli.nombre}" eliminado correctamente.`;
                            this.mensajeErrorFactura = false;
                        } else {
                            this.mensajeFactura = data.message || 'No se pudo eliminar el cliente.';
                            this.mensajeErrorFactura = true;
                        }
                    } catch (e) {
                        this.mensajeFactura = 'Error de conexión al intentar eliminar el cliente.';
                        this.mensajeErrorFactura = true;
                    } finally {
                        this.eliminandoClienteId = null;
                    }
                },

                abrirModalHomologarCliente(cli) {
                    if (!cli) return;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;
                    this.clienteOrigenHomologar = cli;
                    this.clienteDestinoHomologarId = '';
                    this.modalBuscarClienteCfdi = false;
                    this.modalHomologarCliente = true;
                },

                async confirmarHomologacionCliente() {
                    if (!this.clienteOrigenHomologar || !this.clienteDestinoHomologarId || this.homologandoCliente) return;

                    this.homologandoCliente = true;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;

                    try {
                        const res = await fetch(`/pos/clientes-lealtad/${this.clienteOrigenHomologar.id}/homologar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                cliente_destino_id: Number(this.clienteDestinoHomologarId)
                            })
                        });

                        const data = await res.json().catch(() => ({}));

                        if (res.ok) {
                            this.modalHomologarCliente = false;
                            this.modalBuscarClienteCfdi = true;
                            this.mensajeFactura = data.message || 'Cliente homologado correctamente.';
                            this.mensajeErrorFactura = false;
                            await this.buscarClientesExistentes();
                            await this.cargarVentasParaFacturar();
                        } else {
                            const detalleError = data.errors
                                ? Object.values(data.errors).flat().join(' ')
                                : (data.message || 'No se pudo homologar el cliente.');
                            this.mensajeFactura = detalleError;
                            this.mensajeErrorFactura = true;
                        }
                    } catch (e) {
                        this.mensajeFactura = 'Error de conexión al homologar el cliente.';
                        this.mensajeErrorFactura = true;
                    } finally {
                        this.homologandoCliente = false;
                    }
                },

                esFacturaDelMesActual(venta) {
                    const fechaStr = venta?.facturado_at || venta?.created_at;
                    if (!fechaStr) return false;
                    const fecha = new Date(fechaStr);
                    if (Number.isNaN(fecha.getTime())) return false;
                    const ahora = new Date();
                    return fecha.getFullYear() === ahora.getFullYear() && fecha.getMonth() === ahora.getMonth();
                },

                async regenerarFacturaVenta(venta) {
                    if (!venta || this.regenerandoId) return;

                    if (!this.esFacturaDelMesActual(venta)) {
                        this.mensajeFactura = 'Solo se pueden regenerar facturas dentro del mes actual.';
                        this.mensajeErrorFactura = true;
                        return;
                    }

                    this.regenerandoId = venta.id;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;

                    try {
                        const res = await fetch(`/operacion/facturacion/ventas/${venta.id}/regenerar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json().catch(() => ({}));

                        if (res.ok) {
                            this.mensajeFactura = data.message || 'Factura regenerada correctamente.';
                            this.mensajeErrorFactura = false;
                            await this.cargarVentasParaFacturar();
                        } else {
                            this.mensajeFactura = data.message || 'No se pudo regenerar la factura.';
                            this.mensajeErrorFactura = true;
                        }
                    } catch (e) {
                        this.mensajeFactura = 'Error de conexión al regenerar la factura.';
                        this.mensajeErrorFactura = true;
                    } finally {
                        this.regenerandoId = null;
                    }
                },

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

                async enviarFacturaCorreo(venta) {
                    if (!venta || this.enviandoCorreoId) return;
                    this.enviandoCorreoId = venta.id;
                    this.mensajeFactura = '';
                    this.mensajeErrorFactura = false;

                    try {
                        const res = await fetch(`/operacion/facturacion/ventas/${venta.id}/enviar-correo`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok) {
                            this.mensajeFactura = data.message || `Factura del ticket ${venta.folio} enviada por correo.`;
                            this.mensajeErrorFactura = false;
                        } else {
                            this.mensajeFactura = data.message || 'No se pudo enviar la factura por correo.';
                            this.mensajeErrorFactura = true;
                        }
                    } catch (e) {
                        this.mensajeFactura = 'Error de conexión al enviar el correo.';
                        this.mensajeErrorFactura = true;
                    } finally {
                        this.enviandoCorreoId = null;
                    }
                },

                formatearFecha(f) {
                    return f ? new Date(f).toLocaleString() : '';
                }
            };
        }
    </script>
</x-app-layout>
