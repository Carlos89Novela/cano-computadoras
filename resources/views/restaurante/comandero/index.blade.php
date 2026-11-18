<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Comandero Digital | {{ $empresa->nombre ?? 'La Fogata Bistro' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .touch-action-manipulation { touch-action: manipulation; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-[#0b0f19] text-slate-100 min-h-screen font-sans select-none touch-action-manipulation">

    <div x-data='comanderoApp({
            sucursalId: {{ $sucursal?->id ?? 1 }},
            routes: {
                catalogo: "{{ route("restaurante.comandero.catalogo") }}",
                enviar: "{{ route("restaurante.comandero.enviar") }}",
                pedirCuenta: "{{ route("restaurante.comandero.pedir-cuenta-mesa") }}",
                csrf: "{{ csrf_token() }}"
            },
            extrasDisponibles: @json($extrasDisponibles ?? []),
            promocionesVigentes: @json($promocionesVigentes ?? [])
         })'
         class="h-screen flex flex-col p-3 lg:p-4">

        {{-- ENCABEZADO SUPERIOR --}}
        <header class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80 shrink-0">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-600/10 text-indigo-400 rounded-xl border border-indigo-500/20 text-xl flex items-center justify-center">
                    📱
                </div>
                <div>
                    <h1 class="text-base font-bold text-white flex items-center gap-2">
                        Comandero Digital
                        <span class="text-[11px] px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono" x-text="mesaActual"></span>
                    </h1>
                    <p class="text-[11px] text-slate-400 mt-0.5">Atiende: {{ auth()->user()->name ?? 'Mario Gerente' }} | {{ $empresa->nombre ?? 'La Fogata Bistro' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="modalMesa = true"
                        class="px-4 py-2 bg-slate-800/50 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold border border-slate-700 transition flex items-center gap-2 cursor-pointer shadow-sm">
                    <span>🪑</span> Cambiar Mesa
                </button>
                <a href="{{ route('restaurante.mesas.plano') }}" class="px-4 py-2 bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-slate-200 rounded-xl text-xs font-semibold border border-slate-800 transition flex items-center gap-2">
                    <span>🗺️</span> Plano
                </a>
                <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-slate-200 rounded-xl text-xs font-semibold border border-slate-800 transition">
                    Salir
                </a>
            </div>
        </header>

        {{-- ÁREA PRINCIPAL (CATÁLOGO Y ORDEN) --}}
        <main class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-4 overflow-hidden">

            {{-- PANEL IZQUIERDO (Categorías y Platillos) --}}
            <div class="md:col-span-8 lg:col-span-8 xl:col-span-9 flex flex-col h-full overflow-hidden space-y-4">

                {{-- Navegación de Categorías Horizontal --}}
                <div class="flex gap-2 overflow-x-auto pb-1 shrink-0 items-center hide-scrollbar border-b border-slate-800/50">
                    <button type="button" @click="seleccionarCategoria(0)"
                            :class="categoriaSeleccionada === 0 ? 'border-b-2 border-indigo-500 text-white font-bold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900/50'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition cursor-pointer font-semibold rounded-t-lg">
                        Todos
                    </button>

                    @foreach ($categorias as $cat)
                        <button type="button" @click="seleccionarCategoria({{ $cat->id }})"
                                :class="categoriaSeleccionada === {{ $cat->id }} ? 'border-b-2 border-indigo-500 text-white font-bold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900/50'"
                                class="px-4 py-2.5 text-xs whitespace-nowrap transition cursor-pointer font-semibold rounded-t-lg">
                            {{ $cat->nombre }}
                        </button>
                    @endforeach

                    {{-- Pestaña Fija de Extras Sueltos --}}
                    <button type="button" @click="seleccionarCategoria('extras')"
                            :class="categoriaSeleccionada === 'extras' ? 'border-b-2 border-amber-500 text-white font-bold' : 'text-amber-500/70 hover:text-amber-400 hover:bg-amber-900/20'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition cursor-pointer font-semibold flex items-center gap-1.5 rounded-t-lg ml-auto">
                        <span>🍟</span> Extras
                    </button>

                    {{-- Pestaña Fija de Promociones --}}
                    <button type="button" @click="seleccionarCategoria('promociones')"
                            :class="categoriaSeleccionada === 'promociones' ? 'border-b-2 border-purple-500 text-white font-bold' : 'text-purple-400/70 hover:text-purple-400 hover:bg-purple-900/20'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition cursor-pointer font-semibold flex items-center gap-1.5 rounded-t-lg">
                        <span>🎁</span> Promos
                    </button>
                </div>

                {{-- 1. GRID PRINCIPAL (Platillos del Menú) --}}
                {{-- Nota: Se agregó content-start items-start para que las tarjetas NO se estiren hacia abajo --}}
                <div class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 pr-2 hide-scrollbar pb-4 content-start items-start"
                     x-show="categoriaSeleccionada !== 'promociones' && categoriaSeleccionada !== 'extras'">

                    <template x-if="productos.length === 0 && !cargando">
                        <div class="col-span-full text-center py-20 text-slate-500 text-sm">
                            <div class="text-4xl mb-3">🍽️</div>
                            No hay platillos en esta categoría.
                        </div>
                    </template>

                    <template x-if="cargando">
                        <div class="col-span-full flex justify-center py-20">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
                        </div>
                    </template>

                    <template x-for="p in productos" :key="p.id">
                        <div @click="abrirModalExtras(p)"
                             class="h-28 p-3 bg-[#111827] border border-slate-800/80 rounded-2xl cursor-pointer flex flex-col justify-between hover:border-indigo-500/50 hover:bg-slate-900 transition-all shadow-lg active:scale-95 group">

                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[9px] uppercase font-bold px-2 py-0.5 rounded border"
                                          :class="(p.estacion === 'bar' || p.estacion === 'barra') ? 'bg-cyan-950/40 text-cyan-400 border-cyan-500/20' : 'bg-amber-950/40 text-amber-400 border-amber-500/20'"
                                          x-text="p.estacion"></span>
                                    <span class="text-[10px] text-slate-500 font-mono group-hover:text-slate-400" x-text="`${p.tiempo_min}m`"></span>
                                </div>
                                {{-- Forzamos a máximo 2 líneas de texto para que no estire la tarjeta --}}
                                <h3 class="text-[13px] font-bold text-white leading-snug line-clamp-2" title="" x-text="p.nombre"></h3>
                            </div>

                            <div class="pt-2 border-t border-slate-800/50 flex items-center justify-between text-xs mt-auto">
                                <span class="font-mono font-bold text-emerald-400 text-sm" x-text="'$' + Number(p.precio).toFixed(2)"></span>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- 2. GRID DE EXTRAS SUELTOS (Pestaña "Extras") --}}
                <div class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 pr-2 hide-scrollbar pb-4 content-start items-start"
                     x-show="categoriaSeleccionada === 'extras'" x-cloak>
                    <template x-for="ext in extrasDisponibles" :key="'extra-'+ext.id">
                        <div @click="agregarExtraSuelto(ext)"
                             class="h-28 p-3 bg-gradient-to-b from-[#111827] to-[#1a1f33] border border-amber-500/30 rounded-2xl cursor-pointer flex flex-col justify-between hover:border-amber-400 hover:bg-slate-900 transition-all shadow-lg active:scale-95">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[9px] uppercase font-bold px-2 py-0.5 rounded border bg-amber-950/40 text-amber-400 border-amber-500/20">Modificador</span>
                                </div>
                                <h3 class="text-[13px] font-bold text-white leading-snug line-clamp-2" x-text="ext.nombre"></h3>
                            </div>
                            <div class="pt-2 border-t border-slate-800/50 flex items-center justify-between text-xs mt-auto">
                                <span class="font-mono font-bold text-emerald-400 text-sm" x-text="'$' + Number(ext.precio_extra || ext.precio_base || 0).toFixed(2)"></span>
                                <span class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-300 flex items-center justify-center font-bold text-xs">+</span>
                            </div>
                        </div>
                    </template>
                    <template x-if="extrasDisponibles.length === 0">
                        <div class="col-span-full text-center py-20 text-slate-500 text-sm">
                            <div class="text-4xl mb-3">🍟</div>
                            No hay extras configurados en el sistema.
                        </div>
                    </template>
                </div>

                {{-- 3. GRID DE PROMOCIONES Y COMBOS (Pestaña "Promos") --}}
                <div class="flex-1 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pr-2 pb-4 hide-scrollbar content-start items-start"
                     x-show="categoriaSeleccionada === 'promociones'" x-cloak>
                    <template x-for="promo in promocionesVigentes" :key="promo.id">
                        <div class="p-4 bg-gradient-to-br from-purple-900/30 to-[#111827] border border-purple-500/30 rounded-2xl flex flex-col justify-between shadow-xl">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[9px] uppercase font-bold px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 font-mono tracking-wider"
                                          x-text="promo.tipo.replace(/_/g, ' ')"></span>
                                </div>
                                <h3 class="text-[15px] font-black text-white leading-tight mb-1.5" x-text="promo.nombre"></h3>
                                <p class="text-[11px] text-purple-300/70" x-text="promo.descripcion || 'Promoción especial para consumo en restaurante.'"></p>
                            </div>
                            <button type="button" @click="aplicarPromocion(promo)"
                                    class="w-full mt-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl transition shadow-lg shadow-purple-900/50 cursor-pointer flex items-center justify-center gap-2">
                                <span>🎉</span> Aplicar a la Comanda
                            </button>
                        </div>
                    </template>
                    <template x-if="promocionesVigentes.length === 0">
                        <div class="col-span-full text-center py-20 text-slate-500 text-sm">
                            <div class="text-3xl mb-3">🎁</div>
                            No hay promociones o combos activos en este horario.
                        </div>
                    </template>
                </div>

            </div>

            {{-- PANEL DERECHO (Orden de Mesa) --}}
            <div class="md:col-span-4 lg:col-span-4 xl:col-span-3 bg-[#111827] border border-slate-800 rounded-2xl flex flex-col h-full overflow-hidden shadow-2xl relative">

                <div class="flex items-center justify-between p-4 pb-3 border-b border-slate-800/80 bg-slate-900/50">
                    <h2 class="text-xs font-bold uppercase text-white tracking-wider flex items-center gap-2">
                        <span>📋</span> ORDEN: <span class="text-indigo-400 font-mono ml-1" x-text="mesaActual"></span>
                    </h2>
                    <button type="button" @click="vaciarOrden()" class="text-[11px] text-rose-400 font-semibold hover:text-rose-300 transition cursor-pointer">Vaciar</button>
                </div>

                <div class="flex-1 overflow-y-auto space-y-2 p-3 hide-scrollbar">
                    <template x-if="orden.length === 0">
                        <div class="text-center h-full flex flex-col items-center justify-center text-slate-500 text-[11px] px-4">
                            <div class="text-4xl mb-4 opacity-50">🍽️</div>
                            La comanda está vacía.<br>Selecciona platillos para agregarlos.
                        </div>
                    </template>

                    <template x-for="(item, idx) in orden" :key="idx">
                        <div class="p-3 bg-[#172033] border border-slate-800 rounded-xl space-y-2.5 relative">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold text-white text-xs leading-tight" x-text="item.nombre"></h4>
                                    <template x-if="item.extras && item.extras.length > 0">
                                        <div class="mt-1 space-y-0.5">
                                            <template x-for="ext in item.extras" :key="ext.id">
                                                <div class="text-[9px] text-indigo-300 flex justify-between pr-2">
                                                    <span x-text="`+ ${ext.nombre}`"></span>
                                                    <span class="font-mono opacity-70" x-text="`+$${Number(ext.precio_extra || ext.precio_base || 0).toFixed(2)}`"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                <div class="flex items-center bg-slate-900 border border-slate-700 rounded-lg shrink-0">
                                    <button type="button" @click="decrementar(idx)" class="w-7 h-7 text-slate-300 hover:text-white font-bold flex items-center justify-center cursor-pointer transition">-</button>
                                    <span class="w-6 text-center font-mono font-bold text-white text-[11px]" x-text="item.cantidad"></span>
                                    <button type="button" @click="incrementar(idx)" class="w-7 h-7 text-slate-300 hover:text-white font-bold flex items-center justify-center cursor-pointer transition">+</button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1.5 border-t border-slate-800/80">
                                <div class="flex items-center gap-2 flex-1">
                                    <span class="font-mono text-emerald-400 font-bold text-xs" x-text="'$' + calcularSubtotalItem(item).toFixed(2)"></span>
                                    <input type="text" x-model="item.notas_especiales" placeholder="Notas a cocina..."
                                           class="w-full bg-slate-900 border border-slate-800 rounded-md px-2 py-1 text-[10px] text-amber-400 placeholder-slate-600 focus:outline-none focus:border-indigo-500 transition">
                                </div>
                                <button type="button" @click="eliminar(idx)" class="w-6 h-6 ml-2 rounded text-rose-500 hover:text-rose-400 hover:bg-rose-500/10 flex items-center justify-center cursor-pointer transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Totales --}}
                <div class="p-4 bg-slate-900/80 border-t border-slate-800 shrink-0">
                    <div class="flex items-end justify-between mb-4">
                        <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Comanda:</span>
                        <span class="font-mono font-black text-emerald-400 text-xl" x-text="'$' + totalOrden.toFixed(2)"></span>
                    </div>

                    <div class="space-y-2">
                        <button type="button" @click="pedirCuenta()"
                                :disabled="orden.length > 0"
                                :class="orden.length > 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-800 hover:border-amber-500/40 cursor-pointer'"
                                class="w-full py-2.5 bg-slate-800/80 text-amber-400 border border-slate-700 font-bold text-xs rounded-xl transition flex items-center justify-center gap-2">
                            <span>🧾</span> Pedir Cuenta a Caja (Precuenta)
                        </button>

                        <button type="button"
                                @click="enviarOrdenACocina()"
                                :disabled="orden.length === 0 || enviando"
                                class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-500 disabled:bg-slate-800 disabled:text-slate-500 text-white font-bold rounded-xl text-[13px] shadow-lg shadow-emerald-900/20 transition flex items-center justify-center gap-2 cursor-pointer">
                            <span x-show="!enviando">🚀 Enviar a Cocina</span>
                            <span x-show="enviando">Enviando a KDS...</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>

        {{-- MODAL DE EXTRAS --}}
        <div x-show="modalExtras" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalExtras = false" x-transition.opacity
                 class="bg-[#111827] border border-slate-700 rounded-2xl max-w-sm w-full shadow-2xl flex flex-col max-h-[85vh]">
                <div class="p-4 border-b border-slate-800 flex justify-between items-start">
                    <div>
                        <span class="text-[9px] uppercase font-bold px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20" x-text="productoSeleccionado?.estacion"></span>
                        <h3 class="text-[15px] font-bold text-white mt-2 leading-tight pr-4" x-text="productoSeleccionado?.nombre"></h3>
                        <p class="text-xs font-mono font-bold text-emerald-400 mt-1" x-text="productoSeleccionado ? '$' + Number(productoSeleccionado.precio).toFixed(2) : ''"></p>
                    </div>
                    <button @click="modalExtras = false" class="text-slate-400 hover:text-white p-1 rounded-lg bg-slate-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-4 overflow-y-auto flex-1 hide-scrollbar">
                    <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3">Agregar Extras / Modificadores:</h4>
                    <div class="space-y-2">
                        <template x-for="ext in extrasDisponibles" :key="ext.id">
                            <label class="flex items-center justify-between p-3 bg-slate-900/80 border border-slate-800 rounded-xl cursor-pointer hover:bg-slate-800 transition">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" :value="ext.id" @change="toggleExtra(ext)" class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-indigo-600">
                                    <span class="text-[13px] font-semibold text-slate-200" x-text="ext.nombre"></span>
                                </div>
                                <span class="text-xs font-mono font-bold text-indigo-400" x-text="'+$' + Number(ext.precio_extra || ext.precio_base || 0).toFixed(2)"></span>
                            </label>
                        </template>
                    </div>
                    <div class="mt-5">
                        <label class="text-[11px] text-slate-400 font-bold block mb-2 uppercase tracking-wider">Instrucciones a Cocina:</label>
                        <input type="text" x-model="notaTemporal" placeholder="ej. Término medio..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-3 text-xs text-white shadow-inner">
                    </div>
                </div>
                <div class="p-4 border-t border-slate-800 bg-slate-900/50 rounded-b-2xl">
                    <button type="button" @click="confirmarAgregarItem()" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[13px] rounded-xl transition shadow-lg cursor-pointer">
                        Agregar a la Comanda
                    </button>
                </div>
            </div>
        </div>

        {{-- MODAL MESAS --}}
        <div x-show="modalMesa" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalMesa = false" class="bg-[#111827] border border-slate-700 rounded-2xl max-w-sm w-full p-5 shadow-2xl flex flex-col gap-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Seleccionar Mesa</h3>
                <div class="grid grid-cols-3 gap-2.5">
                    <template x-for="m in ['Mesa 1', 'Mesa 2', 'Mesa 3', 'Mesa 4', 'Mesa 5', 'Terraza 1', 'Terraza 2', 'Barra', 'Llevar']" :key="m">
                        <button type="button" @click="mesaActual = m; modalMesa = false"
                                :class="mesaActual === m ? 'bg-indigo-600 text-white border-indigo-500 shadow-lg' : 'bg-slate-900 text-slate-300 border-slate-800 hover:bg-slate-800'"
                                class="py-3 rounded-xl text-xs font-semibold transition cursor-pointer border" x-text="m"></button>
                    </template>
                </div>
            </div>
        </div>

    </div>

    {{-- LÓGICA ALPINE --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('comanderoApp', (config) => ({
                sucursalId: config.sucursalId,
                routes: config.routes,
                extrasDisponibles: config.extrasDisponibles || [],
                promocionesVigentes: config.promocionesVigentes || [],
                productos: [],
                orden: [],
                categoriaSeleccionada: 0,
                mesaActual: 'Mesa 1',
                modalMesa: false,
                modalExtras: false,
                productoSeleccionado: null,
                extrasSeleccionados: [],
                notaTemporal: '',
                enviando: false,
                cargando: false,

                get totalOrden() {
                    return this.orden.reduce((sum, item) => sum + this.calcularSubtotalItem(item), 0);
                },

                async init() {
                    await this.cargarProductos();
                },

                async seleccionarCategoria(catId) {
                    this.categoriaSeleccionada = catId;
                    // Solo recarga productos vía AJAX si NO es la pestaña Promociones ni Extras
                    if (catId !== 'promociones' && catId !== 'extras') {
                        await this.cargarProductos();
                    }
                },

                async cargarProductos() {
                    this.cargando = true;
                    this.productos = [];
                    try {
                        const res = await fetch(`${this.routes.catalogo}?categoria_id=${this.categoriaSeleccionada}`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (res.ok) {
                            this.productos = await res.json();
                        }
                    } catch (e) {
                        console.error('Error AJAX:', e);
                    } finally {
                        this.cargando = false;
                    }
                },

                abrirModalExtras(producto) {
                    this.productoSeleccionado = JSON.parse(JSON.stringify(producto));
                    this.extrasSeleccionados = [];
                    this.notaTemporal = '';
                    document.querySelectorAll('input[type="checkbox"]').forEach(el => el.checked = false);
                    this.modalExtras = true;
                },

                toggleExtra(ext) {
                    const idx = this.extrasSeleccionados.findIndex(e => e.id === ext.id);
                    if (idx > -1) {
                        this.extrasSeleccionados.splice(idx, 1);
                    } else {
                        this.extrasSeleccionados.push(JSON.parse(JSON.stringify(ext)));
                    }
                },

                confirmarAgregarItem() {
                    if (!this.productoSeleccionado) return;
                    this.orden.push({
                        producto_id: this.productoSeleccionado.id,
                        nombre: this.productoSeleccionado.nombre,
                        precio: Number(this.productoSeleccionado.precio),
                        cantidad: 1,
                        extras: [...this.extrasSeleccionados],
                        notas_especiales: this.notaTemporal.trim()
                    });
                    this.modalExtras = false;
                },

                // Nueva función para agregar un extra como un platillo independiente
                agregarExtraSuelto(ext) {
                    this.orden.push({
                        producto_id: ext.id,
                        nombre: `[Extra] ${ext.nombre}`,
                        precio: Number(ext.precio_extra || ext.precio_base || 0),
                        cantidad: 1,
                        extras: [],
                        notas_especiales: ''
                    });
                },

                aplicarPromocion(promo) {
                    if (promo.items && promo.items.length > 0) {
                        promo.items.forEach(pi => {
                            if (pi.producto) {
                                this.orden.push({
                                    producto_id: pi.producto.id,
                                    nombre: `[PROMO] ${pi.producto.nombre}`,
                                    precio: Number(pi.producto.precio_base || 0),
                                    cantidad: Number(pi.cantidad || 1),
                                    extras: [],
                                    notas_especiales: `Promo/Combo: ${promo.nombre}`
                                });
                            }
                        });
                    }
                },

                calcularSubtotalItem(item) {
                    const base = Number(item.precio || 0);
                    const extSuma = (item.extras || []).reduce((s, e) => s + Number(e.precio_extra || e.precio_base || 0), 0);
                    return (base + extSuma) * Number(item.cantidad || 1);
                },

                incrementar(idx) { this.orden[idx].cantidad += 1; },
                decrementar(idx) { if (this.orden[idx].cantidad > 1) this.orden[idx].cantidad -= 1; },
                eliminar(idx) { this.orden.splice(idx, 1); },
                vaciarOrden() { if (confirm('¿Vaciar orden completa?')) this.orden = []; },

                async pedirCuenta() {
                    if (this.orden.length > 0) {
                        alert('Aún tienes platillos sin enviar a cocina.');
                        return;
                    }
                    if (!confirm(`¿Pedir PRECUENTA para ${this.mesaActual}?`)) return;
                    try {
                        const res = await fetch(this.routes.pedirCuenta, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.routes.csrf, 'Accept': 'application/json' },
                            body: JSON.stringify({ mesa: this.mesaActual })
                        });
                        const data = await res.json();
                        alert((res.ok ? '✓ ' : 'Error: ') + data.message);
                    } catch (e) {
                        alert('Error de conexión.');
                    }
                },

                async enviarOrdenACocina() {
                    if (this.orden.length === 0 || this.enviando) return;
                    this.enviando = true;
                    const payload = {
                        sucursal_id: this.sucursalId,
                        mesa_o_referencia: this.mesaActual,
                        items: this.orden.map(i => {
                            let nf = (i.notas_especiales || '').trim();
                            if (i.extras && i.extras.length > 0) nf += (nf ? ' | ' : '') + `[Extras: ${i.extras.map(e => e.nombre).join(', ')}]`;
                            return { producto_id: i.producto_id, cantidad: i.cantidad, notas_especiales: nf };
                        })
                    };
                    try {
                        const res = await fetch(this.routes.enviar, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.routes.csrf, 'Accept': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        if (res.ok) this.orden = [];
                        else alert((await res.json()).message || 'Error en servidor.');
                    } catch (e) {
                        alert('Sin conexión.');
                    } finally {
                        this.enviando = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>
