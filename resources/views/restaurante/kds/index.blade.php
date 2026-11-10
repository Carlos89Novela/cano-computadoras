<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KDS - Monitor de Cocina | {{ $empresa->nombre }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen overflow-x-hidden font-sans">

    <div x-data="kdsMonitor({
             sucursalId: {{ $sucursal?->id ?? 1 }},
             routes: {
                 activas: '{{ route('restaurante.kds.comandas-activas') }}',
                 csrf: '{{ csrf_token() }}'
             }
         })"
         x-init="iniciarMonitor()"
         class="p-4 flex flex-col h-screen">

        {{-- Barra superior de la Pantalla de Cocina --}}
        <header class="flex flex-wrap items-center justify-between pb-3 mb-4 border-b border-slate-800 gap-3">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-500/10 text-amber-400 rounded-xl border border-amber-500/20 font-bold text-lg">
                    👨‍🍳 KDS
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white flex items-center gap-2">
                        Cocina & Preparación
                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700 font-mono"
                              x-text="`${comandas.length} activas`"></span>
                    </h1>
                    <p class="text-xs text-slate-400">
                        {{ $sucursal?->nombre ?? 'Sucursal Matriz' }} | Hora: <strong class="text-white font-mono" x-text="horaActual"></strong>
                    </p>
                </div>
            </div>

            {{-- Filtro por Estación de Trabajo --}}
            <div class="flex items-center gap-2">
                <template x-for="est in ['todas', 'cocina', 'parrilla', 'barra', 'postres']" :key="est">
                    <button type="button"
                            @click="cambiarEstacion(est)"
                            :class="estacionSeleccionada === est ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-900 text-slate-400 border border-slate-800 hover:text-white'"
                            class="px-3 py-1.5 rounded-xl text-xs uppercase tracking-wider transition capitalize"
                            x-text="est === 'todas' ? 'Todas las estaciones' : est">
                    </button>
                </template>

                <a href="{{ route('dashboard') }}" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white rounded-xl text-xs border border-slate-800 transition">
                    Salir
                </a>
            </div>
        </header>

        {{-- Grid Dinámico de Comandas --}}
        <main class="flex-1 overflow-x-auto overflow-y-hidden pb-2">
            <div class="flex gap-4 h-full items-start">

                <template x-if="comandas.length === 0">
                    <div class="w-full flex flex-col items-center justify-center h-96 text-slate-500">
                        <div class="text-4xl mb-2">🍽️</div>
                        <p class="text-sm">No hay pedidos pendientes en esta estación.</p>
                        <span class="text-xs text-slate-600">Los nuevos pedidos aparecerán aquí al instante.</span>
                    </div>
                </template>

                <template x-for="c in comandas" :key="c.id">
                    <div class="w-80 shrink-0 bg-slate-900 border rounded-2xl flex flex-col max-h-full shadow-2xl transition"
                         :class="{
                             'border-emerald-500/40': minutosTranscurridos(c.enviada_a_cocina_at) < 10,
                             'border-amber-500/50 shadow-amber-500/5': minutosTranscurridos(c.enviada_a_cocina_at) >= 10 && minutosTranscurridos(c.enviada_a_cocina_at) < 20,
                             'border-rose-500/70 shadow-rose-500/10 animate-pulse': minutosTranscurridos(c.enviada_a_cocina_at) >= 20
                         }">

                        {{-- Encabezado de la Tarjeta de Pedido --}}
                        <div class="p-3.5 border-b border-slate-800 flex items-center justify-between rounded-t-2xl"
                             :class="{
                                 'bg-emerald-950/30': minutosTranscurridos(c.enviada_a_cocina_at) < 10,
                                 'bg-amber-950/30': minutosTranscurridos(c.enviada_a_cocina_at) >= 10 && minutosTranscurridos(c.enviada_a_cocina_at) < 20,
                                 'bg-rose-950/40': minutosTranscurridos(c.enviada_a_cocina_at) >= 20
                             }">
                            <div>
                                <div class="text-sm font-black text-white font-mono" x-text="c.mesa_o_referencia"></div>
                                <div class="text-[11px] text-slate-400 font-mono" x-text="`${c.folio} • Mesero: ${c.mesero?.name || 'Caja'}`"></div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-full"
                                      :class="{
                                          'bg-emerald-500/20 text-emerald-300': minutosTranscurridos(c.enviada_a_cocina_at) < 10,
                                          'bg-amber-500/20 text-amber-300': minutosTranscurridos(c.enviada_a_cocina_at) >= 10 && minutosTranscurridos(c.enviada_a_cocina_at) < 20,
                                          'bg-rose-500/20 text-rose-300': minutosTranscurridos(c.enviada_a_cocina_at) >= 20
                                      }"
                                      x-text="`${minutosTranscurridos(c.enviada_a_cocina_at)} min`">
                                </span>
                            </div>
                        </div>

                        {{-- Lista de Platillos / Bebidas --}}
                        <div class="p-3 overflow-y-auto flex-1 space-y-2">
                            <template x-for="item in c.items" :key="item.id">
                                <div @click="toggleItemEstado(item)"
                                     class="p-2.5 rounded-xl border cursor-pointer select-none transition flex items-start justify-between gap-2"
                                     :class="{
                                         'bg-slate-950/80 border-slate-800 text-slate-200 hover:border-slate-700': item.estado === 'pendiente',
                                         'bg-amber-500/10 border-amber-500/30 text-amber-200': item.estado === 'en_preparacion',
                                         'bg-emerald-500/10 border-emerald-500/30 text-emerald-300 line-through opacity-60': item.estado === 'listo'
                                     }">
                                    <div>
                                        <div class="font-bold text-xs flex items-center gap-1.5">
                                            <span class="w-5 h-5 rounded bg-slate-800 text-white flex items-center justify-center font-mono text-[11px]"
                                                  x-text="Number(item.cantidad).toFixed(0)"></span>
                                            <span x-text="item.producto?.nombre"></span>
                                        </div>
                                        <template x-if="item.notas_especiales">
                                            <p class="text-[11px] text-amber-400 mt-1 pl-6 italic" x-text="`⚠️ ${item.notas_especiales}`"></p>
                                        </template>
                                    </div>
                                    <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded border"
                                          :class="{
                                              'border-slate-700 text-slate-400': item.estado === 'pendiente',
                                              'border-amber-500/40 text-amber-300': item.estado === 'en_preparacion',
                                              'border-emerald-500/40 text-emerald-300': item.estado === 'listo'
                                          }"
                                          x-text="item.estado === 'listo' ? '✓ Listo' : (item.estado === 'en_preparacion' ? 'Fuego' : 'Espera')">
                                    </span>
                                </div>
                            </template>
                        </div>

                        {{-- Botón para Despachar / Completar toda la comanda --}}
                        <div class="p-3 border-t border-slate-800 bg-slate-950/50 rounded-b-2xl">
                            <button type="button"
                                    @click="despacharComandaCompleta(c.id)"
                                    class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-1.5">
                                <span>✓ Marcar Orden Completa</span>
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
                timerActualizacion: null,

                async iniciarMonitor() {
                    this.actualizarHora();
                    setInterval(() => this.actualizarHora(), 1000);

                    await this.cargarComandas();
                    // Polling reactivo cada 5 segundos
                    this.timerActualizacion = setInterval(() => {
                        this.cargarComandas();
                    }, 5000);
                },

                actualizarHora() {
                    const ahora = new Date();
                    this.horaActual = ahora.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
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
                        console.error('Error al actualizar pantalla KDS:', e);
                    }
                },

                minutosTranscurridos(fechaStr) {
                    if (!fechaStr) return 0;
                    const diffMs = new Date() - new Date(fechaStr);
                    return Math.max(0, Math.floor(diffMs / 60000));
                },

                async toggleItemEstado(item) {
                    const siguienteEstado = item.estado === 'pendiente'
                        ? 'en_preparacion'
                        : (item.estado === 'en_preparacion' ? 'listo' : 'pendiente');

                    item.estado = siguienteEstado;

                    try {
                        await fetch(`/restaurante/kds/items/${item.id}/estado`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.routes.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ estado: siguienteEstado })
                        });
                        await this.cargarComandas();
                    } catch (e) {
                        console.error('Error al actualizar item:', e);
                    }
                },

                async despacharComandaCompleta(comandaId) {
                    try {
                        await fetch(`/restaurante/kds/comandas/${comandaId}/completar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.routes.csrf,
                                'Accept': 'application/json'
                            }
                        });
                        await this.cargarComandas();
                    } catch (e) {
                        console.error('Error al completar comanda:', e);
                    }
                }
            };
        }
    </script>
</body>
</html>
