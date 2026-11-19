<x-app-layout>
    {{-- ===================================================================== --}}
    {{-- TÍTULO DE LA PESTAÑA DEL NAVEGADOR                                    --}}
    {{-- ===================================================================== --}}
    @section('title', 'Monitor KDS | ' . ($empresa->nombre ?? 'La Fogata Bistro'))
    <x-slot name="title">
        Monitor KDS | {{ $empresa->nombre ?? 'La Fogata Bistro' }}
    </x-slot>

    {{-- ===================================================================== --}}
    {{-- CONTENEDOR RAÍZ DEL MONITOR KDS                                       --}}
    {{-- ===================================================================== --}}
    <div x-data="kdsMonitor({
            sucursalId: {{ $sucursal?->id ?? 1 }},
            routes: {
                activas: '{{ route('restaurante.kds.comandas-activas') }}',
                csrf: '{{ csrf_token() }}'
            }
         })"
         x-init="iniciarMonitor()"
         class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6 flex flex-col">

        {{-- ================================================================= --}}
        {{-- SECCIÓN 1: BARRA SUPERIOR, RELOJ Y FILTROS POR ESTACIÓN           --}}
        {{-- ================================================================= --}}
        <header class="flex flex-wrap items-center justify-between pb-4 mb-6 border-b border-slate-800/80 gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-amber-500/10 text-amber-400 rounded-xl border border-amber-500/20 text-xl flex items-center justify-center">
                    👨‍🍳
                </div>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-base font-bold text-white tracking-tight">Monitor KDS</h1>
                        <div class="px-2.5 py-1 bg-slate-900 border border-slate-800 rounded-lg text-xs font-mono text-slate-300 shadow-inner"
                             x-text="horaActual"></div>
                        <span class="px-2 py-0.5 rounded-full bg-slate-900 border border-slate-800 text-[11px] font-mono text-slate-400"
                              x-text="comandas.length + ' activas'"></span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ $empresa->nombre ?? 'La Fogata Bistro' }} | {{ $sucursal?->nombre ?? 'Sucursal Centro' }}
                    </p>
                </div>
            </div>

            {{-- Botonera de Estaciones y Control de Sonido --}}
            <div class="flex items-center gap-2 flex-wrap">
                <template x-for="est in ['todas', 'cocina', 'parrilla', 'barra', 'postres']" :key="est">
                    <button type="button"
                            @click="cambiarEstacion(est)"
                            class="px-3.5 py-2 rounded-xl text-xs font-semibold transition capitalize flex items-center gap-1.5 cursor-pointer border"
                            :class="estacionSeleccionada === est
                                ? 'bg-indigo-600 text-white border-indigo-500 shadow-lg shadow-indigo-600/30'
                                : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'">
                        <span x-show="est === 'cocina'">🍳</span>
                        <span x-show="est === 'parrilla'">🔥</span>
                        <span x-show="est === 'barra'">🍹</span>
                        <span x-show="est === 'postres'">🍰</span>
                        <span x-text="est === 'todas' ? 'Todas' : est.charAt(0).toUpperCase() + est.slice(1)"></span>
                    </button>
                </template>

                {{-- Botón para desbloquear y probar timbre en pantalla --}}
                <button type="button"
                        @click="probarTimbreKds()"
                        title="Activar y probar timbre de cocina"
                        class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-amber-400 border border-slate-800 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer">
                    <span>🔔</span>
                    <span x-text="audioActivo ? 'Timbre OK' : 'Activar Audio'"></span>
                </button>

                <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white rounded-xl text-xs border border-slate-800 transition">
                    Salir
                </a>
            </div>
        </header>

        {{-- ================================================================= --}}
        {{-- SECCIÓN 2: TABLERO HORIZONTAL DE COMANDAS ACTIVAS                 --}}
        {{-- ================================================================= --}}
        <main class="flex-1 overflow-x-auto overflow-y-hidden pb-4">
            <div class="flex gap-4 h-full items-start">

                {{-- Estado Vacío --}}
                <template x-if="comandas.length === 0">
                    <div class="w-full flex flex-col items-center justify-center h-96 text-slate-500 text-xs">
                        <div class="text-3xl mb-2">🍽️</div>
                        <span>No hay comandas pendientes para esta estación.</span>
                    </div>
                </template>

                {{-- Tarjetas de Comanda --}}
                <template x-for="c in comandas" :key="c.id">
                    <div class="w-80 shrink-0 bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-2xl flex flex-col justify-between space-y-4">
                        <div>
                            {{-- Cabecera de la Tarjeta (Mesa y Temporizador) --}}
                            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                                <div class="flex items-center gap-2">
                                    <span class="text-amber-400 text-base">🪑</span>
                                    <h3 class="text-sm font-black text-white" x-text="c.mesa_o_referencia"></h3>
                                </div>
                                <div class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-lg text-xs font-mono font-bold flex items-center gap-1">
                                    <span>⏱️</span>
                                    <span x-text="minutosTranscurridos(c.enviada_a_cocina_at) + ' min'"></span>
                                </div>
                            </div>

                            {{-- Folio de la Comanda --}}
                            <div class="text-[11px] font-mono text-slate-500 mt-2 mb-3" x-text="c.folio"></div>

                            {{-- Listado de Platillos / Partidas --}}
                            <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                                <template x-for="item in c.items" :key="item.id">
                                    <div class="p-3 bg-slate-950 border border-slate-800/80 rounded-xl flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-bold text-white flex items-start gap-2">
                                                <span class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-mono text-[11px] shrink-0" x-text="item.cantidad + 'x'"></span>
                                                <span class="leading-snug" x-text="item.producto?.nombre"></span>
                                            </div>
                                            <template x-if="item.notas_especiales">
                                                <p class="text-[11px] text-amber-400 mt-1 italic" x-text="item.notas_especiales"></p>
                                            </template>
                                        </div>
                                        <span class="px-2 py-0.5 bg-slate-900 text-slate-400 border border-slate-800 rounded text-[10px] font-bold uppercase tracking-wider shrink-0"
                                              x-text="item.estado"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- ================================================= --}}
                        {{-- SECCIÓN 3: ACCIÓN DE DESPACHO POR ESTACIÓN        --}}
                        {{-- ================================================= --}}
                        <div>
                            <button type="button" @click="despacharComanda(c.id)"
                                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-emerald-950/40 flex items-center justify-center gap-2 cursor-pointer">
                                <span>✓</span>
                                <span x-text="estacionSeleccionada === 'todas' ? 'Despachar Comanda' : 'Despachar ' + estacionSeleccionada.toUpperCase()"></span>
                            </button>
                        </div>
                    </div>
                </template>

            </div>
        </main>
    </div>

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 4: LÓGICA ALPINE.JS, POLLING Y TIMBRE WEB AUDIO API           --}}
    {{-- ===================================================================== --}}
    <script>
        function kdsMonitor(config) {
            return {
                // Estado de configuración y datos
                sucursalId: config.sucursalId,
                routes: config.routes,
                comandas: [],
                estacionSeleccionada: 'todas',
                horaActual: '',

                // Estado del sistema de audio y control de nuevas órdenes
                kdsAudioCtx: null,
                audioActivo: false,
                comandasIdsConocidas: [],
                primeraCargaKds: true,

                // Inicialización del título, reloj, audio y sondeo periódico
                iniciarMonitor() {
                    document.title = 'Monitor KDS | {{ $empresa->nombre ?? 'La Fogata Bistro' }}';
                    window.addEventListener('pointerdown', () => this.desbloquearAudioKds(), { once: true });
                    this.actualizarHora();
                    setInterval(() => this.actualizarHora(), 1000);
                    this.cargarComandas();
                    setInterval(() => this.cargarComandas(), 4000);
                },

                // Reloj digital en tiempo real
                actualizarHora() {
                    const ahora = new Date();
                    this.horaActual = ahora.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
                },

                // Cambio de filtro por estación
                cambiarEstacion(estacion) {
                    this.estacionSeleccionada = estacion;
                    this.primeraCargaKds = true;
                    this.cargarComandas();
                },

                // Inicializa y reanuda AudioContext tras interacción del usuario
                desbloquearAudioKds() {
                    try {
                        if (!this.kdsAudioCtx) {
                            const AudioCtx = window.AudioContext || window.webkitAudioContext;
                            if (AudioCtx) {
                                this.kdsAudioCtx = new AudioCtx();
                            }
                        }
                        if (this.kdsAudioCtx && this.kdsAudioCtx.state === 'suspended') {
                            this.kdsAudioCtx.resume();
                        }
                        if (this.kdsAudioCtx && this.kdsAudioCtx.state === 'running') {
                            this.audioActivo = true;
                        }
                    } catch (e) {
                        console.log('No se pudo inicializar el audio del KDS:', e);
                    }
                },

                // Prueba manual del timbre desde el botón del encabezado
                probarTimbreKds() {
                    this.desbloquearAudioKds();
                    this.reproducirTimbreKds();
                },

                // Genera un timbre doble limpio mediante Web Audio API
                reproducirTimbreKds() {
                    try {
                        this.desbloquearAudioKds();
                        if (!this.kdsAudioCtx || this.kdsAudioCtx.state !== 'running') return;

                        const now = this.kdsAudioCtx.currentTime;
                        const osc = this.kdsAudioCtx.createOscillator();
                        const gain = this.kdsAudioCtx.createGain();

                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(740, now);
                        osc.frequency.setValueAtTime(987.77, now + 0.14);

                        gain.gain.setValueAtTime(0.22, now);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.65);

                        osc.connect(gain);
                        gain.connect(this.kdsAudioCtx.destination);

                        osc.start(now);
                        osc.stop(now + 0.7);
                    } catch (e) {
                        console.log('Error al reproducir timbre en KDS:', e);
                    }
                },

                // Detecta si entraron comandas nuevas para disparar el timbre
                evaluarNuevasComandasKds(listaComandas) {
                    const idsActuales = (Array.isArray(listaComandas) ? listaComandas : []).map(c => c.id);

                    if (this.primeraCargaKds) {
                        this.primeraCargaKds = false;
                        this.comandasIdsConocidas = idsActuales;
                        return;
                    }

                    const hayNuevaComanda = idsActuales.some(id => !this.comandasIdsConocidas.includes(id));
                    this.comandasIdsConocidas = idsActuales;

                    if (hayNuevaComanda) {
                        this.reproducirTimbreKds();
                    }
                },

                // Consulta de comandas activas filtradas por sucursal y estación
                async cargarComandas() {
                    try {
                        const url = `${this.routes.activas}?sucursal_id=${this.sucursalId}&estacion=${this.estacionSeleccionada}`;
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (res.ok) {
                            const data = await res.json();
                            this.comandas = data;
                            this.evaluarNuevasComandasKds(data);
                        }
                    } catch (e) {
                        console.error('Error cargando comandas KDS:', e);
                    }
                },

                // Cálculo de minutos transcurridos desde el envío a cocina
                minutosTranscurridos(fechaStr) {
                    if (!fechaStr) return 0;
                    const diffMs = new Date() - new Date(fechaStr);
                    return Math.max(0, Math.floor(diffMs / 60000));
                },

                // Despacho de comanda completa o parcial según la estación seleccionada
                async despacharComanda(comandaId) {
                    try {
                        const res = await fetch(`/restaurante/kds/comandas/${comandaId}/completar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.routes.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                estacion: this.estacionSeleccionada === 'todas' ? '' : this.estacionSeleccionada
                            })
                        });
                        if (res.ok) {
                            await this.cargarComandas();
                        }
                    } catch (e) {
                        console.error('Error al despachar comanda:', e);
                    }
                }
            }
        }
    </script>
</x-app-layout>
