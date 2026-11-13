<x-app-layout>
    {{-- ========================================================================= --}}
    {{-- SECCIÓN 1: ENCABEZADO CON IDENTIDAD DE EMPRESA, SUCURSAL Y ACCIONES RÁPIDAS --}}
    {{-- ========================================================================= --}}
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                {{-- Badge de Empresa y Sucursal --}}
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] uppercase font-extrabold tracking-wider px-2 py-0.5 rounded bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">
                        🔥 {{ $empresa->nombre ?? 'La Fogata Bistro' }}
                    </span>
                    <span class="text-xs text-slate-400">
                        • Sucursal: <strong class="text-slate-200">{{ $sucursal->nombre ?? 'Sucursal Centro' }}</strong>
                    </span>
                </div>

                {{-- Título Principal y Contador de Mesas --}}
                <h1 class="text-xl font-black text-white tracking-tight flex items-center gap-2">
                    <span>🗺️ Distribución de Mesas</span>
                    <span class="text-xs font-normal text-slate-400">
                        ({{ $areas->first()?->mesas?->count() ?? 8 }} mesas configuradas)
                    </span>
                </h1>
            </div>

            {{-- Accesos Directos Operativos --}}
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('pos.index') }}" 
                   class="px-3 py-1.5 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 text-xs font-bold rounded-xl border border-emerald-500/30 transition flex items-center gap-1.5 shadow">
                    <span>💳</span> Caja (POS)
                </a>
                <a href="{{ route('restaurante.reservaciones.index') }}" 
                   class="px-3 py-1.5 bg-purple-950/60 hover:bg-purple-900 text-purple-300 text-xs font-bold rounded-xl border border-purple-500/40 transition flex items-center gap-1.5">
                    <span>📅</span> Agenda
                </a>
                <a href="{{ route('restaurante.kds.index') }}" 
                   class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center gap-1.5">
                    <span>👨‍🍳</span> KDS Cocina
                </a>
                <a href="{{ route('restaurante.comandero.index') }}" 
                   class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg transition flex items-center gap-1.5">
                    <span>📱</span> Comandero
                </a>
            </div>
        </div>
    </x-slot>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 2: CONTENEDOR PRINCIPAL Y ENTORNO REACTIVO ALPINE.JS             --}}
    {{-- ========================================================================= --}}
    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="planoMesas({
             areas: {{ Js::from($areas) }},
             puedeEditar: {{ $puedeEditarPlano ? 'true' : 'false' }},
             csrfToken: '{{ csrf_token() }}'
         })"
         x-init="initPlano()">

        {{-- Barra de Control: Selector de Ambientes y Acciones de Reacomodo --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-4 shadow-xl flex flex-wrap items-center justify-between gap-3">
            {{-- Pestañas de Ambientes (Salón Principal, Terraza, etc.) --}}
            <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
                <template x-for="area in areas" :key="area.id">
                    <button type="button" 
                            @click="areaActivaId = area.id"
                            :class="areaActivaId === area.id ? 'bg-indigo-600 text-white font-bold shadow' : 'bg-slate-950 text-slate-400 border border-slate-800 hover:text-white'"
                            class="px-3.5 py-2 rounded-xl text-xs transition whitespace-nowrap cursor-pointer"
                            x-text="area.nombre">
                    </button>
                </template>
            </div>

            {{-- Controles Administrativos (Drag & Drop y Creación de Mesas) --}}
            <div class="flex items-center gap-2">
                @if ($puedeEditarPlano)
                    {{-- Botón para entrar en modo edición --}}
                    <template x-if="!modoEdicion">
                        <button type="button" @click="activarEdicion()"
                                class="px-3.5 py-2 bg-amber-500/20 text-amber-300 border border-amber-500/30 hover:bg-amber-500/30 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer">
                            <span>✏️</span> Reacomodar Plano (Mover Mesas)
                        </button>
                    </template>

                    {{-- Botones activos durante la edición --}}
                    <template x-if="modoEdicion">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="modalNuevaMesa = true"
                                    class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold border border-slate-700 cursor-pointer">
                                + Agregar Mesa
                            </button>
                            <button type="button" @click="guardarDistribucion()" :disabled="guardando"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center gap-1 cursor-pointer">
                                <span x-show="!guardando">💾 Guardar Nuevo Acomodo</span>
                                <span x-show="guardando">Guardando...</span>
                            </button>
                            <button type="button" @click="cancelarEdicion()"
                                    class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs cursor-pointer">
                                Cancelar
                            </button>
                        </div>
                    </template>
                @endif
            </div>
        </div>

        {{-- Semáforo Visual de Estados e Instrucción de Edición --}}
        <div class="flex flex-wrap items-center gap-4 mb-3 px-2 text-[11px] text-slate-400">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-500"></span> Disponible</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-purple-500"></span> Reservada Hoy</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-500"></span> Ocupada (Comiendo)</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-indigo-500 animate-pulse"></span> Cuenta Pedida</span>
            
            <template x-if="modoEdicion">
                <span class="text-amber-400 font-bold ml-auto animate-pulse">
                    ⚠️ Modo edición: Arrastra para alinear en la cuadrícula o haz CLIC sobre una mesa para editarla o eliminarla.
                </span>
            </template>
        </div>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 3: LIENZO GRÁFICO 2D DEL PISO / SALÓN (FLOOR CANVAS)             --}}
        {{-- ========================================================================= --}}
        <div id="lienzo-plano"
             @pointermove="onPointerMoveLienzo($event)"
             @pointerup="onPointerUpLienzo($event)"
             @pointerleave="onPointerUpLienzo($event)"
             class="relative w-full h-[620px] bg-slate-950/80 border-2 rounded-2xl overflow-hidden shadow-2xl transition select-none touch-none"
             :class="modoEdicion ? 'border-dashed border-amber-500/50 bg-[radial-gradient(#475569_1.5px,transparent_1.5px)] [background-size:16px_16px]' : 'border-slate-800'">

            <template x-for="mesa in mesasAreaActiva" :key="mesa.id">
                <div @pointerdown="onPointerDownMesa($event, mesa)"
                     @click="gestionarClickMesa(mesa)"
                     :style="`left: ${mesa.posicion_x}%; top: ${mesa.posicion_y}%;`"
                     class="absolute -translate-x-1/2 -translate-y-1/2 flex flex-col items-center justify-center p-2.5 select-none shadow-xl border cursor-pointer group"
                     :class="{
                         'transition-none z-30 scale-105 cursor-grabbing border-amber-300 ring-4 ring-amber-400/40': arrastrandoMesaId === mesa.id,
                         'transition cursor-grab border-amber-400 ring-2 ring-amber-400/30': modoEdicion && arrastrandoMesaId !== mesa.id,
                         'transition': !modoEdicion,
                         'rounded-full w-24 h-24': mesa.forma === 'redonda',
                         'rounded-2xl w-24 h-24': mesa.forma === 'cuadrada',
                         'rounded-2xl w-36 h-24': mesa.forma === 'rectangular',
                         'bg-emerald-950/40 border-emerald-500/50 text-emerald-300 hover:border-emerald-400': mesa.estado === 'disponible' && (!mesa.reservaciones || mesa.reservaciones.length === 0),
                         'bg-purple-950/40 border-purple-500/70 text-purple-300 hover:border-purple-400 ring-1 ring-purple-500/40': mesa.estado === 'disponible' && mesa.reservaciones && mesa.reservaciones.length > 0,
                         'bg-amber-950/40 border-amber-500/50 text-amber-300 hover:border-amber-400': mesa.estado === 'ocupada',
                         'bg-indigo-950/40 border-indigo-500/50 text-indigo-300 hover:border-indigo-400 animate-pulse': mesa.estado === 'cuenta_pedida'
                     }">
                    
                    {{-- Identificador de Mesa y Capacidad --}}
                    <span class="text-xs font-black font-mono tracking-tight pointer-events-none" x-text="mesa.numero_o_nombre"></span>
                    <span class="text-[10px] text-slate-400 font-mono pointer-events-none" x-text="`${mesa.capacidad} pers`"></span>
                    
                    {{-- Distintivo de Reservación Activa Hoy --}}
                    <template x-if="mesa.estado === 'disponible' && mesa.reservaciones && mesa.reservaciones.length > 0">
                        <span class="text-[8px] font-bold px-1.5 py-0.2 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30 mt-0.5 truncate max-w-[85px] pointer-events-none"
                              x-text="`📅 ${formatearHora(mesa.reservaciones[0].fecha_hora_reservacion)}`">
                        </span>
                    </template>

                    {{-- Badges de Estado Operativo --}}
                    <template x-if="mesa.estado === 'ocupada'">
                        <span class="text-[9px] font-bold text-amber-400 mt-0.5 pointer-events-none">Ocupada</span>
                    </template>
                    <template x-if="mesa.estado === 'cuenta_pedida'">
                        <span class="text-[9px] font-bold text-indigo-400 mt-0.5 pointer-events-none">Pidiendo Cuenta</span>
                    </template>
                </div>
            </template>
        </div>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 4: MODAL DE DETALLE DE RESERVACIÓN (HOSTESS CHECK-IN)            --}}
        {{-- ========================================================================= --}}
        <div x-show="modalInfoReservacion" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalInfoReservacion = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                        <span>📅 Reservación en</span> <span class="text-purple-400 font-mono" x-text="mesaSeleccionada?.numero_o_nombre"></span>
                    </h3>
                    <button type="button" @click="modalInfoReservacion = false" class="text-slate-400 hover:text-white text-base">&times;</button>
                </div>

                <template x-if="mesaSeleccionada?.reservaciones && mesaSeleccionada.reservaciones.length > 0">
                    <div class="space-y-3 text-xs">
                        <div class="p-3 bg-purple-950/30 border border-purple-500/30 rounded-xl space-y-1.5 font-mono">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Cliente:</span>
                                <strong class="text-white" x-text="mesaSeleccionada.reservaciones[0].cliente_nombre"></strong>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Hora:</span>
                                <span class="text-purple-300 font-bold" x-text="formatearHora(mesaSeleccionada.reservaciones[0].fecha_hora_reservacion)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Comensales:</span>
                                <span class="text-white" x-text="`👥 ${mesaSeleccionada.reservaciones[0].numero_personas}`"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Teléfono:</span>
                                <span class="text-slate-300" x-text="mesaSeleccionada.reservaciones[0].cliente_telefono || 'Sin teléfono'"></span>
                            </div>
                            <template x-if="mesaSeleccionada.reservaciones[0].notas_especiales">
                                <div class="text-[10px] text-amber-300 pt-1 border-t border-purple-500/20 italic" x-text="`⚠️ ${mesaSeleccionada.reservaciones[0].notas_especiales}`"></div>
                            </template>
                        </div>

                        <div class="space-y-2 pt-2">
                            <button type="button" 
                                    @click="sentarComensalesReservacion(mesaSeleccionada.reservaciones[0].id, mesaSeleccionada.id)"
                                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-1.5 cursor-pointer">
                                <span>🪑 Sentar Comensales Ahora</span>
                            </button>
                            <button type="button" 
                                    @click="abrirComanderoDirecto(mesaSeleccionada.numero_o_nombre)"
                                    class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs transition cursor-pointer">
                                Ir al Comandero sin Check-in
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 5: MODAL EDITAR O ELIMINAR MESA EXISTENTE                         --}}
        {{-- ========================================================================= --}}
        <div x-show="modalEditarMesa" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalEditarMesa = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white">Editar Mesa</h3>
                    <button type="button" @click="modalEditarMesa = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form @submit.prevent="actualizarMesa()">
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs text-slate-300 block mb-1">Nombre / Número de Mesa *</label>
                            <input type="text" x-model="formEditarMesa.numero_o_nombre" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-xs text-slate-300 block mb-1">Capacidad (Comensales) *</label>
                            <input type="number" min="1" max="50" x-model.number="formEditarMesa.capacidad" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-xs text-slate-300 block mb-1">Forma Geométrica</label>
                            <select x-model="formEditarMesa.forma" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                <option value="cuadrada">Cuadrada</option>
                                <option value="redonda">Redonda</option>
                                <option value="rectangular">Rectangular (Larga)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-5 mt-4 border-t border-slate-800">
                        <button type="button" @click="eliminarMesa()"
                                class="px-3 py-2 bg-rose-600/20 hover:bg-rose-600/30 text-rose-400 border border-rose-500/30 rounded-xl text-xs font-bold cursor-pointer">
                            🗑️ Eliminar
                        </button>
                        <div class="flex gap-2">
                            <button type="button" @click="modalEditarMesa = false" class="px-3 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs cursor-pointer">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs cursor-pointer">Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 6: MODAL AGREGAR NUEVA MESA FÍSICA AL AMBIENTE                   --}}
        {{-- ========================================================================= --}}
        <div x-show="modalNuevaMesa" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalNuevaMesa = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-2xl">
                <h3 class="text-sm font-bold text-white">Nueva Mesa en este Ambiente</h3>
                
                <form @submit.prevent="crearMesa()">
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs text-slate-300 block mb-1">Nombre / Número de Mesa *</label>
                            <input type="text" x-model="formMesa.numero_o_nombre" required placeholder="ej. Mesa 9 o Barra 1"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-xs text-slate-300 block mb-1">Capacidad (Comensales) *</label>
                            <input type="number" min="1" max="50" x-model.number="formMesa.capacidad" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="text-xs text-slate-300 block mb-1">Forma Geométrica</label>
                            <select x-model="formMesa.forma" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                                <option value="cuadrada">Cuadrada</option>
                                <option value="redonda">Redonda</option>
                                <option value="rectangular">Rectangular (Larga)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <button type="button" @click="modalNuevaMesa = false" class="px-3 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs cursor-pointer">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs cursor-pointer">Crear Mesa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 7: LÓGICA REACTIVA JAVASCRIPT CON ALPINE.JS                       --}}
    {{-- ========================================================================= --}}
    <script>
        function planoMesas(config) {
            return {
                areas: config.areas || [],
                areaActivaId: (config.areas && config.areas.length > 0) ? config.areas[0].id : null,
                puedeEditar: config.puedeEditar,
                csrf: config.csrfToken,
                modoEdicion: false,
                guardando: false,
                modalNuevaMesa: false,
                modalEditarMesa: false,
                modalInfoReservacion: false,
                mesaSeleccionada: null,

                // Variables de control de arrastre preciso con ajuste magnético a cuadrícula
                arrastrandoMesaId: null,
                offsetArrastreX: 0,
                offsetArrastreY: 0,
                huboMovimientoArrastre: false,
                posicionesOriginales: [],

                formMesa: {
                    numero_o_nombre: '',
                    capacidad: 4,
                    forma: 'cuadrada'
                },

                formEditarMesa: {
                    id: null,
                    numero_o_nombre: '',
                    capacidad: 4,
                    forma: 'cuadrada'
                },

                initPlano() {
                    setInterval(async () => {
                        if (this.modoEdicion || this.guardando) return;
                        try {
                            const res = await fetch('{{ route('restaurante.comandero.estado-mesas') }}', {
                                headers: { 'Accept': 'application/json' }
                            });
                            if (res.ok) {
                                const nuevasAreas = await res.json();
                                // Actualizar estados sin perder las reservaciones cargadas
                                nuevasAreas.forEach(nuevaArea => {
                                    const areaLocal = this.areas.find(a => a.id === nuevaArea.id);
                                    if (areaLocal) {
                                        nuevaArea.mesas.forEach(nuevaMesa => {
                                            const mesaLocal = areaLocal.mesas.find(m => m.id === nuevaMesa.id);
                                            if (mesaLocal) {
                                                mesaLocal.estado = nuevaMesa.estado;
                                                mesaLocal.comanda_actual_id = nuevaMesa.comanda_actual_id;
                                            }
                                        });
                                    }
                                });
                            }
                        } catch (e) {}
                    }, 4000);
                },

                // Mesas activas pertenecientes al tab seleccionado actualmente
                get mesasAreaActiva() {
                    const area = this.areas.find(a => a.id === this.areaActivaId);
                    return area ? (area.mesas || []) : [];
                },

                formatearHora(fechaIso) {
                    if (!fechaIso) return '';
                    const d = new Date(fechaIso);
                    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                },

                // Activar modo edición y respaldar coordenadas originales
                activarEdicion() {
                    this.modoEdicion = true;
                    this.posicionesOriginales = JSON.parse(JSON.stringify(this.mesasAreaActiva));
                },

                // Cancelar cambios restaurando la copia de seguridad
                cancelarEdicion() {
                    if (this.posicionesOriginales.length > 0) {
                        const area = this.areas.find(a => a.id === this.areaActivaId);
                        if (area) {
                            area.mesas = JSON.parse(JSON.stringify(this.posicionesOriginales));
                        }
                    }
                    this.modoEdicion = false;
                },

                // Inicia el agarre guardando la distancia exacta desde el cursor hasta el centro de la mesa
                onPointerDownMesa(event, mesa) {
                    if (!this.modoEdicion) return;
                    if (event.button !== 0 && event.pointerType === 'mouse') return;

                    const lienzo = document.getElementById('lienzo-plano');
                    const rect = lienzo.getBoundingClientRect();

                    const centroActualX = (Number(mesa.posicion_x) / 100) * rect.width;
                    const centroActualY = (Number(mesa.posicion_y) / 100) * rect.height;

                    const punteroX = event.clientX - rect.left;
                    const punteroY = event.clientY - rect.top;

                    this.offsetArrastreX = punteroX - centroActualX;
                    this.offsetArrastreY = punteroY - centroActualY;
                    this.arrastrandoMesaId = mesa.id;
                    this.huboMovimientoArrastre = false;
                },

                // Mueve la mesa en tiempo real alineándola a los puntos guía de 16px
                onPointerMoveLienzo(event) {
                    if (!this.modoEdicion || !this.arrastrandoMesaId) return;

                    const lienzo = document.getElementById('lienzo-plano');
                    const rect = lienzo.getBoundingClientRect();

                    const punteroX = event.clientX - rect.left;
                    const punteroY = event.clientY - rect.top;

                    // Restamos el offset inicial para evitar saltos respecto al punto de agarre
                    let nuevoCentroX = punteroX - this.offsetArrastreX;
                    let nuevoCentroY = punteroY - this.offsetArrastreY;

                    // Ajuste magnético (Snap to Grid) a los puntos guía cada 16px
                    const pasoGrid = 16;
                    nuevoCentroX = Math.round(nuevoCentroX / pasoGrid) * pasoGrid;
                    nuevoCentroY = Math.round(nuevoCentroY / pasoGrid) * pasoGrid;

                    // Convertir a porcentaje dentro de márgenes seguros (5% a 95%)
                    const porcX = Math.min(95, Math.max(5, (nuevoCentroX / rect.width) * 100));
                    const porcY = Math.min(95, Math.max(5, (nuevoCentroY / rect.height) * 100));

                    const mesa = this.mesasAreaActiva.find(m => m.id === this.arrastrandoMesaId);
                    if (mesa) {
                        const nuevaX = Math.round(porcX * 100) / 100;
                        const nuevaY = Math.round(porcY * 100) / 100;

                        if (mesa.posicion_x !== nuevaX || mesa.posicion_y !== nuevaY) {
                            this.huboMovimientoArrastre = true;
                            mesa.posicion_x = nuevaX;
                            mesa.posicion_y = nuevaY;
                        }
                    }
                },

                // Libera la mesa exactamente en el punto donde se soltó
                onPointerUpLienzo(event) {
                    if (!this.arrastrandoMesaId) return;
                    this.arrastrandoMesaId = null;
                },

                // Manejo de clic sobre una mesa (distingue entre arrastrar y hacer clic para editar)
                gestionarClickMesa(mesa) {
                    if (this.modoEdicion) {
                        if (this.huboMovimientoArrastre) {
                            this.huboMovimientoArrastre = false;
                            return;
                        }

                        this.formEditarMesa = {
                            id: mesa.id,
                            numero_o_nombre: mesa.numero_o_nombre,
                            capacidad: mesa.capacidad,
                            forma: mesa.forma
                        };
                        this.modalEditarMesa = true;
                        return;
                    }

                    // Si tiene reservación activa confirmada, abre el modal para hostess
                    if (mesa.estado === 'disponible' && mesa.reservaciones && mesa.reservaciones.length > 0) {
                        this.mesaSeleccionada = mesa;
                        this.modalInfoReservacion = true;
                        return;
                    }

                    // Navega directamente al comandero con la mesa seleccionada
                    this.abrirComanderoDirecto(mesa.numero_o_nombre);
                },

                abrirComanderoDirecto(nombreMesa) {
                    window.location.href = `{{ route('restaurante.comandero.index') }}?mesa=${encodeURIComponent(nombreMesa)}`;
                },

                // Check-in de reservación: sienta a los clientes y pasa la mesa al comandero
                async sentarComensalesReservacion(reservacionId, mesaId) {
                    try {
                        const res = await fetch(`/restaurante/reservaciones/${reservacionId}/sentar`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ restaurante_mesa_id: mesaId })
                        });
                        const data = await res.json();
                        if (res.ok) {
                            alert(data.message);
                            this.modalInfoReservacion = false;
                            this.abrirComanderoDirecto(this.mesaSeleccionada.numero_o_nombre);
                        } else {
                            alert(data.message || 'Error al sentar comensales.');
                        }
                    } catch (e) {
                        alert('Error de conexión al sentar reservación.');
                    }
                },

                // Guardar la nueva distribución de coordenadas en base de datos
                async guardarDistribucion() {
                    this.guardando = true;
                    const payload = {
                        posiciones: this.mesasAreaActiva.map(m => ({
                            id: m.id,
                            posicion_x: m.posicion_x,
                            posicion_y: m.posicion_y
                        }))
                    };

                    try {
                        const res = await fetch('{{ route('restaurante.mesas.guardar-posiciones') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await res.json();
                        if (res.ok) {
                            alert(data.message);
                            this.modoEdicion = false;
                        } else {
                            alert(data.message || 'Error al guardar la distribución.');
                        }
                    } catch (e) {
                        alert('Error de conexión al guardar.');
                    } finally {
                        this.guardando = false;
                    }
                },

                // Crear nueva mesa física en el área actual
                async crearMesa() {
                    try {
                        const res = await fetch('{{ route('restaurante.mesas.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                restaurante_area_id: this.areaActivaId,
                                numero_o_nombre: this.formMesa.numero_o_nombre,
                                capacidad: this.formMesa.capacidad,
                                forma: this.formMesa.forma
                            })
                        });

                        const data = await res.json();
                        if (res.ok) {
                            const area = this.areas.find(a => a.id === this.areaActivaId);
                            if (area) {
                                data.mesa.reservaciones = [];
                                area.mesas.push(data.mesa);
                            }
                            this.modalNuevaMesa = false;
                            this.formMesa.numero_o_nombre = '';
                        } else {
                            alert(data.message || 'Error al crear la mesa.');
                        }
                    } catch (e) {
                        alert('Error de conexión al crear mesa.');
                    }
                },

                // Actualizar nombre, capacidad o forma geométrica de una mesa
                async actualizarMesa() {
                    try {
                        const res = await fetch(`/restaurante/mesas/${this.formEditarMesa.id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.formEditarMesa)
                        });

                        const data = await res.json();
                        if (res.ok) {
                            const area = this.areas.find(a => a.id === this.areaActivaId);
                            if (area) {
                                const idx = area.mesas.findIndex(m => m.id === this.formEditarMesa.id);
                                if (idx !== -1) {
                                    area.mesas[idx] = { ...area.mesas[idx], ...data.mesa };
                                }
                            }
                            this.modalEditarMesa = false;
                        } else {
                            alert(data.message || 'Error al actualizar la mesa.');
                        }
                    } catch (e) {
                        alert('Error de conexión al actualizar mesa.');
                    }
                },

                // Eliminar mesa del plano (si está disponible y sin comanda abierta)
                async eliminarMesa() {
                    if (!confirm(`¿Seguro que deseas eliminar "${this.formEditarMesa.numero_o_nombre}" del plano?`)) return;

                    try {
                        const res = await fetch(`/restaurante/mesas/${this.formEditarMesa.id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();
                        if (res.ok) {
                            const area = this.areas.find(a => a.id === this.areaActivaId);
                            if (area) {
                                area.mesas = area.mesas.filter(m => m.id !== this.formEditarMesa.id);
                            }
                            this.modalEditarMesa = false;
                        } else {
                            alert(data.message || 'No se pudo eliminar la mesa.');
                        }
                    } catch (e) {
                        alert('Error de conexión al eliminar mesa.');
                    }
                }
            };
        }
    </script>
</x-app-layout>