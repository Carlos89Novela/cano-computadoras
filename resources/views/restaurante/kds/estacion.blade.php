<x-app-layout>
    @php
        $metaEstaciones = [
            'cocina'   => ['titulo' => 'KDS Cocina',   'icono' => '🍳'],
            'parrilla' => ['titulo' => 'KDS Parrilla', 'icono' => '🔥'],
            'barra'    => ['titulo' => 'KDS Barra',    'icono' => '🍹'],
            'postres'  => ['titulo' => 'KDS Postres',  'icono' => '🍰'],
            'todas'    => ['titulo' => 'KDS Maestro',  'icono' => '👨‍🍳'],
        ];
        $meta = $metaEstaciones[$estacionFija ?? 'cocina'] ?? $metaEstaciones['cocina'];
    @endphp

    @section('title', $meta['titulo'] . ' | ' . ($empresa->nombre ?? 'La Fogata Bistro'))
    <x-slot name="title">
        {{ $meta['titulo'] }} | {{ $empresa->nombre ?? 'La Fogata Bistro' }}
    </x-slot>

    <div x-data="kdsMonitor({
            sucursalId: {{ $sucursal?->id ?? 1 }},
            estacionInicial: '{{ $estacionFija ?? 'cocina' }}',
            routes: {
                activas: '{{ route('restaurante.kds.comandas-activas') }}',
                csrf: '{{ csrf_token() }}'
            }
         })"
         x-init="iniciarMonitor()"
         class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6 flex flex-col">

        {{-- Encabezado de estación individual --}}
        <header class="flex flex-wrap items-center justify-between pb-4 mb-6 border-b border-slate-800/80 gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-amber-500/10 text-amber-400 rounded-xl border border-amber-500/20 text-xl flex items-center justify-center">
                    {{ $meta['icono'] }}
                </div>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-base font-bold text-white tracking-tight">{{ $meta['titulo'] }}</h1>
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

            <div class="flex items-center gap-2 flex-wrap">
                <button type="button"
                        @click="probarTimbreKds()"
                        title="Activar y probar timbre de estación"
                        class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-amber-400 border border-slate-800 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer">
                    <span>🔔</span>
                    <span x-text="audioActivo ? 'Timbre OK' : 'Activar Audio'"></span>
                </button>

                <a href="{{ route('restaurante.kds.index') }}"
                   class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white rounded-xl text-xs border border-slate-800 transition">
                    Ver Todas las Estaciones
                </a>

                <a href="{{ route('dashboard') }}"
                   class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white rounded-xl text-xs border border-slate-800 transition">
                    Salir
                </a>
            </div>
        </header>

        {{-- Tablero de comandas de la estación --}}
        <main class="flex-1 overflow-x-auto overflow-y-hidden pb-4">
            <div class="flex gap-4 h-full items-start">
                <template x-if="comandas.length === 0">
                    <div class="w-full flex flex-col items-center justify-center h-96 text-slate-500 text-xs">
                        <div class="text-3xl mb-2">{{ $meta['icono'] }}</div>
                        <span>No hay comandas pendientes para {{ $meta['titulo'] }}.</span>
                    </div>
                </template>

                <template x-for="c in comandas" :key="c.id">
                    <div class="w-80 shrink-0 bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-2xl flex flex-col justify-between space-y-4">
                        <div>
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

                            <div class="text-[11px] font-mono text-slate-500 mt-2 mb-3" x-text="c.folio"></div>

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

                        <div>
                            <button type="button" @click="despacharComanda(c.id)"
                                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-emerald-950/40 flex items-center justify-center gap-2 cursor-pointer">
                                <span>✓</span>
                                <span>Despachar {{ strtoupper($estacionFija ?? 'COCINA') }}</span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>

    <script>
        function kdsMonitor(config) {
            return {
                sucursalId: config.sucursalId,
                routes: config.routes,
                comandas: [],
                estacionSeleccionada: config.estacionInicial || 'cocina',
                horaActual: '',
                kdsAudioCtx: null,
                audioActivo: false,
                comandasIdsConocidas: [],
                primeraCargaKds: true,

                iniciarMonitor() {
                    document.title = '{{ $meta['titulo'] }} | {{ $empresa->nombre ?? 'La Fogata Bistro' }}';
                    window.addEventListener('pointerdown', () => this.desbloquearAudioKds(), { once: true });
                    this.actualizarHora();
                    setInterval(() => this.actualizarHora(), 1000);
                    this.cargarComandas();
                    setInterval(() => this.cargarComandas(), 4000);
                },

                actualizarHora() {
                    const ahora = new Date();
                    this.horaActual = ahora.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
                },

                async desbloquearAudioKds() {
                    try {
                        if (!this.kdsAudioCtx) {
                            const AudioCtx = window.AudioContext || window.webkitAudioContext;
                            if (AudioCtx) this.kdsAudioCtx = new AudioCtx();
                        }
                        if (this.kdsAudioCtx && this.kdsAudioCtx.state === 'suspended') {
                            await this.kdsAudioCtx.resume();
                        }
                        if (this.kdsAudioCtx && this.kdsAudioCtx.state === 'running') {
                            this.audioActivo = true;
                        }
                    } catch (e) {}
                },

                probarTimbreKds() {
                    this.desbloquearAudioKds();
                    this.reproducirTimbreKds();
                },

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
                    } catch (e) {}
                },

                evaluarNuevasComandasKds(listaComandas) {
                    const idsActuales = (Array.isArray(listaComandas) ? listaComandas : []).map(c => c.id);
                    if (this.primeraCargaKds) {
                        this.primeraCargaKds = false;
                        this.comandasIdsConocidas = idsActuales;
                        return;
                    }
                    const hayNuevaComanda = idsActuales.some(id => !this.comandasIdsConocidas.includes(id));
                    this.comandasIdsConocidas = idsActuales;
                    if (hayNuevaComanda) this.reproducirTimbreKds();
                },

                async cargarComandas() {
                    try {
                        const url = `${this.routes.activas}?sucursal_id=${this.sucursalId}&estacion=${this.estacionSeleccionada}`;
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (res.ok) {
                            const data = await res.json();
                            this.comandas = data;
                            this.evaluarNuevasComandasKds(data);
                        }
                    } catch (e) {}
                },

                minutosTranscurridos(fechaStr) {
                    if (!fechaStr) return 0;
                    const diffMs = new Date() - new Date(fechaStr);
                    return Math.max(0, Math.floor(diffMs / 60000));
                },

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
                        if (res.ok) await this.cargarComandas();
                    } catch (e) {}
                }
            };
        }
    </script>
</x-app-layout>
