<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-400 font-mono">
                    Restaurante • Mapa de Salón
                </p>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    Distribución de Mesas
                </h2>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('restaurante.reservaciones.index') }}" 
                   class="px-3 py-1.5 bg-purple-950/60 hover:bg-purple-900 text-purple-300 text-xs font-bold rounded-xl border border-purple-500/40 transition">
                    📅 Agenda de Reservaciones
                </a>
                <a href="{{ route('restaurante.kds.index') }}" 
                   class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl border border-slate-700 transition">
                    👨‍🍳 Pantalla KDS
                </a>
                <a href="{{ route('restaurante.comandero.index') }}" 
                   class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg transition">
                    📱 Comandero Móvil
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="planoMesas({
             areas: {{ Js::from($areas) }},
             puedeEditar: {{ $puedeEditarPlano ? 'true' : 'false' }},
             csrfToken: '{{ csrf_token() }}'
         })"
         x-init="initPlano()">

        {{-- Barra de Control de Ambientes y Estado de Edición --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-4 shadow-xl flex flex-wrap items-center justify-between gap-3">
            {{-- Tabs de Áreas / Ambientes --}}
            <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
                <template x-for="area in areas" :key="area.id">
                    <button type="button" 
                            @click="areaActivaId = area.id"
                            :class="areaActivaId === area.id ? 'bg-indigo-600 text-white font-bold shadow' : 'bg-slate-950 text-slate-400 border border-slate-800 hover:text-white'"
                            class="px-3.5 py-2 rounded-xl text-xs transition whitespace-nowrap"
                            x-text="area.nombre">
                    </button>
                </template>
            </div>

            {{-- Controles Administrativos de Edición --}}
            <div class="flex items-center gap-2">
                @if ($puedeEditarPlano)
                    <template x-if="!modoEdicion">
                        <button type="button" @click="activarEdicion()"
                                class="px-3.5 py-2 bg-amber-500/20 text-amber-300 border border-amber-500/30 hover:bg-amber-500/30 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                            <span>✏️</span> Reacomodar Plano (Mover Mesas)
                        </button>
                    </template>

                    <template x-if="modoEdicion">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="modalNuevaMesa = true"
                                    class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold border border-slate-700">
                                + Agregar Mesa
                            </button>
                            <button type="button" @click="guardarDistribucion()" :disabled="guardando"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center gap-1">
                                <span x-show="!guardando">💾 Guardar Nuevo Acomodo</span>
                                <span x-show="guardando">Guardando...</span>
                            </button>
                            <button type="button" @click="cancelarEdicion()"
                                    class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs">
                                Cancelar
                            </button>
                        </div>
                    </template>
                @endif
            </div>
        </div>

        {{-- Semáforo de Referencia --}}
        <div class="flex flex-wrap items-center gap-4 mb-3 px-2 text-[11px] text-slate-400">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-500"></span> Disponible</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-purple-500"></span> Reservada Hoy</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-500"></span> Ocupada (Comiendo)</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-indigo-500"></span> Cuenta Pedida</span>
            <template x-if="modoEdicion">
                <span class="text-amber-400 font-bold ml-auto animate-pulse">
                    ⚠️ Modo edición activo: Arrastra las mesas a la posición deseada.
                </span>
            </template>
        </div>

        {{-- Lienzo del Plano (Floor Canvas) --}}
        <div id="lienzo-plano"
             @dragover.prevent
             @drop.prevent="onDropLienzo($event)"
             class="relative w-full h-[620px] bg-slate-950/80 border-2 rounded-2xl overflow-hidden shadow-2xl transition"
             :class="modoEdicion ? 'border-dashed border-amber-500/50 bg-[radial-gradient(#334155_1px,transparent_1px)] [background-size:16px_16px]' : 'border-slate-800'">

            <template x-for="mesa in mesasAreaActiva" :key="mesa.id">
                <div :draggable="modoEdicion"
                     @dragstart="onDragStart($event, mesa)"
                     @click="gestionarClickMesa(mesa)"
                     :style="`left: ${mesa.posicion_x}%; top: ${mesa.posicion_y}%;`"
                     class="absolute -translate-x-1/2 -translate-y-1/2 flex flex-col items-center justify-center p-2.5 transition select-none shadow-xl border cursor-pointer group"
                     :class="{
                         'cursor-grab active:cursor-grabbing border-amber-400 ring-2 ring-amber-400/30': modoEdicion,
                         'rounded-full w-24 h-24': mesa.forma === 'redonda',
                         'rounded-2xl w-24 h-24': mesa.forma === 'cuadrada',
                         'rounded-2xl w-36 h-24': mesa.forma === 'rectangular',
                         'bg-emerald-950/40 border-emerald-500/50 text-emerald-300 hover:border-emerald-400': mesa.estado === 'disponible' && (!mesa.reservaciones || mesa.reservaciones.length === 0),
                         'bg-purple-950/40 border-purple-500/70 text-purple-300 hover:border-purple-400 ring-1 ring-purple-500/40': mesa.estado === 'disponible' && mesa.reservaciones && mesa.reservaciones.length > 0,
                         'bg-amber-950/40 border-amber-500/50 text-amber-300 hover:border-amber-400': mesa.estado === 'ocupada',
                         'bg-indigo-950/40 border-indigo-500/50 text-indigo-300 hover:border-indigo-400 animate-pulse': mesa.estado === 'cuenta_pedida'
                     }">
                    
                    <span class="text-xs font-black font-mono tracking-tight" x-text="mesa.numero_o_nombre"></span>
                    <span class="text-[10px] text-slate-400 font-mono" x-text="`${mesa.capacidad} pers`"></span>
                    
                    {{-- Distintivo de Reservación Activa Hoy --}}
                    <template x-if="mesa.estado === 'disponible' && mesa.reservaciones && mesa.reservaciones.length > 0">
                        <span class="text-[8px] font-bold px-1.5 py-0.2 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30 mt-0.5 truncate max-w-[85px]"
                              x-text="`📅 ${formatearHora(mesa.reservaciones[0].fecha_hora_reservacion)}`">
                        </span>
                    </template>

                    <template x-if="mesa.estado === 'ocupada'">
                        <span class="text-[9px] font-bold text-amber-400 mt-0.5">Ocupada</span>
                    </template>
                    <template x-if="mesa.estado === 'cuenta_pedida'">
                        <span class="text-[9px] font-bold text-indigo-400 mt-0.5">Pidiendo Cuenta</span>
                    </template>
                </div>
            </template>
        </div>

        {{-- Modal Detalle de Reservación para Hostess --}}
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
                                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-1.5">
                                <span>🪑 Sentar Comensales Ahora</span>
                            </button>
                            <button type="button" 
                                    @click="abrirComanderoDirecto(mesaSeleccionada.numero_o_nombre)"
                                    class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs transition">
                                Ir al Comandero sin Check-in
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Modal Agregar Nueva Mesa --}}
        <div x-show="modalNuevaMesa" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalNuevaMesa = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-2xl">
                <h3 class="text-sm font-bold text-white">Nueva Mesa en este Ambiente</h3>
                
                <form @submit.prevent="crearMesa()">
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs text-slate-300 block mb-1">Nombre / Número de Mesa *</label>
                            <input type="text" x-model="formMesa.numero_o_nombre" required placeholder="ej. Mesa 7 o Barra 1"
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
                        <button type="button" @click="modalNuevaMesa = false" class="px-3 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs">Crear Mesa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function planoMesas(config) {
            return {
                areas: config.areas || [],
                areaActivaId: config.areas.length > 0 ? config.areas[0].id : null,
                puedeEditar: config.puedeEditar,
                csrf: config.csrfToken,
                modoEdicion: false,
                guardando: false,
                modalNuevaMesa: false,
                modalInfoReservacion: false,
                mesaSeleccionada: null,
                arrastrandoMesaId: null,
                posicionesOriginales: [],

                formMesa: {
                    numero_o_nombre: '',
                    capacidad: 4,
                    forma: 'cuadrada'
                },

                initPlano() {},

                get mesasAreaActiva() {
                    const area = this.areas.find(a => a.id === this.areaActivaId);
                    return area ? area.mesas : [];
                },

                formatearHora(fechaIso) {
                    if (!fechaIso) return '';
                    const d = new Date(fechaIso);
                    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                },

                activarEdicion() {
                    this.modoEdicion = true;
                    this.posicionesOriginales = JSON.parse(JSON.stringify(this.mesasAreaActiva));
                },

                cancelarEdicion() {
                    if (this.posicionesOriginales.length > 0) {
                        const area = this.areas.find(a => a.id === this.areaActivaId);
                        if (area) {
                            area.mesas = JSON.parse(JSON.stringify(this.posicionesOriginales));
                        }
                    }
                    this.modoEdicion = false;
                },

                onDragStart(event, mesa) {
                    if (!this.modoEdicion) return;
                    this.arrastrandoMesaId = mesa.id;
                    event.dataTransfer.setData('text/plain', mesa.id);
                },

                onDropLienzo(event) {
                    if (!this.modoEdicion || !this.arrastrandoMesaId) return;

                    const lienzo = document.getElementById('lienzo-plano');
                    const rect = lienzo.getBoundingClientRect();

                    const clickX = event.clientX - rect.left;
                    const clickY = event.clientY - rect.top;

                    const porcX = Math.min(95, Math.max(5, (clickX / rect.width) * 100));
                    const porcY = Math.min(95, Math.max(5, (clickY / rect.height) * 100));

                    const mesa = this.mesasAreaActiva.find(m => m.id === this.arrastrandoMesaId);
                    if (mesa) {
                        mesa.posicion_x = Math.round(porcX * 100) / 100;
                        mesa.posicion_y = Math.round(porcY * 100) / 100;
                    }

                    this.arrastrandoMesaId = null;
                },

                gestionarClickMesa(mesa) {
                    if (this.modoEdicion) return;

                    // Si tiene reservación activa confirmada, abre el modal para hostess
                    if (mesa.estado === 'disponible' && mesa.reservaciones && mesa.reservaciones.length > 0) {
                        this.mesaSeleccionada = mesa;
                        this.modalInfoReservacion = true;
                        return;
                    }

                    this.abrirComanderoDirecto(mesa.numero_o_nombre);
                },

                abrirComanderoDirecto(nombreMesa) {
                    window.location.href = `{{ route('restaurante.comandero.index') }}?mesa=${encodeURIComponent(nombreMesa)}`;
                },

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
                        alert('Error de conexión.');
                    }
                },

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
                        alert('Error de conexión.');
                    }
                }
            };
        }
    </script>
</x-app-layout>