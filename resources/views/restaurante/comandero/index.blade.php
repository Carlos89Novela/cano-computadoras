<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Comandero Meseros | {{ $empresa->nombre }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        .touch-action-manipulation { touch-action: manipulation; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans select-none touch-action-manipulation">

    <div x-data="comanderoApp({
             sucursalId: {{ $sucursal?->id ?? 1 }},
             routes: {
                 catalogo: '{{ route('restaurante.comandero.catalogo') }}',
                 enviar: '{{ route('restaurante.comandero.enviar') }}',
                 csrf: '{{ csrf_token() }}'
             }
         })"
         x-init="init()"
         class="h-screen flex flex-col p-3 lg:p-4">

        {{-- Encabezado del Comandero --}}
        <header class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-600/20 text-indigo-400 rounded-xl border border-indigo-500/30 text-lg">
                    📱
                </div>
                <div>
                    <h1 class="text-base font-bold text-white flex items-center gap-2">
                        Comandero Digital
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono" x-text="mesaActual"></span>
                    </h1>
                    <p class="text-[11px] text-slate-400">Atiende: {{ auth()->user()->name }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="modalMesa = true"
                        class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold border border-slate-700">
                    🪑 Cambiar Mesa
                </button>
                <a href="{{ route('dashboard') }}" class="px-3 py-1.5 bg-slate-900 text-slate-400 rounded-xl text-xs border border-slate-800">
                    Salir
                </a>
            </div>
        </header>

        {{-- Alerta de confirmación --}}
        <template x-if="mensajeExito">
            <div class="mb-3 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 rounded-xl text-xs font-bold" x-text="mensajeExito"></div>
        </template>

        {{-- Cuerpo Principal: Catálogo (Izquierda) y Comanda Actual (Derecha) --}}
        <main class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-3 overflow-hidden">

            {{-- Columna Izquierda: Categorías y Platillos (7 columnas) --}}
            <div class="md:col-span-7 flex flex-col h-full overflow-hidden space-y-3">
                {{-- Selector de Categorías --}}
                <div class="flex gap-2 overflow-x-auto pb-1 shrink-0">
                    <button type="button" @click="seleccionarCategoria(0)"
                            :class="categoriaSeleccionada === 0 ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-900 text-slate-400 border border-slate-800'"
                            class="px-3 py-2 rounded-xl text-xs whitespace-nowrap">
                        Todos
                    </button>
                    @foreach ($categorias as $cat)
                        <button type="button" @click="seleccionarCategoria({{ $cat->id }})"
                                :class="categoriaSeleccionada === {{ $cat->id }} ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-900 text-slate-400 border border-slate-800'"
                                class="px-3 py-2 rounded-xl text-xs whitespace-nowrap">
                            {{ $cat->nombre }}
                        </button>
                    @endforeach
                </div>

                {{-- Grid de Platillos --}}
                <div class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 gap-2 pr-1">
                    <template x-for="p in productos" :key="p.id">
                        <div @click="agregarItem(p)"
                             class="p-3 bg-slate-900 border border-slate-800 active:border-indigo-500 rounded-xl cursor-pointer flex flex-col justify-between hover:border-slate-700 transition">
                            <div>
                                <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded"
                                      :class="p.estacion === 'barra' ? 'bg-cyan-500/10 text-cyan-400' : 'bg-amber-500/10 text-amber-400'"
                                      x-text="p.estacion"></span>
                                <h3 class="text-xs font-bold text-white mt-1 leading-snug line-clamp-2" x-text="p.nombre"></h3>
                            </div>
                            <div class="mt-2 pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs">
                                <span class="font-mono font-bold text-emerald-400" x-text="'$' + p.precio.toFixed(2)"></span>
                                <span class="text-[10px] text-slate-500 font-mono" x-text="`${p.tiempo_min}m`"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Columna Derecha: Comanda / Orden Actual (5 columnas) --}}
            <div class="md:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col h-full overflow-hidden p-3.5 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                    <h2 class="text-xs font-bold uppercase text-white tracking-wider flex items-center gap-1.5">
                        <span>📋</span> Orden: <span class="text-indigo-400 font-mono" x-text="mesaActual"></span>
                    </h2>
                    <button type="button" @click="vaciarOrden()" class="text-xs text-rose-400 hover:underline">Vaciar</button>
                </div>

                {{-- Lista de platillos en la orden --}}
                <div class="flex-1 overflow-y-auto space-y-2 pr-1">
                    <template x-if="orden.length === 0">
                        <div class="text-center py-12 text-slate-500 text-xs">
                            Selecciona platillos o bebidas para armar la comanda.
                        </div>
                    </template>

                    <template x-for="(item, idx) in orden" :key="idx">
                        <div class="p-2.5 bg-slate-950 border border-slate-800 rounded-xl space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-white text-xs truncate flex-1 pr-2" x-text="item.nombre"></span>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button type="button" @click="decrementar(idx)" class="w-6 h-6 rounded bg-slate-800 text-white font-bold flex items-center justify-center">-</button>
                                    <span class="w-6 text-center font-bold font-mono text-white" x-text="item.cantidad"></span>
                                    <button type="button" @click="incrementar(idx)" class="w-6 h-6 rounded bg-slate-800 text-white font-bold flex items-center justify-center">+</button>
                                    <button type="button" @click="eliminar(idx)" class="text-rose-400 font-bold ml-1 px-1">&times;</button>
                                </div>
                            </div>
                            <div>
                                <input type="text" x-model="item.notas_especiales" placeholder="Especificaciones (ej. sin cebolla, salsa aparte)..."
                                       class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2 py-1 text-[11px] text-amber-300 placeholder-slate-600 focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Botón de envío directo a Cocina (KDS) --}}
                <div class="pt-2 border-t border-slate-800">
                    <button type="button"
                            @click="enviarOrdenACocina()"
                            :disabled="orden.length === 0 || enviando"
                            class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 disabled:bg-slate-800 disabled:text-slate-500 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                        <span x-show="!enviando">🚀 Enviar Comanda a Cocina</span>
                        <span x-show="enviando">Enviando a KDS...</span>
                    </button>
                </div>
            </div>
        </main>

        {{-- Modal Selector de Mesa / Ubicación --}}
        <div x-show="modalMesa" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalMesa = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-2xl">
                <h3 class="text-sm font-bold text-white">Seleccionar Mesa o Referencia</h3>
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="m in ['Mesa 1', 'Mesa 2', 'Mesa 3', 'Mesa 4', 'Mesa 5', 'Terraza 1', 'Terraza 2', 'Barra', 'Para Llevar']" :key="m">
                        <button type="button"
                                @click="mesaActual = m; modalMesa = false"
                                :class="mesaActual === m ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-950 text-slate-300 border border-slate-800'"
                                class="py-2.5 rounded-xl text-xs transition" x-text="m">
                        </button>
                    </template>
                </div>
                <div>
                    <label class="text-[11px] text-slate-400 block mb-1">O escribir referencia personalizada:</label>
                    <input type="text" x-model="mesaActual" placeholder="ej. Mesa 12 / Evento Privado"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <button type="button" @click="modalMesa = false" class="w-full py-2 bg-slate-800 text-white text-xs font-bold rounded-xl">
                    Confirmar Mesa
                </button>
            </div>
        </div>
    </div>

    <script>
        function comanderoApp(config) {
            return {
                sucursalId: config.sucursalId,
                routes: config.routes,
                productos: [],
                orden: [],
                categoriaSeleccionada: 0,
                mesaActual: 'Mesa 1',
                modalMesa: false,
                enviando: false,
                mensajeExito: '',

                async init() {
                    await this.cargarProductos();
                },

                async seleccionarCategoria(catId) {
                    this.categoriaSeleccionada = catId;
                    await this.cargarProductos();
                },

                async cargarProductos() {
                    try {
                        const res = await fetch(`${this.routes.catalogo}?categoria_id=${this.categoriaSeleccionada}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            this.productos = await res.json();
                        }
                    } catch (e) {
                        console.error('Error al cargar catálogo en comandero:', e);
                    }
                },

                agregarItem(producto) {
                    const existente = this.orden.find(i => i.producto_id === producto.id);
                    if (existente) {
                        existente.cantidad += 1;
                    } else {
                        this.orden.push({
                            producto_id: producto.id,
                            nombre: producto.nombre,
                            precio: producto.precio,
                            cantidad: 1,
                            notas_especiales: ''
                        });
                    }
                },

                incrementar(idx) {
                    this.orden[idx].cantidad += 1;
                },

                decrementar(idx) {
                    if (this.orden[idx].cantidad > 1) {
                        this.orden[idx].cantidad -= 1;
                    } else {
                        this.eliminar(idx);
                    }
                },

                eliminar(idx) {
                    this.orden.splice(idx, 1);
                },

                vaciarOrden() {
                    this.orden = [];
                },

                async enviarOrdenACocina() {
                    if (this.orden.length === 0 || this.enviando) return;
                    this.enviando = true;
                    this.mensajeExito = '';

                    const payload = {
                        sucursal_id: this.sucursalId,
                        mesa_o_referencia: this.mesaActual,
                        items: this.orden.map(i => ({
                            producto_id: i.producto_id,
                            cantidad: i.cantidad,
                            notas_especiales: i.notas_especiales
                        }))
                    };

                    try {
                        const res = await fetch(this.routes.enviar, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.routes.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await res.json();
                        if (res.ok) {
                            this.mensajeExito = `¡Comanda ${data.folio} despachada a cocina para ${this.mesaActual}!`;
                            this.orden = [];
                        } else {
                            alert(data.message || 'Error al enviar la comanda.');
                        }
                    } catch (e) {
                        alert('Error de conexión al enviar la orden.');
                    } finally {
                        this.enviando = false;
                    }
                }
            };
        }
    </script>
</body>
</html>
