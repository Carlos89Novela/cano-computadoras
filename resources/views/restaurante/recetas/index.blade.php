<x-app-layout>
    {{-- ===================================================================== --}}
    {{-- ENCABEZADO DEL MÓDULO DE RECETARIO Y ESCANDALLOS (BOM)               --}}
    {{-- ===================================================================== --}}
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-white flex items-center gap-2">
                    <span>🍳 Recetario & Escandallos (BOM)</span>
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ $empresa?->nombre ?? 'Restaurante' }} | Gestión técnica de platillos elaborados, ingredientes, costeos y tiempos.
                </p>
            </div>

            {{-- Acceso rápido al catálogo general de insumos y productos --}}
            <div class="flex items-center gap-2">
                <a href="{{ route('productos.index', ['empresa' => $empresa?->id ?? 1]) }}"
                   class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-lg border border-slate-800 transition">
                    Ver Catálogo de Insumos
                </a>
            </div>
        </div>
    </x-slot>

    {{-- ===================================================================== --}}
    {{-- CONTENIDO PRINCIPAL: FILTROS Y CUADRÍCULA DE FICHAS TÉCNICAS          --}}
    {{-- ===================================================================== --}}
    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Barra de filtros por estación de cocina/barra y búsqueda por texto --}}
            <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
                {{-- Botonera de selección de estación --}}
                <div class="flex bg-slate-950 p-1 rounded-xl border border-slate-800 w-full sm:w-auto overflow-x-auto">
                    <button type="button" onclick="filtrarEstacion('todas', this)"
                            class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white cursor-pointer">
                        Todas
                    </button>
                    <button type="button" onclick="filtrarEstacion('cocina', this)"
                            class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🍳 Cocina
                    </button>
                    <button type="button" onclick="filtrarEstacion('parrilla', this)"
                            class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🔥 Parrilla
                    </button>
                    <button type="button" onclick="filtrarEstacion('postres', this)"
                            class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🍰 Postres
                    </button>
                    <button type="button" onclick="filtrarEstacion('barra', this)"
                            class="btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🍹 Barra
                    </button>
                </div>

                {{-- Campo de búsqueda en tiempo real con debounce --}}
                <div class="w-full sm:w-72">
                    <input type="search" id="busqueda-receta" oninput="buscarConDebounce()"
                           placeholder="Buscar platillo o SKU..."
                           class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            {{-- Contenedor reactivo donde se renderizan las tarjetas de recetas --}}
            <div id="grid-recetas" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="col-span-full py-16 text-center text-slate-500 text-xs">
                    Cargando recetario...
                </div>
            </div>

        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- LÓGICA DE CONSULTA ASÍNCRONA Y RENDERIZADO DEL RECETARIO             --}}
    {{-- ===================================================================== --}}
    <script>
        // Endpoint JSON que retorna las recetas filtradas por estación y búsqueda
        const urlData = "{{ route('restaurante.recetas.data') }}";

        // Estado actual de filtro y temporizador para evitar peticiones excesivas al escribir
        let estacionSeleccionada = 'todas';
        let debounceTimer = null;

        /**
         * Cambia la estación activa, actualiza el estilo visual de los botones
         * y recarga el listado de recetas.
         *
         * @param {string} estacion
         * @param {HTMLElement} btn
         */
        function filtrarEstacion(estacion, btn) {
            estacionSeleccionada = estacion;

            document.querySelectorAll('.btn-filtro').forEach(b => {
                b.className = 'btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer';
            });

            btn.className = 'btn-filtro px-3.5 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white cursor-pointer';
            cargarRecetas();
        }

        /**
         * Aplica un retardo de 300 ms al escribir en el buscador antes de consultar el backend.
         */
        function buscarConDebounce() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(cargarRecetas, 300);
        }

        /**
         * Escapa texto básico para evitar inyección HTML al construir tarjetas dinámicas.
         *
         * @param {*} valor
         * @returns {string}
         */
        function escaparHtml(valor) {
            return String(valor ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        /**
         * Consulta las recetas/fichas técnicas desde el servidor y dibuja la cuadrícula.
         */
        async function cargarRecetas() {
            const search = document.getElementById('busqueda-receta').value;
            const grid = document.getElementById('grid-recetas');

            try {
                const res = await fetch(`${urlData}?estacion=${encodeURIComponent(estacionSeleccionada)}&search=${encodeURIComponent(search)}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }

                const platillos = await res.json();

                // Mostrar mensaje amigable si no hay coincidencias
                if (!Array.isArray(platillos) || platillos.length === 0) {
                    grid.innerHTML = `
                        <div class="col-span-full py-16 text-center text-slate-500 text-xs">
                            No se encontraron recetas con los filtros seleccionados.
                        </div>
                    `;
                    return;
                }

                // Construir tarjetas con métricas de BOM (ingredientes, costo teórico, precio y margen)
                let html = '';
                platillos.forEach(p => {
                    const estacion = String(p.estacion ?? 'cocina').toLowerCase();
                    let badgeEstacion = 'bg-amber-500/10 text-amber-400 border-amber-500/20';

                    if (estacion === 'barra') badgeEstacion = 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20';
                    if (estacion === 'postres') badgeEstacion = 'bg-pink-500/10 text-pink-400 border-pink-500/20';
                    if (estacion === 'parrilla') badgeEstacion = 'bg-rose-500/10 text-rose-400 border-rose-500/20';

                    const totalIngredientes = Number(p.total_ingredientes ?? 0);
                    const costoTeorico = Number(p.costo_teorico ?? 0);
                    const precioVenta = Number(p.precio_venta ?? 0);
                    const margenPorcentaje = Number(p.margen_porcentaje ?? 0);
                    const tiempoMin = Number(p.tiempo_min ?? 0);

                    html += `
                        <div class="p-5 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col justify-between hover:border-slate-700 transition space-y-4">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded border ${badgeEstacion}">
                                        ${escaparHtml(estacion)}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono">${escaparHtml(p.sku ?? 'S/N')}</span>
                                </div>
                                <h3 class="text-sm font-bold text-white">${escaparHtml(p.nombre ?? 'Platillo')}</h3>
                                <p class="text-[11px] text-slate-400 mt-0.5">${escaparHtml(p.categoria ?? 'General')} • ${tiempoMin} min prep.</p>
                            </div>

                            <div class="p-3 bg-slate-950 rounded-xl border border-slate-800/80 space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Ingredientes en Ficha:</span>
                                    <span class="font-bold text-indigo-400">${totalIngredientes} insumos</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Costo Teórico (BOM):</span>
                                    <span class="font-bold text-rose-400">$${costoTeorico.toFixed(2)}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">Precio de Venta:</span>
                                    <span class="font-bold text-emerald-400">$${precioVenta.toFixed(2)}</span>
                                </div>
                                <div class="flex items-center justify-between pt-1 border-t border-slate-900">
                                    <span class="text-slate-400">Margen Bruto Estimado:</span>
                                    <span class="font-extrabold ${margenPorcentaje >= 65 ? 'text-emerald-400' : 'text-amber-400'}">${margenPorcentaje}%</span>
                                </div>
                            </div>

                            <a href="${escaparHtml(p.url_editar ?? '#')}"
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

        // Carga inicial al abrir el recetario
        cargarRecetas();
    </script>
</x-app-layout>
