<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    {{-- ===================================================================== --}}
    {{-- SECCIÓN 1: METADATOS, ESTILOS Y NOTIFICACIONES FLOTANTES KDS          --}}
    {{-- ===================================================================== --}}
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Comandero Digital | {{ $empresa->nombre ?? 'La Fogata Bistro' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .touch-action-manipulation { touch-action: manipulation; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .kds-toast-container {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 380px;
            width: calc(100% - 32px);
            pointer-events: none;
        }

        .kds-toast-card {
            pointer-events: auto;
            background: rgba(15, 23, 42, 0.96);
            border: 1px solid rgba(52, 211, 153, 0.65);
            color: #f1f5f9;
            padding: 12px 16px;
            border-radius: 16px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            opacity: 1;
            filter: blur(0px);
            transform: translateY(0) scale(1);
            transition: opacity 1.5s ease-in-out, filter 1.5s ease-in-out, transform 1.5s ease-in-out;
        }

        .kds-toast-card.is-fading {
            opacity: 0;
            filter: blur(10px);
            transform: translateY(-14px) scale(0.96);
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-[#0b0f19] text-slate-100 h-screen overflow-hidden font-sans select-none touch-action-manipulation"
      x-data="comanderoApp">

    {{-- NOTIFICACIONES FLOTANTES KDS Y AVISOS DE SISTEMA DENTRO DE LA PÁGINA --}}
    <div class="kds-toast-container">
        <template x-for="notif in notificacionesKds" :key="notif.id">
            <div class="kds-toast-card"
                 :class="[
                     notif.fading ? 'is-fading' : '',
                     notif.tipo === 'error' ? '!border-rose-500/70' : (notif.tipo === 'aviso' ? '!border-amber-400/70' : '!border-emerald-400/65')
                 ]">
                <div class="p-2 rounded-xl border text-lg shrink-0"
                     :class="notif.tipo === 'error' ? 'bg-rose-500/15 text-rose-300 border-rose-500/30' : (notif.tipo === 'aviso' ? 'bg-amber-500/15 text-amber-300 border-amber-500/30' : 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30')"
                     x-text="notif.icono || '🔔'"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider truncate"
                            :class="notif.tipo === 'error' ? 'text-rose-300' : (notif.tipo === 'aviso' ? 'text-amber-300' : 'text-emerald-300')"
                            x-text="notif.titulo"></h4>
                        <span class="text-[10px] font-mono text-slate-400 shrink-0" x-text="notif.hora"></span>
                    </div>
                    <p class="text-xs text-slate-200 mt-0.5 leading-snug break-words" x-text="notif.mensaje"></p>
                </div>
            </div>
        </template>
    </div>

    <div class="h-screen flex flex-col p-3 lg:p-4 overflow-hidden relative">

        {{-- ================================================================= --}}
        {{-- SECCIÓN 2: ENCABEZADO DEL COMANDERO                               --}}
        {{-- ================================================================= --}}
        <header class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80 shrink-0">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-600/10 text-indigo-400 rounded-xl border border-indigo-500/20 text-xl flex items-center justify-center">📱</div>
                <div>
                    <h1 class="text-base font-bold text-white flex items-center gap-2">
                        Comandero Digital
                        <span class="text-[11px] px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono" x-text="mesaActual"></span>
                    </h1>
                    <p class="text-[11px] text-slate-400 mt-0.5">Atiende: {{ auth()->user()->name ?? 'Mario Gerente' }} | {{ $empresa->nombre ?? 'La Fogata Bistro' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="modalMesa = true" class="px-4 py-2 bg-slate-800/50 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold border border-slate-700 transition flex items-center gap-2">
                    <span>🪑</span> Cambiar Mesa
                </button>
                <a href="{{ route('restaurante.mesas.plano') }}" class="px-4 py-2 bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-slate-200 rounded-xl text-xs font-semibold border border-slate-800 transition"><span>🗺️</span> Plano</a>
                <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-slate-900/80 hover:bg-slate-800 text-slate-400 rounded-xl text-xs font-semibold border border-slate-800 transition">Salir</a>
            </div>
        </header>

        {{-- ================================================================= --}}
        {{-- SECCIÓN 3: CONTENEDOR DE 2 COLUMNAS (MENÚ Y ORDEN DE MESA)        --}}
        {{-- ================================================================= --}}
        <main class="flex-1 grid gap-4 min-h-0 overflow-hidden pb-1" style="grid-template-columns: 1fr 370px;">

            {{-- MENÚ IZQUIERDO --}}
            <div class="flex flex-col min-h-0 h-full overflow-hidden space-y-3">
                <div class="flex gap-2 overflow-x-auto pb-1 shrink-0 items-center hide-scrollbar border-b border-slate-800/50">
                    <button type="button" @click="seleccionarCategoria(0)" :class="categoriaSeleccionada === 0 ? 'border-b-2 border-indigo-500 text-white font-bold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900/50'" class="px-4 py-2.5 text-xs whitespace-nowrap transition cursor-pointer font-semibold rounded-t-lg">Todos</button>
                    @foreach ($categorias as $cat)
                        <button type="button" @click="seleccionarCategoria({{ $cat->id }})" :class="categoriaSeleccionada === {{ $cat->id }} ? 'border-b-2 border-indigo-500 text-white font-bold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900/50'" class="px-4 py-2.5 text-xs whitespace-nowrap transition cursor-pointer font-semibold rounded-t-lg">{{ $cat->nombre }}</button>
                    @endforeach
                    <button type="button" @click="seleccionarCategoria('extras')" class="px-3 py-1.5 text-xs whitespace-nowrap transition cursor-pointer font-semibold flex items-center gap-1.5 rounded-xl border bg-amber-500/20 text-amber-300 border-amber-500/40 hover:bg-amber-500/30 ml-auto"><span>🍟</span> Extras</button>
                    <button type="button" @click="seleccionarCategoria('promociones')" class="px-3 py-1.5 text-xs whitespace-nowrap transition cursor-pointer font-semibold flex items-center gap-1.5 rounded-xl border bg-purple-500/20 text-purple-300 border-purple-500/40 hover:bg-purple-500/30"><span>🎁</span> Promos</button>
                </div>

                {{-- GRID PRODUCTOS --}}
                <div class="flex-1 overflow-y-auto pr-2 hide-scrollbar content-start" x-show="categoriaSeleccionada !== 'promociones' && categoriaSeleccionada !== 'extras'">
                    <div class="grid gap-2.5" style="grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));">
                        <template x-for="p in productos" :key="p.id">
                            <div @click="abrirModalExtras(p)" class="h-28 p-3 bg-slate-800/80 border border-slate-700/60 rounded-xl cursor-pointer flex flex-col justify-between hover:bg-slate-700 hover:border-indigo-500/50 transition-all shadow-sm relative overflow-hidden group">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded border" :class="(p.estacion === 'bar' || p.estacion === 'barra') ? 'bg-cyan-950/40 text-cyan-400 border-cyan-500/20' : 'bg-amber-950/40 text-amber-400 border-amber-500/20'" x-text="p.estacion || 'GENERAL'"></span>
                                    </div>
                                    <h3 class="text-xs font-bold text-slate-100 group-hover:text-white line-clamp-2 leading-snug" x-text="p.nombre"></h3>
                                </div>
                                <div class="flex items-center justify-between mt-auto">
                                    <span class="text-xs font-extrabold text-emerald-400 font-mono" x-text="'$' + Number(p.precio).toFixed(2)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- GRID EXTRAS --}}
                <div class="flex-1 overflow-y-auto pr-2 hide-scrollbar content-start" x-show="categoriaSeleccionada === 'extras'" x-cloak>
                    <div class="grid gap-2.5" style="grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));">
                        <template x-for="ext in extrasUnicos" :key="'extra-'+ext.id">
                            <div @click="agregarExtraSuelto(ext)" class="h-28 p-3 bg-[#1c1811] border border-amber-500/60 rounded-xl cursor-pointer flex flex-col justify-between hover:border-amber-400 hover:bg-[#262016] transition-all shadow-md relative overflow-hidden group">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded border bg-amber-950/85 text-amber-300 border-amber-500/50">Modificador</span>
                                    </div>
                                    <h3 class="text-xs font-bold text-amber-200 group-hover:text-amber-100 line-clamp-2 leading-snug" x-text="ext.nombre"></h3>
                                </div>
                                <div class="flex items-center justify-between mt-auto">
                                    <span class="text-xs font-extrabold text-emerald-400 font-mono" x-text="'$' + Number(ext.precio_extra || ext.precio_base || 0).toFixed(2)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- GRID PROMOS --}}
                <div class="flex-1 overflow-y-auto pr-2 hide-scrollbar content-start" x-show="categoriaSeleccionada === 'promociones'" x-cloak>
                    <div class="grid gap-2.5" style="grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));">
                        <template x-for="promo in promocionesVigentes" :key="'promo-'+promo.id">
                            <div class="h-28 p-3 bg-[#19111f] border border-purple-500/60 rounded-xl cursor-pointer flex flex-col justify-between hover:border-purple-400 hover:bg-[#23172c] transition-all shadow-md relative overflow-hidden group">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded border bg-purple-950/85 text-purple-300 border-purple-500/50">Promoción</span>
                                    </div>
                                    <h3 class="text-xs font-bold text-purple-200 group-hover:text-purple-100 line-clamp-2 leading-snug" x-text="promo.nombre"></h3>
                                </div>
                                <div class="flex items-center justify-between mt-auto">
                                    <span class="text-xs font-extrabold text-emerald-400 font-mono" x-text="'$' + Number(promo.precio || 0).toFixed(2)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- TICKET DERECHO --}}
            <div class="bg-[#111827] border border-slate-800 rounded-2xl flex flex-col min-h-0 h-full overflow-hidden shadow-2xl relative">
                <div class="flex items-center justify-between p-3.5 pb-2.5 border-b border-slate-800/80 bg-slate-900/50 shrink-0">
                    <h2 class="text-xs font-bold uppercase text-white tracking-wider flex items-center gap-2"><span>📋</span> ORDEN: <span class="text-indigo-400 font-mono ml-1" x-text="mesaActual"></span></h2>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="cargarConsumoMesa()" class="text-[10px] text-indigo-300 hover:text-indigo-200 transition" title="Actualizar consumo de la mesa">🔄</button>
                        <button type="button" @click="solicitarConfirmacionVaciarOrden()" class="text-[11px] text-rose-400 hover:text-rose-300 transition">Vaciar</button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto space-y-3 p-3 hide-scrollbar">

                    {{-- YA PEDIDO EN ESTA MESA (CONSUMO ACTIVO) --}}
                    <template x-if="consumoMesa.length > 0">
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between px-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400/90">🍽️ Ya pedido en <span x-text="mesaActual"></span></span>
                                <span class="text-[10px] font-mono text-slate-400" x-text="consumoMesa.length + ' ítem(s)'"></span>
                            </div>
                            <template x-for="(cItem, cIdx) in consumoMesa" :key="'consumo-' + (cItem.id || cIdx)">
                                <div class="p-2 bg-slate-900/75 border border-slate-800/90 rounded-xl flex items-center justify-between gap-2 text-xs">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-slate-200 truncate">
                                            <span class="font-mono text-indigo-400 mr-1" x-text="(cItem.cantidad || 1) + 'x'"></span>
                                            <span x-text="cItem.nombre || cItem.producto?.nombre || 'Platillo'"></span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[9px] uppercase px-1.5 py-0.2 rounded bg-slate-800 text-slate-400" x-text="cItem.estacion_cocina || cItem.estacion || 'cocina'"></span>
                                            <span class="text-[9px] uppercase font-bold"
                                                  :class="(cItem.estado === 'listo') ? 'text-emerald-400' : 'text-amber-400'"
                                                  x-text="cItem.estado === 'listo' ? '✓ Listo' : '⏳ En preparación'"></span>
                                        </div>
                                    </div>
                                    <span class="font-mono text-slate-300 text-[11px] shrink-0" x-text="'$' + Number(cItem.subtotal || ((cItem.precio || 0) * (cItem.cantidad || 1))).toFixed(2)"></span>
                                </div>
                            </template>
                            <div class="border-t border-dashed border-slate-800 pt-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400/90 px-1">➕ Por enviar ahora</span>
                            </div>
                        </div>
                    </template>

                    {{-- ITEMS NUEVOS EN CARRITO --}}
                    <template x-for="(item, idx) in orden" :key="idx">
                        <div class="p-2.5 bg-[#172033] border border-slate-800 rounded-xl space-y-2">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold text-white text-xs leading-tight" x-text="item.nombre"></h4>
                                    <template x-if="item.extras && item.extras.length > 0">
                                        <div class="mt-1 space-y-0.5">
                                            <template x-for="ext in item.extras" :key="ext.id">
                                                <div class="text-[9px] text-indigo-300 flex justify-between pr-2">
                                                    <span x-text="`+ ${ext.nombre}`"></span><span class="font-mono opacity-70" x-text="`+$${Number(ext.precio_extra || ext.precio_base || 0).toFixed(2)}`"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex items-center bg-slate-900 border border-slate-700 rounded-lg shrink-0">
                                    <button @click="decrementar(idx)" class="w-6 h-6 text-slate-300">-</button>
                                    <span class="w-5 text-center font-mono font-bold text-white text-[11px]" x-text="item.cantidad"></span>
                                    <button @click="incrementar(idx)" class="w-6 h-6 text-slate-300">+</button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between pt-1 border-t border-slate-800/80">
                                <span class="font-mono text-emerald-400 font-bold text-xs" x-text="'$' + calcularSubtotalItem(item).toFixed(2)"></span>
                                <button @click="eliminar(idx)" class="text-rose-500">&times;</button>
                            </div>
                        </div>
                    </template>

                    <div x-show="orden.length === 0 && consumoMesa.length === 0" class="h-32 flex items-center justify-center text-xs text-slate-500 text-center px-4">
                        Sin ítems en esta mesa. Selecciona platillos del menú para comenzar.
                    </div>
                </div>

                <div class="p-3.5 bg-slate-900/80 border-t border-slate-800 shrink-0">
                    <div class="flex items-end justify-between mb-1">
                        <span class="text-[11px] text-slate-400 font-semibold">Ya pedido:</span>
                        <span class="font-mono font-bold text-slate-300 text-xs" x-text="'$' + totalConsumoMesa.toFixed(2)"></span>
                    </div>
                    <div class="flex items-end justify-between mb-3">
                        <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Comanda:</span>
                        <span class="font-mono font-black text-emerald-400 text-lg" x-text="'$' + (totalOrden + totalConsumoMesa).toFixed(2)"></span>
                    </div>
                    <div class="space-y-1.5">
                        <button type="button" @click="abrirModalPedirCuenta()" class="w-full py-2 bg-slate-800/80 hover:bg-slate-800 text-amber-400 border border-slate-700 font-bold text-xs rounded-xl transition cursor-pointer">🧾 Pedir Cuenta</button>
                        <button type="button" @click="enviarOrdenACocina()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition cursor-pointer">🚀 Enviar a Cocina</button>
                    </div>
                </div>
            </div>
        </main>

        {{-- ================================================================= --}}
        {{-- SECCIÓN 4: MODAL DE CONFIRMACIÓN INTEGRADO EN PÁGINA              --}}
        {{-- ================================================================= --}}
        <div x-show="modalConfirmacion.abierto" x-cloak
             class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="cerrarModalConfirmacion()"
                 class="bg-[#111827] border border-slate-700 rounded-2xl max-w-sm w-full p-5 shadow-2xl space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <span class="text-xl" x-text="modalConfirmacion.icono || '🧾'"></span>
                        <h3 class="text-sm font-bold text-white" x-text="modalConfirmacion.titulo"></h3>
                    </div>
                    <button type="button" @click="cerrarModalConfirmacion()" class="text-slate-400 hover:text-white text-lg leading-none cursor-pointer">&times;</button>
                </div>

                <p class="text-xs text-slate-300 leading-relaxed" x-text="modalConfirmacion.mensaje"></p>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="cerrarModalConfirmacion()"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700 transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="button" @click="confirmarAccionModal()"
                            :disabled="modalConfirmacion.procesando"
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition cursor-pointer">
                        <span x-show="!modalConfirmacion.procesando" x-text="modalConfirmacion.textoConfirmar || 'Confirmar'"></span>
                        <span x-show="modalConfirmacion.procesando">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ================================================================= --}}
        {{-- SECCIÓN 5: MODAL EXTRAS Y MODIFICADORES                            --}}
        {{-- ================================================================= --}}
        <div x-show="modalExtras" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalExtras = false" class="bg-[#111827] border border-slate-700 rounded-2xl max-w-sm w-full shadow-2xl flex flex-col max-h-[85vh]">
                <div class="p-4 border-b border-slate-800 flex justify-between items-start">
                    <div>
                        <span class="text-[9px] uppercase font-bold px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400" x-text="productoSeleccionado?.estacion"></span>
                        <h3 class="text-[15px] font-bold text-white mt-2 leading-tight pr-4" x-text="productoSeleccionado?.nombre"></h3>
                    </div>
                    <button @click="modalExtras = false" class="text-slate-400 text-xl">&times;</button>
                </div>
                <div class="p-4 overflow-y-auto flex-1 hide-scrollbar">
                    <template x-if="requiereLeche()">
                        <div class="mb-5 p-3 bg-slate-900/80 border border-slate-700 rounded-xl">
                            <label class="text-[11px] text-slate-400 font-bold block mb-2 uppercase tracking-wider">🥛 Elegir Leche (Base):</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" @click="lecheSeleccionada = null"
                                        :class="lecheSeleccionada === null ? 'bg-indigo-600 text-white border-indigo-500 shadow-md' : 'bg-slate-950 text-slate-400 border-slate-800 hover:bg-slate-800'"
                                        class="py-2.5 px-2 rounded-lg text-xs font-semibold border transition cursor-pointer">
                                    Entera (Normal)
                                </button>
                                <template x-for="leche in lechesDisponibles" :key="leche.id">
                                    <button type="button" @click="lecheSeleccionada = leche"
                                            :class="lecheSeleccionada?.id === leche.id ? 'bg-indigo-600 text-white border-indigo-500 shadow-md' : 'bg-slate-950 text-slate-400 border-slate-800 hover:bg-slate-800'"
                                            class="py-2 px-2 rounded-lg text-xs font-semibold border transition cursor-pointer flex flex-col items-center justify-center gap-1">
                                        <span x-text="leche.nombre.replace('Leche de ', '').replace('Leche ', '')"></span>
                                        <span class="opacity-75 text-[10px]" x-text="'+$' + Number(leche.precio_extra || leche.precio_base).toFixed(2)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3">Agregar Modificadores:</h4>
                    <div class="space-y-2">
                        <template x-for="ext in extrasFiltradosPorEstacion" :key="ext.id">
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
                        <label class="text-[11px] text-slate-400 font-bold block mb-2 uppercase tracking-wider">Instrucciones:</label>
                        <input type="text" x-model="notaTemporal" placeholder="ej. Sin espuma..." class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-3 text-xs text-white">
                    </div>
                </div>
                <div class="p-4 border-t border-slate-800 bg-slate-900/50 rounded-b-2xl">
                    <button type="button" @click="confirmarAgregarItem()" class="w-full py-3 bg-indigo-600 text-white font-bold text-[13px] rounded-xl shadow-lg cursor-pointer">
                        Agregar a la Comanda
                    </button>
                </div>
            </div>
        </div>

        {{-- ================================================================= --}}
        {{-- SECCIÓN 6: MODAL SELECCIÓN DE MESA                                --}}
        {{-- ================================================================= --}}
        <div x-show="modalMesa" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalMesa = false" class="bg-[#111827] border border-slate-700 rounded-2xl max-w-md w-full p-5 flex flex-col gap-4 max-h-[85vh] overflow-y-auto hide-scrollbar">
                <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                    <h3 class="text-xs font-bold uppercase text-white tracking-wider">Seleccionar Mesa</h3>
                    <button @click="modalMesa = false" class="text-slate-400 text-lg">&times;</button>
                </div>
                <div class="grid grid-cols-3 gap-2.5">
                    @foreach($mesas as $mesa)
                        @php
                            $etiquetaMesa = $mesa->nombre ?? $mesa->identificador ?? ('Mesa ' . ($mesa->numero ?? $mesa->id));
                        @endphp
                        <button type="button" @click="seleccionarMesa('{{ $etiquetaMesa }}')" class="py-3 bg-slate-900 hover:bg-indigo-600/20 hover:border-indigo-500/50 text-slate-300 border-slate-800 rounded-xl text-xs font-semibold border transition" x-text="'{{ $etiquetaMesa }}'"></button>
                    @endforeach
                    <button type="button" @click="seleccionarMesa('Llevar')" class="py-3 bg-slate-900 hover:bg-indigo-600/20 hover:border-indigo-500/50 text-slate-300 border-slate-800 rounded-xl text-xs font-semibold border transition">Llevar</button>
                    <button type="button" @click="seleccionarMesa('Barra')" class="py-3 bg-slate-900 hover:bg-indigo-600/20 hover:border-indigo-500/50 text-slate-300 border-slate-800 rounded-xl text-xs font-semibold border transition">Barra</button>
                </div>
            </div>
        </div>

    </div>

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 7: LÓGICA ALPINE.JS DEL COMANDERO                             --}}
    {{-- ===================================================================== --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('comanderoApp', () => ({
                sucursalId: {{ $sucursal?->id ?? 1 }},
                routes: {
                    catalogo: '{{ route('restaurante.comandero.catalogo') }}',
                    enviar: '{{ route('restaurante.comandero.enviar') }}',
                    pedirCuenta: '{{ route('restaurante.comandero.pedir-cuenta-mesa') }}',
                    csrf: '{{ csrf_token() }}'
                },
                extrasDisponibles: @json($extrasDisponibles ?? []),
                promocionesVigentes: @json($promocionesVigentes ?? []),
                productos: [],
                orden: [],
                consumoMesa: [],
                categoriaSeleccionada: 0,
                mesaActual: 'Mesa 1',
                modalMesa: false,
                modalExtras: false,
                productoSeleccionado: null,
                extrasSeleccionados: [],
                lecheSeleccionada: null,
                notaTemporal: '',
                enviando: false,
                cargando: false,
                notificacionesKds: [],
                alertasVistas: new Set(),
                audioCtx: null,

                // Estado del modal de confirmación integrado dentro de la página
                modalConfirmacion: {
                    abierto: false,
                    procesando: false,
                    icono: '🧾',
                    titulo: '',
                    mensaje: '',
                    textoConfirmar: 'Confirmar',
                    accion: null
                },

                /**
                 * Elimina modificadores o extras repetidos por ID o por nombre normalizado
                 * para evitar que aparezcan duplicados en la interfaz del comandero.
                 *
                 * @param {Array} lista
                 * @returns {Array}
                 */
                deduplicarModificadores(lista) {
                    if (!Array.isArray(lista)) return [];
                    const vistosId = new Set();
                    const vistosNombre = new Set();

                    return lista.filter(item => {
                        if (!item) return false;
                        const idKey = item.id !== undefined && item.id !== null ? String(item.id) : '';
                        const nombreKey = String(item.nombre || '').trim().toLowerCase();

                        if (idKey && vistosId.has(idKey)) return false;
                        if (nombreKey && vistosNombre.has(nombreKey)) return false;

                        if (idKey) vistosId.add(idKey);
                        if (nombreKey) vistosNombre.add(nombreKey);
                        return true;
                    });
                },

                get extrasUnicos() {
                    return this.deduplicarModificadores(this.extrasDisponibles);
                },

                get totalOrden() {
                    return this.orden.reduce((sum, item) => sum + this.calcularSubtotalItem(item), 0);
                },

                get totalConsumoMesa() {
                    return this.consumoMesa.reduce((sum, item) => {
                        const sub = item.subtotal ?? ((Number(item.precio || 0)) * Number(item.cantidad || 1));
                        return sum + Number(sub || 0);
                    }, 0);
                },

                get lechesDisponibles() {
                    const filtradas = this.extrasUnicos.filter(ext => {
                        const nombre = (ext.nombre || '').toLowerCase();
                        const sku = (ext.sku || '').toLowerCase();
                        return nombre.includes('leche') || sku.includes('lech');
                    });
                    return this.deduplicarModificadores(filtradas);
                },

                requiereLeche() {
                    if (!this.productoSeleccionado) return false;
                    const n = (this.productoSeleccionado.nombre || '').toLowerCase();
                    const e = (this.productoSeleccionado.estacion || '').toLowerCase();
                    return e.includes('bar') || n.includes('cap') || n.includes('latte') || n.includes('moka') || n.includes('té') || n.includes('te ') || n.includes('chai') || n.includes('avena') || n.includes('crema');
                },

                get extrasFiltradosPorEstacion() {
                    if (!this.productoSeleccionado) return [];
                    const est = (this.productoSeleccionado.estacion || '').toLowerCase();
                    const esBebida = est.includes('bar') || est.includes('bebida');

                    const filtrados = this.extrasUnicos.filter(ext => {
                        const nombreExtra = (ext.nombre || '').toLowerCase();
                        const skuExtra = (ext.sku || '').toLowerCase();
                        if (nombreExtra.includes('leche') || skuExtra.includes('lech')) return false;

                        const esSab = nombreExtra.includes('sabor') || nombreExtra.includes('jarabe');
                        const esEsp = nombreExtra.includes('espresso') || nombreExtra.includes('expresso');
                        const esLic = nombreExtra.includes('tequila') || nombreExtra.includes('mezcal') || nombreExtra.includes('licor');
                        const esExtraBarra = esSab || esEsp || esLic;

                        if (esBebida) {
                            return this.requiereLeche() ? (esSab || esEsp) : esLic;
                        } else {
                            return !esExtraBarra;
                        }
                    });

                    return this.deduplicarModificadores(filtrados);
                },

                async init() {
                    window.addEventListener('pointerdown', () => this.desbloquearAudio(), { once: true });
                    this.categoriaSeleccionada = 0;
                    await Promise.all([
                        this.cargarProductos(),
                        this.cargarConsumoMesa()
                    ]);
                    this.iniciarEscuchaAlertasKds();
                },

                desbloquearAudio() {
                    try {
                        if (!this.audioCtx) {
                            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                            if (AudioContextClass) {
                                this.audioCtx = new AudioContextClass();
                            }
                        }
                        if (this.audioCtx && this.audioCtx.state === 'suspended') {
                            this.audioCtx.resume();
                        }
                    } catch (e) {
                        console.log('No se pudo inicializar AudioContext:', e);
                    }
                },

                async seleccionarMesa(nombreMesa) {
                    this.mesaActual = nombreMesa;
                    this.modalMesa = false;
                    await this.cargarConsumoMesa();
                },

                async cargarConsumoMesa() {
                    try {
                        const params = new URLSearchParams({
                            mesa: this.mesaActual,
                            sucursal_id: this.sucursalId
                        });
                        const res = await fetch(`/restaurante/comandero/consumo-mesa?${params.toString()}`, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (res.ok) {
                            const data = await res.json();
                            if (Array.isArray(data)) {
                                this.consumoMesa = data;
                            } else if (Array.isArray(data.items)) {
                                this.consumoMesa = data.items;
                            } else if (Array.isArray(data.comanda?.items)) {
                                this.consumoMesa = data.comanda.items;
                            } else {
                                this.consumoMesa = [];
                            }
                        }
                    } catch (e) {
                        console.error('Error al cargar consumo de la mesa:', e);
                    }
                },

                reproducirTimbre() {
                    try {
                        this.desbloquearAudio();
                        if (!this.audioCtx || this.audioCtx.state !== 'running') return;

                        const now = this.audioCtx.currentTime;
                        const osc = this.audioCtx.createOscillator();
                        const gain = this.audioCtx.createGain();

                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(880, now);
                        gain.gain.setValueAtTime(0.18, now);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.75);

                        osc.connect(gain);
                        gain.connect(this.audioCtx.destination);

                        osc.start(now);
                        osc.stop(now + 0.8);
                    } catch (e) {
                        console.log('Error al reproducir timbre:', e);
                    }
                },

                mostrarToastSistema(titulo, mensaje, tipo = 'exito', icono = '✅') {
                    const notifId = `${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
                    const nuevaNotif = {
                        id: notifId,
                        titulo,
                        mensaje,
                        tipo,
                        icono,
                        hora: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
                        fading: false
                    };

                    this.notificacionesKds = [...this.notificacionesKds, nuevaNotif];

                    setTimeout(() => {
                        this.notificacionesKds = this.notificacionesKds.map(n =>
                            n.id === notifId ? { ...n, fading: true } : n
                        );
                    }, 4500);

                    setTimeout(() => {
                        this.notificacionesKds = this.notificacionesKds.filter(n => n.id !== notifId);
                    }, 6100);
                },

                agregarNotificacionKds(alerta) {
                    const idUnico = `${alerta.tipo || 'kds'}_${alerta.id ?? Math.random()}_${alerta.timestamp ?? Date.now()}`;
                    if (this.alertasVistas.has(idUnico)) return;
                    this.alertasVistas.add(idUnico);

                    this.reproducirTimbre();

                    const estacion = (alerta.estacion || alerta.estacion_cocina || 'cocina').toString().toUpperCase();
                    const mesa = alerta.mesa || alerta.mesa_o_referencia || 'Mesa';
                    const platillo = alerta.platillo || alerta.item_nombre || alerta.nombre || 'Orden despachada';

                    const notifId = `${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
                    const nuevaNotif = {
                        id: notifId,
                        titulo: `¡Despacho en ${estacion}!`,
                        mensaje: `${mesa} — ${platillo}`,
                        tipo: 'kds',
                        icono: '🔔',
                        hora: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
                        fading: false
                    };

                    this.notificacionesKds = [...this.notificacionesKds, nuevaNotif];

                    setTimeout(() => {
                        this.notificacionesKds = this.notificacionesKds.map(n =>
                            n.id === notifId ? { ...n, fading: true } : n
                        );
                    }, 7500);

                    setTimeout(() => {
                        this.notificacionesKds = this.notificacionesKds.filter(n => n.id !== notifId);
                    }, 9100);
                },

                iniciarEscuchaAlertasKds() {
                    setInterval(async () => {
                        try {
                            const res = await fetch(`/restaurante/comandero/alertas-listas?sucursal_id=${this.sucursalId}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            if (res.ok) {
                                const data = await res.json();
                                const alertas = Array.isArray(data) ? data : (data.alertas || []);
                                alertas.forEach(alerta => this.agregarNotificacionKds(alerta));
                            }
                        } catch (e) {
                            console.error('Error en alertas KDS', e);
                        }
                    }, 2500);
                },

                async seleccionarCategoria(catId) {
                    this.categoriaSeleccionada = catId;
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
                            const data = await res.json();
                            this.productos = Array.isArray(data) ? data : [];
                        }
                    } catch (e) {
                        console.error('Error al cargar catálogo:', e);
                    } finally {
                        this.cargando = false;
                    }
                },

                abrirModalExtras(producto) {
                    this.productoSeleccionado = JSON.parse(JSON.stringify(producto));
                    this.extrasSeleccionados = [];
                    this.lecheSeleccionada = null;
                    this.notaTemporal = '';
                    this.modalExtras = true;
                },

                toggleExtra(ext) {
                    const idx = this.extrasSeleccionados.findIndex(e => e.id === ext.id);
                    if (idx > -1) this.extrasSeleccionados.splice(idx, 1);
                    else this.extrasSeleccionados.push(JSON.parse(JSON.stringify(ext)));
                },

                confirmarAgregarItem() {
                    if (!this.productoSeleccionado) return;
                    let extrasFinales = [...this.extrasSeleccionados];
                    if (this.lecheSeleccionada) {
                        extrasFinales.unshift(JSON.parse(JSON.stringify(this.lecheSeleccionada)));
                    }

                    this.orden.push({
                        producto_id: this.productoSeleccionado.id,
                        nombre: this.productoSeleccionado.nombre,
                        precio: Number(this.productoSeleccionado.precio),
                        cantidad: 1,
                        extras: extrasFinales,
                        notas_especiales: this.notaTemporal.trim()
                    });
                    this.modalExtras = false;
                },

                agregarExtraSuelto(ext) {
                    this.orden.push({ producto_id: ext.id, nombre: `[Extra] ${ext.nombre}`, precio: Number(ext.precio_extra || ext.precio_base || 0), cantidad: 1, extras: [], notas_especiales: '' });
                },

                calcularSubtotalItem(item) {
                    const base = Number(item.precio || 0);
                    const extSuma = (item.extras || []).reduce((s, e) => s + Number(e.precio_extra || e.precio_base || 0), 0);
                    return (base + extSuma) * Number(item.cantidad || 1);
                },

                incrementar(idx) { this.orden[idx].cantidad += 1; },
                decrementar(idx) { if (this.orden[idx].cantidad > 1) this.orden[idx].cantidad -= 1; },
                eliminar(idx) { this.orden.splice(idx, 1); },

                abrirModalConfirmacion({ icono = '🧾', titulo, mensaje, textoConfirmar = 'Confirmar', accion }) {
                    this.modalConfirmacion = {
                        abierto: true,
                        procesando: false,
                        icono,
                        titulo,
                        mensaje,
                        textoConfirmar,
                        accion
                    };
                },

                cerrarModalConfirmacion() {
                    if (this.modalConfirmacion.procesando) return;
                    this.modalConfirmacion.abierto = false;
                    this.modalConfirmacion.accion = null;
                },

                async confirmarAccionModal() {
                    if (typeof this.modalConfirmacion.accion !== 'function') {
                        this.cerrarModalConfirmacion();
                        return;
                    }
                    this.modalConfirmacion.procesando = true;
                    try {
                        await this.modalConfirmacion.accion();
                        this.modalConfirmacion.abierto = false;
                        this.modalConfirmacion.accion = null;
                    } finally {
                        this.modalConfirmacion.procesando = false;
                    }
                },

                solicitarConfirmacionVaciarOrden() {
                    if (this.orden.length === 0) return;
                    this.abrirModalConfirmacion({
                        icono: '🗑️',
                        titulo: 'Vaciar orden actual',
                        mensaje: `¿Deseas quitar los platillos por enviar de ${this.mesaActual}?`,
                        textoConfirmar: 'Vaciar orden',
                        accion: () => {
                            this.orden = [];
                            this.mostrarToastSistema('Orden vaciada', `Se limpiaron los ítems pendientes de ${this.mesaActual}.`, 'aviso', '🗑️');
                        }
                    });
                },

                abrirModalPedirCuenta() {
                    this.abrirModalConfirmacion({
                        icono: '🧾',
                        titulo: 'Solicitar cuenta a caja',
                        mensaje: `¿Solicitar cuenta para ${this.mesaActual}?`,
                        textoConfirmar: 'Solicitar cuenta',
                        accion: async () => {
                            await this.ejecutarPedirCuenta();
                        }
                    });
                },

                async ejecutarPedirCuenta() {
                    try {
                        const res = await fetch(this.routes.pedirCuenta, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.routes.csrf, 'Accept': 'application/json' },
                            body: JSON.stringify({ mesa: this.mesaActual, sucursal_id: this.sucursalId })
                        });
                        if (res.ok) {
                            this.mostrarToastSistema('Cuenta solicitada', `Se envió la solicitud de cuenta de ${this.mesaActual} a caja.`, 'exito', '🧾');
                        } else {
                            this.mostrarToastSistema('Aviso', 'No se pudo enviar la solicitud de cuenta.', 'error', '⚠️');
                        }
                    } catch (e) {
                        console.error('Error al pedir cuenta', e);
                        this.mostrarToastSistema('Error de conexión', 'No hubo comunicación con el servidor.', 'error', '⚠️');
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
                        const res = await fetch(this.routes.enviar, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.routes.csrf, 'Accept': 'application/json' }, body: JSON.stringify(payload) });
                        if (res.ok) {
                            this.orden = [];
                            await this.cargarConsumoMesa();
                            this.mostrarToastSistema('Orden enviada', `Comanda enviada a cocina/barra para ${this.mesaActual}.`, 'exito', '🚀');
                        } else {
                            this.mostrarToastSistema('Error en servidor', 'No se pudo enviar la orden a cocina.', 'error', '⚠️');
                        }
                    } finally { this.enviando = false; }
                }
            }));
        });
    </script>
</body>
</html>
