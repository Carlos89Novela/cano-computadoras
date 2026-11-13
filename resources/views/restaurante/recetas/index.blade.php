<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-white flex items-center gap-2">
                    <span>🍳 Recetario & Escandallos (BOM)</span>
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ $empresa->nombre }} | Gestión técnica de platillos elaborados, ingredientes, costeos y tiempos.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('productos.index', ['empresa' => $empresa->id]) }}"
                   class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-lg border border-slate-800 transition">
                    📦 Ver Catálogo de Insumos
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Barra de Filtros y Búsqueda --}}
            <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
                {{-- Selector de Estación --}}
                <div class="flex bg-slate-950 p-1 rounded-xl border border-slate-800 w-full sm:w-auto overflow-x-auto">
                    <button onclick="filtrarEstacion('todas', this)" class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white cursor-pointer">
                        Todas
                    </button>
                    <button onclick="filtrarEstacion('cocina', this)" class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🍳 Cocina
                    </button>
                    <button onclick="filtrarEstacion('parrilla', this)" class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🔥 Parrilla
                    </button>
                    <button onclick="filtrarEstacion('postres', this)" class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🍰 Postres
                    </button>
                    <button onclick="filtrarEstacion('barra', this)" class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🍹 Barra
                    </button>
                </div>

                {{-- Input de Búsqueda --}}
                <div class="w-full sm:w-72">
                    <input type="search" id="busqueda-receta" oninput="buscarConDebounce()"
                           placeholder="Buscar platillo o SKU..."
                           class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            {{-- Grid de Recetas Técnicas --}}
            <div id="grid-recetas" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="col-span-full py-16 text-center text-slate-500 text-xs">
                    Cargando recetario...
                </div>
            </div>

        </div>
    </div>

    <script>
        const urlData = "{{ route('restaurante.recetas.data') }}";
        let estacionSeleccionada = 'todas';
        let debounceTimer = null;

        function filtrarEstacion(estacion, btn) {
            estacionSeleccionada = estacion;
            document.querySelectorAll('.btn-filtro').forEach(b => {
                b.className = 'btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer';
            });
            btn.className = 'btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white cursor-pointer';
            cargarRecetas();
        }

        function buscarConDebounce() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(cargarRecetas, 300);
        }

        async function cargarRecetas() {
            const search = document.getElementById('busqueda-receta').value;
            const grid = document.getElementById('grid-recetas');

            try {
                const res = await fetch(`${urlData}?estacion=${estacionSeleccionada}&search=${encodeURIComponent(search)}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) throw new Error();

                const platillos = await res.json();

                if (platillos.length === 0) {
                    grid.innerHTML = `
                        <div class="col-span-full py-16 text-center text-slate-500 text-xs">
                            No se encontraron recetas con los filtros seleccionados.
                        </div>
                    `;
                    return;
                }

                let html = '';
                platillos.forEach(p => {
                    let badgeEstacion = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                    if (p.estacion === 'barra') badgeEstacion = 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20';
                    if (p.estacion === 'postres') badgeEstacion = 'bg-pink-500/10 text-pink-400 border-pink-500/20';
                    if (p.estacion === 'parrilla') badgeEstacion = 'bg-rose-500/10 text-rose-400 border-rose-500/20';

                    html += `
                        <div class="p-5 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col justify-between hover:border-slate-700 transition space-y-4">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded border ${badgeEstacion}">
                                        ${p.estacion}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono">${p.sku}</span>
                                </div>
                                <h3 class="text-sm font-bold text-white">${p.nombre}</h3>
                                <p class="text-[11px] text-slate-400 mt-0.5">${p.categoria} • ${p.tiempo_min} min prep.</p>
                            </div>

                            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Ingredientes en Ficha:</span>
                                    <span class="font-bold text-indigo-400">${p.total_ingredientes} insumos</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Costo Teórico (BOM):</span>
                                    <span class="font-bold text-rose-400">$${p.costo_teorico.toFixed(2)}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Precio de Venta:</span>
                                    <span class="font-bold text-emerald-400">$${p.precio_venta.toFixed(2)}</span>
                                </div>
                                <div class="flex items-center justify-between pt-1 border-t border-slate-900">
                                    <span class="text-slate-400">Margen Bruto Estimado:</span>
                                    <span class="font-extrabold ${p.margen_porcentaje >= 65 ? 'text-emerald-400' : 'text-amber-400'}">${p.margen_porcentaje}%</span>
                                </div>
                            </div>

                            <a href="${p.url_editar}"
                               class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition flex items-center justify-center gap-2">
                                <span>✏️</span> Ver / Modificar Ficha Técnica
                            </a>
                        </div>
                    `;
                });

                grid.innerHTML = html;
            } catch (e) {
                grid.innerHTML = `
                    <div class="col-span-full py-16 text-center text-rose-400 text-xs">
                        Error al cargar las recetas del restaurante.
                    </div>
                `;
            }
        }

        cargarRecetas();
    </script>
</x-app-layout>
