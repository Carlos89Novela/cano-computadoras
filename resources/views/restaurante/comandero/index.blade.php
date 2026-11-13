<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950">
<head>
    {{-- ========================================================================= --}}
    {{-- SECCIÓN 1: METADATOS, ESTILOS BASE Y DIRECTIVAS VITE                      --}}
    {{-- ========================================================================= --}}
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Comandero | {{ $empresa->nombre }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .grid-catalogo {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }
        @media (min-width: 640px) {
            .grid-catalogo {
                grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
                gap: 12px;
            }
        }
        .platillo-card {
            background-color: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 14px;
            padding: 12px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 110px;
            transition: all 0.15s ease;
        }
        .platillo-card:hover {
            border-color: #6366f1;
            background-color: #1e1b4b20;
        }
        .platillo-card:active { transform: scale(0.96); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 font-sans antialiased overflow-hidden m-0 p-0 flex flex-col">

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 2: CONSULTAS DE CATÁLOGO, EXTRAS, PROMOCIONES Y ÁREAS DEL SALÓN   --}}
    {{-- ========================================================================= --}}
    @php
        // 1. Platillos Elaborados activos de la empresa restaurantera
        $platillosElaborados = \App\Models\Producto::where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->where('es_receta', true)
            ->with('receta')
            ->get();

        // 2. Insumos configurados como Extras Vendibles Sueltos
        $productosExtrasSueltos = \App\Models\Producto::where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->where('es_extra', true)
            ->get();

        // 2.1 Mapear extras compatibles para el modal de personalización de platillos
        $extrasDisponibles = $productosExtrasSueltos->map(function ($ext) {
            $nombreLower = strtolower($ext->nombre);
            $esBarra = str_contains($nombreLower, 'jarabe')
                || str_contains($nombreLower, 'leche')
                || str_contains($nombreLower, 'hielo')
                || str_contains($nombreLower, 'shot')
                || str_contains($nombreLower, 'limón');

            return [
                'id' => $ext->id,
                'nombre' => $ext->nombre,
                'precio_extra' => (float) ($ext->precio_extra ?? $ext->precio_base ?? 0),
                'tipo' => $esBarra ? 'barra' : 'cocina',
            ];
        })->values()->all();

        // 3. Promociones y Combos vigentes en este momento
        $promocionesVigentes = $promocionesVigentes ?? \App\Models\Promocion::query()
            ->where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->with(['items.producto.receta', 'items.categoria'])
            ->get()
            ->filter(fn (\App\Models\Promocion $p) => $p->estaVigenteAhora())
            ->values();

        // 4. Áreas y Mesas físicas sincronizadas con el Plano del Salón
        $areasMesas = \App\Models\RestauranteArea::where('empresa_id', $empresa->id)
            ->where('activo', true)
            ->with(['mesas' => fn ($q) => $q->where('activo', true)->orderBy('id')])
            ->orderBy('orden')
            ->get();

        // Mesa inicial por defecto
        $mesaInicial = request()->query('mesa')
            ?? $areasMesas->first()?->mesas?->first()?->numero_o_nombre
            ?? 'Mesa 1';
    @endphp

    <div class="h-full flex flex-col p-2 sm:p-4 box-border">

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 3: ENCABEZADO SUPERIOR Y SELECTOR RÁPIDO DE MESA                  --}}
        {{-- ========================================================================= --}}
        <header class="flex items-center justify-between pb-2 sm:pb-3 mb-2 border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-2 sm:gap-3">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center font-bold text-base sm:text-lg">
                    📱
                </div>
                <div>
                    <h1 class="text-xs sm:text-base font-bold flex items-center gap-1.5">
                        <span>Comandero</span>
                        <span id="label-mesa" class="text-[11px] sm:text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            {{ $mesaInicial }}
                        </span>
                    </h1>
                    <p class="text-[10px] sm:text-xs text-slate-400 truncate max-w-[170px] sm:max-w-none">
                        {{ auth()->user()->name }} | {{ $empresa->nombre }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="abrirModalMesas()"
                        class="px-2.5 sm:px-3.5 py-1.5 sm:py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 transition flex items-center gap-1 cursor-pointer">
                    <span>🪑</span> Cambiar Mesa
                </button>
                <a href="{{ route('restaurante.mesas.plano') }}"
                   class="px-2.5 sm:px-3 py-1.5 sm:py-2 bg-slate-900 hover:bg-slate-800 text-slate-400 text-xs rounded-lg transition border border-slate-800">
                    🗺️ Plano
                </a>
                <a href="{{ route('admin.dashboard') }}"
                   class="px-2.5 sm:px-3 py-1.5 sm:py-2 bg-slate-900 hover:bg-slate-800 text-slate-400 text-xs rounded-lg transition">
                    Salir
                </a>
            </div>
        </header>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 4: CONTENEDOR OPERATIVO (MENÚ IZQUIERDA Y COMANDA DERECHA)        --}}
        {{-- ========================================================================= --}}
        <div class="flex-1 flex flex-col md:flex-row gap-3 min-h-0 overflow-hidden relative">

            {{-- PANEL IZQUIERDO: Filtros de Categoría, Extras y Promociones/Combos --}}
            <div class="flex-1 flex flex-col min-h-0 overflow-hidden">

                {{-- Barra de Categorías Horizontal --}}
                <div class="flex gap-2 overflow-x-auto pb-2 mb-2 shrink-0 no-scrollbar">
                    <button onclick="filtrarCategoria(0, this)"
                            class="btn-cat px-3 py-1.5 text-xs font-semibold rounded-lg shrink-0 transition bg-indigo-600 text-white cursor-pointer">
                        Todos
                    </button>
                    @foreach ($categorias as $cat)
                        <button onclick="filtrarCategoria({{ $cat->id }}, this)"
                                class="btn-cat px-3 py-1.5 text-xs font-semibold rounded-lg shrink-0 transition bg-slate-900 text-slate-400 hover:bg-slate-800 border border-slate-800 cursor-pointer">
                            {{ $cat->nombre }}
                        </button>
                    @endforeach

                    {{-- Pestaña Exclusiva de Extras --}}
                    <button onclick="filtrarCategoria('extras', this)"
                            class="btn-cat px-3 py-1.5 text-xs font-bold rounded-lg shrink-0 transition bg-amber-950/40 text-amber-300 hover:bg-amber-900/60 border border-amber-500/30 cursor-pointer">
                        🧀 Extras & Guarniciones
                    </button>

                    {{-- Pestaña Exclusiva de Promociones y Combos (JS Nativo) --}}
                    <button onclick="filtrarCategoria('promociones', this)"
                            class="btn-cat px-3 py-1.5 text-xs font-bold rounded-lg shrink-0 transition bg-purple-950/50 text-purple-300 hover:bg-purple-900/70 border border-purple-500/40 cursor-pointer">
                        🎉 Promociones &amp; Combos ({{ $promocionesVigentes->count() }})
                    </button>
                </div>

                {{-- Rejilla Principal de Platillos, Extras y Promociones --}}
                <div class="flex-1 overflow-y-auto pr-1 pb-16 md:pb-0">
                    <div class="grid-catalogo" id="grid-platillos">

                        {{-- 1. Platillos Elaborados --}}
                        @foreach ($platillosElaborados as $p)
                            @php
                                $estacion = $p->receta?->estacion_cocina ?? 'cocina';
                                $badgeColor = match($estacion) {
                                    'parrilla' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                    'barra' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
                                    'postres' => 'bg-pink-500/10 text-pink-400 border-pink-500/20',
                                    default => 'bg-amber-500/10 text-amber-400 border-amber-500/20'
                                };
                            @endphp
                            <div class="platillo-card item-categoria-{{ $p->categoria_producto_id }}"
                                 onclick="abrirModalPersonalizar({{ $p->id }}, '{{ addslashes($p->nombre) }}', {{ (float) $p->precio_base }}, '{{ $estacion }}')">
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded border {{ $badgeColor }}">
                                            {{ $estacion }}
                                        </span>
                                        <span class="text-[9px] text-slate-400 font-mono">{{ $p->receta?->tiempo_preparacion_minutos ?? 10 }}m</span>
                                    </div>
                                    <h3 class="text-xs font-bold text-slate-100 leading-snug line-clamp-2">{{ $p->nombre }}</h3>
                                </div>
                                <div class="mt-2 text-xs sm:text-sm font-black text-emerald-400">
                                    ${{ number_format((float) $p->precio_base, 2) }}
                                </div>
                            </div>
                        @endforeach

                        {{-- 2. Extras como productos sueltos --}}
                        @foreach ($productosExtrasSueltos as $ext)
                            <div class="platillo-card item-categoria-extras hidden"
                                 onclick="agregarDirectoExtra({{ $ext->id }}, '{{ addslashes($ext->nombre) }}', {{ (float) $ext->precio_extra }})">
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded border bg-amber-500/10 text-amber-400 border-amber-500/20">
                                            Extra / Suelto
                                        </span>
                                        <span class="text-[9px] text-slate-400 font-mono">{{ $ext->sku }}</span>
                                    </div>
                                    <h3 class="text-xs font-bold text-amber-200 leading-snug line-clamp-2">{{ $ext->nombre }}</h3>
                                </div>
                                <div class="mt-2 text-xs sm:text-sm font-black text-emerald-400">
                                    ${{ number_format((float) $ext->precio_extra, 2) }}
                                </div>
                            </div>
                        @endforeach

                    </div>

                    {{-- 3. Contenedor Exclusivo de Promociones & Combos Vigentes --}}
                    <div id="grid-promociones" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-1">
                        @forelse ($promocionesVigentes as $promo)
                            @php
                                $itemsPayload = $promo->items->map(function ($it) {
                                    if (! $it->producto) {
                                        return null;
                                    }
                                    return [
                                        'id' => $it->producto->id,
                                        'nombre' => $it->producto->nombre,
                                        'precio' => (float) $it->producto->precio_base,
                                        'cantidad' => (int) ($it->cantidad ?? 1),
                                    ];
                                })->filter()->values();

                                $multiplicador = in_array($promo->tipo, ['2x1', 'segundo_al_porcentaje'], true) ? 2 : 1;
                                $etiquetaBadge = match($promo->tipo) {
                                    '2x1' => '🔥 2x1',
                                    'segundo_al_porcentaje' => '🏷️ 2do al ' . (int)$promo->valor_descuento . '%',
                                    'precio_fijo_combo' => '🍔 COMBO $' . number_format((float)$promo->precio_combo, 2),
                                    default => '⏰ ' . (int)$promo->valor_descuento . '% DESC.',
                                };
                            @endphp

                            <div class="p-4 bg-gradient-to-br from-indigo-950/70 to-slate-900 border border-indigo-500/40 rounded-2xl flex flex-col justify-between space-y-3 shadow-lg">
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-1.5">
                                        <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                            {{ $etiquetaBadge }}
                                        </span>
                                        <span class="text-[10px] font-bold text-emerald-400">● Vigente Ahora</span>
                                    </div>

                                    <h3 class="text-sm font-black text-white">{{ $promo->nombre }}</h3>
                                    @if ($promo->descripcion)
                                        <p class="text-[11px] text-slate-300 mt-1">{{ $promo->descripcion }}</p>
                                    @endif

                                    <div class="mt-2.5 flex flex-wrap gap-1">
                                        @foreach ($promo->items as $it)
                                            <span class="text-[10px] px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-amber-300 font-semibold">
                                                {{ $it->producto?->nombre ?? ('Categoría: ' . $it->categoria?->nombre) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                <button type="button"
                                        onclick='agregarPromocionAOrden(@json($promo->nombre), @json($itemsPayload), {{ $multiplicador }})'
                                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition shadow-md cursor-pointer">
                                    + Agregar Promoción a la Orden
                                </button>
                            </div>
                        @empty
                            <div class="col-span-full py-12 text-center bg-slate-900/70 border border-slate-800 rounded-2xl text-slate-400 text-xs">
                                No hay promociones o combos vigentes en este día y horario.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Barra Flotante Inferior en Dispositivos Móviles --}}
            <div id="barra-movil-orden" onclick="alternarOrdenMovil(true)"
                 class="md:hidden fixed bottom-3 left-3 right-3 bg-indigo-600 active:bg-indigo-700 text-white rounded-2xl p-3 shadow-2xl flex items-center justify-between z-30 cursor-pointer">
                <div class="flex items-center gap-2">
                    <span class="bg-black/20 text-xs font-bold px-2 py-0.5 rounded-lg" id="badge-cant-movil">0</span>
                    <span class="text-xs font-bold">Ver Comanda (<span id="header-mesa-movil">{{ $mesaInicial }}</span>)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-black" id="total-movil-bar">$0.00</span>
                    <span class="text-xs">▲</span>
                </div>
            </div>

            {{-- PANEL DERECHO: Ticket de Orden Activa y Nueva Ronda --}}
            <div id="panel-orden"
                 class="fixed inset-y-0 right-0 z-40 w-full sm:w-96 md:static md:w-80 lg:w-96 bg-slate-900 border-l md:border border-slate-800 md:rounded-2xl flex flex-col shrink-0 overflow-hidden transition-transform duration-200 ease-in-out translate-x-full md:translate-x-0">

                <div class="p-3 bg-slate-800/40 border-b border-slate-800 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <button onclick="alternarOrdenMovil(false)" class="md:hidden text-slate-400 hover:text-white p-1 text-sm font-bold">✕</button>
                        <span class="text-xs font-bold text-slate-300">📋 ORDEN: <strong id="header-mesa" class="text-white">{{ $mesaInicial }}</strong></span>
                    </div>
                    <button onclick="vaciarOrdenNueva()" class="text-[11px] text-rose-400 hover:text-rose-300 font-semibold transition cursor-pointer">Limpiar ronda</button>
                </div>

                {{-- Listado de Partidas (Acumuladas + Ronda Actual) --}}
                <div class="flex-1 overflow-y-auto p-3 space-y-2" id="lista-orden">
                    <div id="empty-state" class="h-full flex flex-col items-center justify-center text-slate-500 text-xs py-10">
                        <span class="text-2xl mb-2">🍽️</span>
                        <span>Comanda vacía.</span>
                        <span class="text-[10px] text-slate-600 mt-1">Selecciona platillos a la izquierda.</span>
                    </div>
                </div>

                {{-- Botón para Solicitar Cuenta a Caja --}}
                <div id="contenedor-btn-cuenta" class="px-3 pt-1">
                    <button type="button" onclick="pedirCuentaMesaActual()"
                            class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-amber-300 border border-amber-500/30 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer">
                        <span>🧾</span> Pedir Cuenta a Caja (Precuenta)
                    </button>
                </div>

                {{-- Pie de Orden: Total y Despacho a Cocina/Barra --}}
                <div class="p-3 sm:p-4 bg-slate-950 border-t border-slate-800 shrink-0 space-y-2.5 mt-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-medium">Total Comanda:</span>
                        <span id="total-comanda" class="text-base sm:text-lg font-black text-emerald-400">$0.00</span>
                    </div>

                    <button id="btn-enviar" onclick="enviarComanda()"
                            disabled
                            class="w-full py-2.5 sm:py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold text-xs rounded-xl transition flex items-center justify-center gap-2 shadow-lg shadow-emerald-950/50 cursor-pointer">
                        <span id="btn-enviar-text">🚀 Enviar a Cocina</span>
                    </button>
                </div>
            </div>

        </div>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 5: MODAL DE PERSONALIZACIÓN DE PLATILLO Y EXTRAS CONTEXTUALES     --}}
        {{-- ========================================================================= --}}
        <div id="modal-personalizar" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl flex flex-col max-h-[90vh]">
                <div class="flex items-start justify-between border-b border-slate-800 pb-3">
                    <div>
                        <h3 class="text-sm font-bold text-white" id="modal-platillo-nombre">Platillo</h3>
                        <p class="text-xs font-semibold text-emerald-400 mt-0.5" id="modal-platillo-precio">$0.00</p>
                    </div>
                    <button onclick="cerrarModalPersonalizar()" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <div class="flex-1 overflow-y-auto space-y-4 pr-1">
                    {{-- Extras Contextuales --}}
                    <div id="seccion-extras-modal">
                        <label class="block text-xs font-bold uppercase tracking-wider text-indigo-400 mb-2">
                            🧀 Extras Compatibles con esta Receta
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" id="contenedor-extras">
                            @foreach ($extrasDisponibles as $extra)
                                <label class="item-extra-label extra-tipo-{{ $extra['tipo'] }} p-2.5 bg-slate-950 border border-slate-800 rounded-xl flex items-center justify-between cursor-pointer hover:border-slate-700 transition">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox"
                                               value="{{ $extra['id'] }}"
                                               data-tipo="{{ $extra['tipo'] }}"
                                               data-nombre="{{ $extra['nombre'] }}"
                                               data-precio="{{ $extra['precio_extra'] }}"
                                               class="checkbox-extra rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-0">
                                        <span class="text-xs text-slate-200">{{ $extra['nombre'] }}</span>
                                    </div>
                                    <span class="text-xs font-bold text-emerald-400">+${{ number_format($extra['precio_extra'], 2) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Nota de Cocina --}}
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                            📝 Especificación para Cocina / Barra
                        </label>
                        <input type="text" id="modal-nota-input"
                               placeholder="Ej. Término medio, sin cebolla, poco hielo..."
                               class="w-full text-xs bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800 flex items-center justify-between gap-3">
                    <button onclick="cerrarModalPersonalizar()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-300">
                        Cancelar
                    </button>
                    <button onclick="confirmarAgregarAlPedido()" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5">
                        <span>+</span> Agregar a la Comanda
                    </button>
                </div>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 6: MODAL DINÁMICO DE ÁREAS Y MESAS (SINCRONIZADO CON EL PLANO)    --}}
        {{-- ========================================================================= --}}
        <div id="modal-mesas" class="hidden fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-5 space-y-4 shadow-2xl max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 shrink-0">
                    <h3 class="text-sm font-bold text-white">Seleccionar Mesa</h3>
                    <span class="text-xs text-slate-400">{{ $empresa->nombre }}</span>
                </div>

                <div id="contenedor-areas-mesas" class="flex-1 overflow-y-auto space-y-4 pr-1">
                    @forelse ($areasMesas as $area)
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                    {{ $area->nombre }}
                                </span>
                                <span class="text-[10px] text-slate-500 font-mono">
                                    {{ $area->mesas->count() }} mesas
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($area->mesas as $m)
                                    @php
                                        $estadoColor = match($m->estado) {
                                            'ocupada' => 'border-amber-500/50 bg-amber-950/20 text-amber-200',
                                            'cuenta_pedida' => 'border-indigo-500/60 bg-indigo-950/30 text-indigo-200',
                                            default => 'border-slate-800 bg-slate-950 text-slate-200 hover:border-slate-700',
                                        };
                                    @endphp
                                    <button onclick="seleccionarMesa('{{ addslashes($m->numero_o_nombre) }}')"
                                            class="btn-mesa p-3 rounded-xl border {{ $estadoColor }} text-xs font-bold transition flex flex-col items-center gap-1 cursor-pointer">
                                        <span class="text-base">
                                            @if($m->estado === 'cuenta_pedida') 🔔 @elseif($m->estado === 'ocupada') 🍽️ @else 🪑 @endif
                                        </span>
                                        <span class="truncate max-w-full">{{ $m->numero_o_nombre }}</span>
                                        <span class="text-[10px] text-slate-500 font-normal">{{ $m->capacidad }} pers</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-xs text-slate-400 py-6">
                            No hay mesas configuradas en el plano de salón.
                        </p>
                    @endforelse
                </div>

                <button onclick="cerrarModalMesas()" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-300 transition cursor-pointer shrink-0">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 7: LÓGICA OPERATIVA JAVASCRIPT, TIMBRE SONORO Y POLLING           --}}
    {{-- ========================================================================= --}}
    <script>
        const config = {
            csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            enviarUrl: "{{ route('restaurante.comandero.enviar') }}",
            consumoUrlBase: "{{ url('/restaurante/mesas') }}",
            alertasUrl: "{{ route('restaurante.comandero.alertas-listas') }}"
        };

        let mesaActiva = @json($mesaInicial);
        let ordenNueva = [];
        let consumoActivo = [];
        let totalAcumuladoCocina = 0;
        let platilloTemporal = null;
        let alertasNotificadas = new Set();

        document.addEventListener('DOMContentLoaded', () => {
            cargarConsumoMesa(mesaActiva);
        });

        // Control responsivo de panel derecho en móvil
        function alternarOrdenMovil(abrir) {
            const panel = document.getElementById('panel-orden');
            if (abrir) panel.classList.remove('translate-x-full');
            else panel.classList.add('translate-x-full');
        }

        // Control de modal selector de mesas
        function abrirModalMesas() {
            document.getElementById('modal-mesas').classList.remove('hidden');
            refrescarEstadoMesasModal();
        }
        function cerrarModalMesas() { document.getElementById('modal-mesas').classList.add('hidden'); }

        function seleccionarMesa(nombre) {
            mesaActiva = nombre;
            document.getElementById('label-mesa').innerText = nombre;
            document.getElementById('header-mesa').innerText = nombre;
            document.getElementById('header-mesa-movil').innerText = nombre;
            cerrarModalMesas();
            ordenNueva = [];
            cargarConsumoMesa(mesaActiva);
        }

        // Consulta el consumo ya enviado a cocina/barra de la mesa seleccionada
        async function cargarConsumoMesa(nombreMesa) {
            try {
                const res = await fetch(`${config.consumoUrlBase}/${encodeURIComponent(nombreMesa)}/consumo-activo`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    consumoActivo = data.items || [];
                    totalAcumuladoCocina = data.total || 0;
                    renderizarOrden();
                }
            } catch (e) {
                console.error('Error al cargar consumo de mesa:', e);
            }
        }

        // Filtro de pestañas: Categorías, Extras y Promociones & Combos
        function filtrarCategoria(catId, btn) {
            document.querySelectorAll('.btn-cat').forEach(b => {
                b.className = 'btn-cat px-3 py-1.5 text-xs font-semibold rounded-lg shrink-0 transition bg-slate-900 text-slate-400 hover:bg-slate-800 border border-slate-800 cursor-pointer';
            });
            btn.className = 'btn-cat px-3 py-1.5 text-xs font-semibold rounded-lg shrink-0 transition bg-indigo-600 text-white cursor-pointer';

            const gridPlatillos = document.getElementById('grid-platillos');
            const gridPromociones = document.getElementById('grid-promociones');

            // Si selecciona la pestaña de Promociones & Combos
            if (catId === 'promociones') {
                gridPlatillos.style.display = 'none';
                gridPromociones.classList.remove('hidden');
                return;
            }

            // Para cualquier otra pestaña, mostrar catálogo normal y ocultar promociones
            gridPromociones.classList.add('hidden');
            gridPlatillos.style.display = 'grid';

            const cards = document.querySelectorAll('.platillo-card');
            cards.forEach(c => {
                if (catId === 'extras') {
                    if (c.classList.contains('item-categoria-extras')) {
                        c.classList.remove('hidden');
                        c.style.display = 'flex';
                    } else {
                        c.style.display = 'none';
                    }
                } else if (catId === 0) {
                    if (c.classList.contains('item-categoria-extras')) {
                        c.style.display = 'none';
                    } else {
                        c.style.display = 'flex';
                    }
                } else {
                    if (c.classList.contains('item-categoria-' + catId) && !c.classList.contains('item-categoria-extras')) {
                        c.style.display = 'flex';
                    } else {
                        c.style.display = 'none';
                    }
                }
            });
        }

        // Agrega todos los platillos participantes de una promoción o combo a la nueva ronda
        function agregarPromocionAOrden(nombrePromo, items, multiplicador) {
            if (!Array.isArray(items) || items.length === 0) {
                alert('Esta promoción aplica de forma general al consumir en la categoría configurada.');
                return;
            }

            items.forEach(prod => {
                const cantidadAgregar = (prod.cantidad || 1) * (multiplicador || 1);
                const existente = ordenNueva.find(i => i.producto_id === prod.id && i.notas_especiales === `Promo: ${nombrePromo}`);

                if (existente) {
                    existente.cantidad += cantidadAgregar;
                } else {
                    ordenNueva.push({
                        producto_id: prod.id,
                        nombre: prod.nombre,
                        precio_base: prod.precio,
                        precio_total: prod.precio,
                        cantidad: cantidadAgregar,
                        es_extra_suelto: false,
                        extras: [],
                        notas_especiales: `Promo: ${nombrePromo}`
                    });
                }
            });

            renderizarOrden();
        }

        // Personalización de platillo y filtro de extras compatibles (Cocina vs Barra)
        function abrirModalPersonalizar(id, nombre, precio, estacion) {
            platilloTemporal = { id, nombre, precio, estacion };
            document.getElementById('modal-platillo-nombre').innerText = nombre;
            document.getElementById('modal-platillo-precio').innerText = '$' + precio.toFixed(2);
            document.getElementById('modal-nota-input').value = '';
            document.querySelectorAll('.checkbox-extra').forEach(cb => cb.checked = false);

            const tipoRequerido = (estacion === 'barra') ? 'barra' : 'cocina';

            let countCompatibles = 0;
            document.querySelectorAll('.item-extra-label').forEach(label => {
                if (label.classList.contains('extra-tipo-' + tipoRequerido)) {
                    label.style.display = 'flex';
                    countCompatibles++;
                } else {
                    label.style.display = 'none';
                }
            });

            document.getElementById('seccion-extras-modal').style.display = (countCompatibles > 0) ? 'block' : 'none';
            document.getElementById('modal-personalizar').classList.remove('hidden');
        }

        function cerrarModalPersonalizar() {
            document.getElementById('modal-personalizar').classList.add('hidden');
            platilloTemporal = null;
        }

        // Agregar extra vendible suelto directamente a la orden
        function agregarDirectoExtra(id, nombre, precio) {
            const existente = ordenNueva.find(item => item.producto_id === id && item.es_extra_suelto);
            if (existente) {
                existente.cantidad++;
            } else {
                ordenNueva.push({
                    producto_id: id,
                    nombre: nombre,
                    precio_base: precio,
                    precio_total: precio,
                    cantidad: 1,
                    es_extra_suelto: true,
                    extras: [],
                    notas_especiales: 'Extra / Guarnición'
                });
            }
            renderizarOrden();
        }

        function confirmarAgregarAlPedido() {
            if (!platilloTemporal) return;

            const extrasSeleccionados = [];
            let totalExtras = 0;

            document.querySelectorAll('.checkbox-extra:checked').forEach(cb => {
                const precio = parseFloat(cb.getAttribute('data-precio'));
                const nom = cb.getAttribute('data-nombre');
                extrasSeleccionados.push({ id: cb.value, nombre: nom, precio: precio });
                totalExtras += precio;
            });

            const notaUsuario = document.getElementById('modal-nota-input').value.trim();
            let textoNotas = '';

            if (extrasSeleccionados.length > 0) {
                textoNotas += 'Extras: ' + extrasSeleccionados.map(e => `+${e.nombre}`).join(', ');
            }
            if (notaUsuario !== '') {
                textoNotas += (textoNotas ? ' | ' : '') + notaUsuario;
            }

            ordenNueva.push({
                producto_id: platilloTemporal.id,
                nombre: platilloTemporal.nombre,
                precio_base: platilloTemporal.precio,
                precio_total: platilloTemporal.precio + totalExtras,
                cantidad: 1,
                es_extra_suelto: false,
                extras: extrasSeleccionados,
                notas_especiales: textoNotas
            });

            cerrarModalPersonalizar();
            renderizarOrden();
        }

        function cambiarCantidad(idx, delta) {
            ordenNueva[idx].cantidad += delta;
            if (ordenNueva[idx].cantidad <= 0) ordenNueva.splice(idx, 1);
            renderizarOrden();
        }

        function vaciarOrdenNueva() {
            ordenNueva = [];
            renderizarOrden();
        }

        // Renderizado del ticket en panel derecho
        function renderizarOrden() {
            const contenedor = document.getElementById('lista-orden');
            const totalEl = document.getElementById('total-comanda');
            const totalMovilBar = document.getElementById('total-movil-bar');
            const badgeCantMovil = document.getElementById('badge-cant-movil');
            const btnEnviar = document.getElementById('btn-enviar');

            let subtotalNueva = ordenNueva.reduce((acc, i) => acc + (i.precio_total * i.cantidad), 0);
            let granTotal = totalAcumuladoCocina + subtotalNueva;

            badgeCantMovil.innerText = ordenNueva.reduce((acc, i) => acc + i.cantidad, 0);

            let html = '';

            // 1. Consumo ya enviado en rondas previas
            if (consumoActivo.length > 0) {
                html += `
                    <div class="mb-3 pb-2 border-b border-slate-800">
                        <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-emerald-400 mb-2">
                            <span>✓ En Cocina / Activo (${consumoActivo.length})</span>
                            <span>$${totalAcumuladoCocina.toFixed(2)}</span>
                        </div>
                        <div class="space-y-1.5 opacity-90">
                `;

                consumoActivo.forEach(item => {
                    html += `
                        <div class="p-2 bg-slate-950/60 border border-slate-800/60 rounded-lg text-xs flex justify-between items-start">
                            <div>
                                <span class="font-bold text-slate-200">${item.cantidad}x ${item.nombre}</span>
                                ${item.notas_especiales ? `<p class="text-[10px] text-amber-300">👉 ${item.notas_especiales}</p>` : ''}
                                <span class="text-[9px] text-slate-500 font-mono">${item.comanda_folio}</span>
                            </div>
                            <span class="font-semibold text-slate-400">$${item.subtotal.toFixed(2)}</span>
                        </div>
                    `;
                });

                html += `</div></div>`;
            }

            // 2. Nueva ronda actual por despachar
            if (ordenNueva.length > 0) {
                html += `
                    <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-indigo-400 mb-2">
                        <span>+ Nueva Ronda por Enviar</span>
                        <button onclick="vaciarOrdenNueva()" class="text-rose-400 hover:text-rose-300 lowercase text-[10px] cursor-pointer">cancelar ronda</button>
                    </div>
                `;

                ordenNueva.forEach((item, idx) => {
                    const sub = item.precio_total * item.cantidad;
                    html += `
                        <div class="p-2.5 bg-slate-950 border border-indigo-500/40 rounded-xl space-y-1.5 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-bold text-slate-100 truncate">${item.nombre}</h4>
                                    <span class="text-[11px] text-emerald-400 font-semibold">$${sub.toFixed(2)}</span>
                                </div>
                                <div class="flex items-center gap-1 bg-slate-900 rounded-lg p-1 border border-slate-800">
                                    <button onclick="cambiarCantidad(${idx}, -1)" class="w-5 h-5 flex items-center justify-center text-xs font-bold text-slate-400 hover:text-white cursor-pointer">-</button>
                                    <span class="w-4 text-center text-xs font-bold text-white">${item.cantidad}</span>
                                    <button onclick="cambiarCantidad(${idx}, 1)" class="w-5 h-5 flex items-center justify-center text-xs font-bold text-slate-400 hover:text-white cursor-pointer">+</button>
                                </div>
                            </div>
                            ${item.notas_especiales ? `
                                <div class="text-[10px] text-amber-300 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded">
                                    👉 ${item.notas_especiales}
                                </div>
                            ` : ''}
                        </div>
                    `;
                });
            } else if (consumoActivo.length === 0) {
                html = `
                    <div id="empty-state" class="h-full flex flex-col items-center justify-center text-slate-500 text-xs py-10">
                        <span class="text-2xl mb-2">🍽️</span>
                        <span>Mesa sin consumo activo.</span>
                        <span class="text-[10px] text-slate-600 mt-1">Selecciona platillos para abrir comanda.</span>
                    </div>
                `;
            }

            contenedor.innerHTML = html;
            totalEl.innerText = '$' + granTotal.toFixed(2);
            totalMovilBar.innerText = '$' + granTotal.toFixed(2);
            btnEnviar.disabled = (ordenNueva.length === 0);
        }

        // Despacho de ronda a Cocina y Barra (KDS)
        async function enviarComanda() {
            if (ordenNueva.length === 0) return;

            const btnEnviar = document.getElementById('btn-enviar');
            const btnText = document.getElementById('btn-enviar-text');
            btnEnviar.disabled = true;
            btnText.innerText = 'Despachando...';

            try {
                const res = await fetch(config.enviarUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        mesa_o_referencia: mesaActiva,
                        items: ordenNueva.map(i => ({
                            producto_id: i.producto_id,
                            cantidad: i.cantidad,
                            notas_especiales: i.notas_especiales
                        }))
                    })
                });

                const data = await res.json();
                if (res.ok) {
                    ordenNueva = [];
                    await cargarConsumoMesa(mesaActiva);
                    alternarOrdenMovil(false);
                } else {
                    alert(data.message || 'Error al enviar comanda');
                }
            } catch (e) {
                alert('Error de conexión.');
            } finally {
                btnText.innerText = '🚀 Enviar a Cocina';
            }
        }

        // Solicitar cuenta / precuenta a Caja (ilumina la mesa en el POS)
        async function pedirCuentaMesaActual() {
            if (consumoActivo.length === 0 && ordenNueva.length === 0) {
                alert('La mesa no tiene consumo registrado.');
                return;
            }

            if (!confirm(`¿Confirmar solicitud de cuenta para ${mesaActiva}? Se notificará a Caja para imprimir el ticket.`)) {
                return;
            }

            try {
                const res = await fetch("{{ route('restaurante.comandero.pedir-cuenta-mesa') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ mesa: mesaActiva })
                });

                const data = await res.json();
                if (res.ok) {
                    alert('✓ ' + data.message);
                } else {
                    alert(data.message || 'Error al solicitar cuenta');
                }
            } catch (e) {
                alert('Error de conexión al pedir cuenta.');
            }
        }

        // Sintetizador de campana de pase mediante Web Audio API
        function sonarTimbreCampana() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                const ctx = new AudioContext();

                const emitirTono = (frecuencia, tiempoInicio, duracion) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();

                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(frecuencia, tiempoInicio);

                    gain.gain.setValueAtTime(0.3, tiempoInicio);
                    gain.gain.exponentialRampToValueAtTime(0.001, tiempoInicio + duracion);

                    osc.connect(gain);
                    gain.connect(ctx.destination);

                    osc.start(tiempoInicio);
                    osc.stop(tiempoInicio + duracion);
                };

                const ahora = ctx.currentTime;
                emitirTono(1567.98, ahora, 0.7);
                emitirTono(2093.00, ahora + 0.12, 0.9);
            } catch (e) {
                console.log('Audio en espera de interacción del usuario.');
            }
        }

        // Polling periódico de platillos terminados en KDS (cada 4 segundos)
        async function verificarPlatillosListos() {
            try {
                const res = await fetch(config.alertasUrl, {
                    headers: { 'Accept': 'application/json' }
                });

                if (res.ok) {
                    const alertas = await res.json();
                    let nuevasEnCiclo = 0;

                    alertas.forEach(alerta => {
                        const key = `${alerta.id}_${alerta.timestamp}`;

                        if (!alertasNotificadas.has(key)) {
                            alertasNotificadas.add(key);
                            const retraso = nuevasEnCiclo * 300;
                            nuevasEnCiclo++;

                            setTimeout(() => {
                                sonarTimbreCampana();
                                mostrarToastAlerta(alerta);
                            }, retraso);

                            if (alerta.mesa === mesaActiva) {
                                cargarConsumoMesa(mesaActiva);
                            }
                        }
                    });
                }
            } catch (e) {}
        }

        async function refrescarEstadoMesasModal() {
            try {
                const res = await fetch("{{ route('restaurante.comandero.estado-mesas') }}", {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;

                const areas = await res.json();
                const contenedor = document.getElementById('contenedor-areas-mesas');
                if (!contenedor) return;

                let html = '';
                areas.forEach(area => {
                    html += `
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">${area.nombre}</span>
                                <span class="text-[10px] text-slate-500 font-mono">${area.mesas.length} mesas</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                    `;

                    area.mesas.forEach(m => {
                        let estilo = 'border-slate-800 bg-slate-950 text-slate-200 hover:border-slate-700';
                        let icono = '🪑';

                        if (m.estado === 'cuenta_pedida') {
                            estilo = 'border-indigo-500/60 bg-indigo-950/30 text-indigo-200';
                            icono = '🔔';
                        } else if (m.estado === 'ocupada') {
                            estilo = 'border-amber-500/50 bg-amber-950/20 text-amber-200';
                            icono = '🍽️';
                        }

                        const nombreEscapado = m.numero_o_nombre.replace(/'/g, "\\'");
                        html += `
                            <button onclick="seleccionarMesa('${nombreEscapado}')"
                                    class="btn-mesa p-3 rounded-xl border ${estilo} text-xs font-bold transition flex flex-col items-center gap-1 cursor-pointer">
                                <span class="text-base">${icono}</span>
                                <span class="truncate max-w-full">${m.numero_o_nombre}</span>
                                <span class="text-[10px] text-slate-500 font-normal">${m.capacidad} pers</span>
                            </button>
                        `;
                    });

                    html += `</div></div>`;
                });

                contenedor.innerHTML = html;
            } catch (e) {}
        }

        // Mantener sincronizado el estado de mesas mientras el modal esté visible
        setInterval(() => {
            const modal = document.getElementById('modal-mesas');
            if (modal && !modal.classList.contains('hidden')) {
                refrescarEstadoMesasModal();
            }
        }, 4000);

        // Crea una tarjeta independiente apilable por cada platillo listo
        function mostrarToastAlerta(alerta) {
            const contenedor = document.getElementById('contenedor-toasts-pase');
            if (!contenedor) return;

            const toastId = 'toast-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
            const horaTexto = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const esBarra = (alerta.estacion || '').toLowerCase() === 'barra';
            const bordeColor = esBarra ? 'border-cyan-500' : 'border-emerald-500';
            const textoColor = esBarra ? 'text-cyan-400' : 'text-emerald-400';
            const fondoIcono = esBarra ? 'bg-cyan-500/20 text-cyan-400' : 'bg-emerald-500/20 text-emerald-400';

            const toastEl = document.createElement('div');
            toastEl.id = toastId;
            toastEl.className = `pointer-events-auto bg-slate-900/95 backdrop-blur-md border-2 ${bordeColor} rounded-2xl p-4 shadow-2xl transition-all duration-700 ease-out opacity-0 translate-x-6`;

            toastEl.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl ${fondoIcono} flex items-center justify-center font-bold text-xl shrink-0 animate-bounce">
                        🔔
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider ${textoColor}">
                                🔔 ${(alerta.estacion || 'COCINA').toUpperCase()} / ¡LISTO PARA PASE!
                            </span>
                            <span class="text-[10px] text-slate-500 font-mono shrink-0">${horaTexto}</span>
                        </div>
                        <h4 class="text-sm font-black text-white mt-0.5">🪑 ${alerta.mesa}</h4>
                        <p class="text-xs text-slate-200 mt-1 font-semibold leading-snug">${alerta.platillo}</p>
                    </div>
                    <button type="button" onclick="cerrarToastPorId('${toastId}')" class="text-slate-500 hover:text-white p-1 text-xs cursor-pointer">✕</button>
                </div>
            `;

            contenedor.prepend(toastEl);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    toastEl.classList.remove('opacity-0', 'translate-x-6');
                    toastEl.classList.add('opacity-100', 'translate-x-0');
                });
            });

            setTimeout(() => {
                cerrarToastPorId(toastId);
            }, 7000);
        }

        function cerrarToastPorId(toastId) {
            const toastEl = document.getElementById(toastId);
            if (!toastEl) return;

            toastEl.classList.remove('opacity-100', 'translate-x-0');
            toastEl.classList.add('opacity-0', '-translate-y-2', 'scale-95');

            setTimeout(() => {
                if (toastEl.parentNode) {
                    toastEl.parentNode.removeChild(toastEl);
                }
            }, 700);
        }

        setInterval(verificarPlatillosListos, 4000);
    </script>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 8: TOAST FLOTANTE DE NOTIFICACIÓN DE PASE (COCINA / BARRA LISTO)  --}}
    {{-- ========================================================================= --}}
    <div id="contenedor-toasts-pase"
         class="fixed top-4 right-4 z-50 max-w-sm w-full flex flex-col gap-2.5 pointer-events-none px-3 sm:px-0">
    </div>
</body>
</html>