<x-app-layout>
    <div x-data="kdsMonitor({
            sucursalId: {{ $sucursal?->id ?? 1 }},
            routes: {
                activas: '{{ route('restaurante.kds.comandas-activas') }}',
                csrf: '{{ csrf_token() }}'
            }
         })"
         x-init="iniciarMonitor()"
         class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6 flex flex-col">

        {{-- Barra Superior / Header Exacto --}}
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
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ $empresa->nombre ?? 'La Fogata Bistro' }} | {{ $sucursal?->nombre ?? 'Sucursal Centro' }}
                    </p>
                </div>
            </div>

            {{-- Filtros por Estación Exactos --}}
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
                        <span x-text="est.charAt(0).toUpperCase() + est.slice(1)"></span>
                    </button>
                </template>

                <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white rounded-xl text-xs border border-slate-800 transition">
                    Salir
                </a>
            </div>
        </header>

        {{-- Contenedor de Comandas --}}
        <main class="flex-1 overflow-x-auto overflow-y-hidden pb-4">
            <div class="flex gap-4 h-full items-start">

                <template x-if="comandas.length === 0">
                    <div class="w-full flex flex-col items-center justify-center h-96 text-slate-500 text-xs">
                        <div class="text-3xl mb-2">🍽️</div>
                        <span>No hay comandas pendientes para esta estación.</span>
                    </div>
                </template>

                <template x-for="c in comandas" :key="c.id">
                    <div class="w-80 shrink-0 bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-2xl flex flex-col justify-between space-y-4">
                        <div>
                            {{-- Cabecera de la Tarjeta (Mesa y Minutos) --}}
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

                            {{-- Partidas / Items --}}
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

                        {{-- Botón Despachar Comanda --}}
                        <div>
                            <button type="button" @click="despacharComanda(c.id)"
                                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-emerald-950/40 flex items-center justify-center gap-2 cursor-pointer">
                                <span>✓ Despachar Comanda</span>
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
                estacionSeleccionada: 'todas',
                horaActual: '',

                iniciarMonitor() {
                    this.actualizarHora();
                    setInterval(() => this.actualizarHora(), 1000);
                    this.cargarComandas();
                    setInterval(() => this.cargarComandas(), 5000);
                },

                actualizarHora() {
                    const ahora = new Date();
                    this.horaActual = ahora.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
                },

                cambiarEstacion(estacion) {
                    this.estacionSeleccionada = estacion;
                    this.cargarComandas();
                },

                async cargarComandas() {
                    try {
                        const url = `${this.routes.activas}?sucursal_id=${this.sucursalId}&estacion=${this.estacionSeleccionada}`;
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (res.ok) {
                            this.comandas = await res.json();
                        }
                    } catch (e) {
                        console.error('Error cargando comandas KDS:', e);
                    }
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
                            }
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