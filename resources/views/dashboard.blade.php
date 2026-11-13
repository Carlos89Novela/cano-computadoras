<x-app-layout>
    @php
        /** @var \App\Models\User $usuario */
        $usuario = auth()->user();

        $sucursalUser = $usuario->sucursales()->with('empresa')->where('sucursales.activo', true)->first();
        /** @var \App\Models\Empresa|null $empresaActual */
        $empresaActual = $sucursalUser?->empresa ?? $usuario->empresa ?? \App\Models\Empresa::query()->where('activo', true)->first();

        $esRestaurante = $empresaActual && (
            (method_exists($empresaActual, 'esRestaurante') && $empresaActual->esRestaurante())
            || in_array($empresaActual->giro_comercial, ['restaurantes_alimentos', 'restaurante_cafeteria'], true)
        );

        $comandasPendientes = 0;
        $comandasEnPreparacion = 0;
        $comandasCompletadasHoy = 0;

        if ($esRestaurante) {
            $comandasPendientes = \App\Models\Comanda::query()
                ->where('empresa_id', $empresaActual->id)
                ->where('estado', 'pendiente')
                ->count();

            $comandasEnPreparacion = \App\Models\Comanda::query()
                ->where('empresa_id', $empresaActual->id)
                ->where('estado', 'en_preparacion')
                ->count();

            $comandasCompletadasHoy = \App\Models\Comanda::query()
                ->where('empresa_id', $empresaActual->id)
                ->where('estado', 'completada')
                ->whereDate('updated_at', today())
                ->count();
        }
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-white flex items-center gap-2">
                    @if ($esRestaurante)
                        <span>🍽️ Panel Operativo: {{ $empresaActual->nombre }}</span>
                    @else
                        <span>⚡ Dashboard</span>
                    @endif
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    Bienvenido, <strong class="text-zinc-200">{{ $usuario->name }}</strong>
                    @if ($sucursalUser)
                        | Sucursal: <span class="text-emerald-400 font-semibold">{{ $sucursalUser->nombre }} ({{ $sucursalUser->codigo }})</span>
                    @endif
                </p>
            </div>

            @if ($esRestaurante)
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        ● Servicio en Vivo
                    </span>
                    <a href="{{ route('restaurante.comandero.index') }}"
                       class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-500 transition">
                        + Nueva Comanda
                    </a>
                </div>
            @elseif ($usuario->hasRole('cliente'))
                <a href="{{ route('ordenes.create') }}"
                   class="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">
                    Solicitar reparación
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

            {{-- ========================================================= --}}
            {{-- DASHBOARD DE RESTAURANTE                                --}}
            {{-- ========================================================= --}}
            @if ($esRestaurante)
                <!-- Indicadores de Cocina y Servicio -->
                <section class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5 shadow">
                        <p class="text-xs font-medium text-zinc-400">Comandas Pendientes</p>
                        <p class="mt-2 text-3xl font-black text-rose-400">{{ $comandasPendientes }}</p>
                        <p class="mt-2 text-[11px] text-zinc-500">Por iniciar en cocina</p>
                    </div>

                    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5 shadow">
                        <p class="text-xs font-medium text-zinc-400">En Preparación</p>
                        <p class="mt-2 text-3xl font-black text-amber-400">{{ $comandasEnPreparacion }}</p>
                        <p class="mt-2 text-[11px] text-zinc-500">En fuegos o barra</p>
                    </div>

                    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5 shadow">
                        <p class="text-xs font-medium text-zinc-400">Despachadas Hoy</p>
                        <p class="mt-2 text-3xl font-black text-emerald-400">{{ $comandasCompletadasHoy }}</p>
                        <p class="mt-2 text-[11px] text-zinc-500">Órdenes servidas</p>
                    </div>

                    <div class="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5 shadow">
                        <p class="text-xs font-medium text-zinc-400">Estaciones Activas</p>
                        <p class="mt-2 text-3xl font-black text-indigo-400">Cocina / Barra</p>
                        <p class="mt-2 text-[11px] text-zinc-500">Monitores KDS conectados</p>
                    </div>
                </section>

                <!-- Accesos Rápidos de Restaurante -->
                <section class="space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-300">Módulos de Operación</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="{{ route('restaurante.comandero.index') }}"
                           class="group p-5 bg-zinc-900 border border-zinc-800 hover:border-indigo-500/50 rounded-2xl transition flex flex-col justify-between shadow">
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">📱</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">Mesas & Salón</span>
                            </div>
                            <div class="mt-4">
                                <h3 class="text-sm font-bold text-white group-hover:text-indigo-400 transition">Comandero Digital</h3>
                                <p class="text-xs text-zinc-400 mt-1">Tomar pedidos de comensales y enviar directo a cocina.</p>
                            </div>
                        </a>

                        <a href="{{ route('restaurante.kds.index') }}"
                           class="group p-5 bg-zinc-900 border border-zinc-800 hover:border-amber-500/50 rounded-2xl transition flex flex-col justify-between shadow">
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">👨‍🍳</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">Cocina & Barra</span>
                            </div>
                            <div class="mt-4">
                                <h3 class="text-sm font-bold text-white group-hover:text-amber-400 transition">Pantalla de Cocina (KDS)</h3>
                                <p class="text-xs text-zinc-400 mt-1">Cronómetro en vivo, semáforo de alerta y despacho de platillos.</p>
                            </div>
                        </a>

                        <a href="{{ url('/restaurante/mesas/plano') }}"
                           class="group p-5 bg-zinc-900 border border-zinc-800 hover:border-cyan-500/50 rounded-2xl transition flex flex-col justify-between shadow">
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">🪑</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">Distribución</span>
                            </div>
                            <div class="mt-4">
                                <h3 class="text-sm font-bold text-white group-hover:text-cyan-400 transition">Plano de Mesas</h3>
                                <p class="text-xs text-zinc-400 mt-1">Mapa gráfico de salón, ocupación en tiempo real y drag & drop.</p>
                            </div>
                        </a>

                        <a href="{{ route('pos.index') }}"
                           class="group p-5 bg-zinc-900 border border-zinc-800 hover:border-emerald-500/50 rounded-2xl transition flex flex-col justify-between shadow">
                            <div class="flex items-center justify-between">
                                <span class="text-2xl">💳</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Caja</span>
                            </div>
                            <div class="mt-4">
                                <h3 class="text-sm font-bold text-white group-hover:text-emerald-400 transition">Cierre de Cuenta & POS</h3>
                                <p class="text-xs text-zinc-400 mt-1">Precuenta térmica, cobro de mesas, propinas y emisión de CFDI.</p>
                            </div>
                        </a>
                    </div>
                </section>

                <!-- Panel Gerencial / Catálogo y Escandallos -->
                @if ($usuario->hasRole(['administrador', 'supervisor']))
                    <section class="p-6 bg-zinc-900/60 border border-zinc-800 rounded-2xl space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-purple-400">Ingeniería de Menú & Almacén</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <a href="{{ route('productos.index', ['empresa' => $empresaActual->id]) }}"
                               class="p-4 bg-zinc-950 border border-zinc-800 hover:border-zinc-700 rounded-xl flex items-center gap-3 transition">
                                <span class="text-2xl">🍳</span>
                                <div>
                                    <h4 class="text-xs font-bold text-white">Fichas Técnicas & Recetas (BOM)</h4>
                                    <p class="text-[11px] text-zinc-400">Escandallos, costeo por porción y mermas.</p>
                                </div>
                            </a>

                            <a href="{{ route('productos.categorias.index', ['empresa' => $empresaActual->id]) }}"
                               class="p-4 bg-zinc-950 border border-zinc-800 hover:border-zinc-700 rounded-xl flex items-center gap-3 transition">
                                <span class="text-2xl">📂</span>
                                <div>
                                    <h4 class="text-xs font-bold text-white">Categorías de Alimentos</h4>
                                    <p class="text-[11px] text-zinc-400">Platillos, bebidas, entradas y postres.</p>
                                </div>
                            </a>

                            <a href="{{ route('inventario.existencias.index', $empresaActual) }}"
                               class="p-4 bg-zinc-950 border border-zinc-800 hover:border-zinc-700 rounded-xl flex items-center gap-3 transition">
                                <span class="text-2xl">📦</span>
                                <div>
                                    <h4 class="text-xs font-bold text-white">Stock y Kardex de Insumos</h4>
                                    <p class="text-[11px] text-zinc-400">Existencias de carnes, panadería y abarrotes.</p>
                                </div>
                            </a>
                        </div>
                    </section>
                @endif

            {{-- ========================================================= --}}
            {{-- DASHBOARD DE TALLER DE CÓMPUTO                            --}}
            {{-- ========================================================= --}}
            @else
                <!-- Indicadores de Reparaciones -->
                <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <a href="{{ route('equipos.index') }}" class="rounded-xl border border-zinc-800 bg-white p-6 shadow transition hover:border-purple-600 dark:bg-zinc-900">
                        <p class="text-sm font-medium text-gray-500">Equipos registrados</p>
                        <p class="mt-3 text-4xl font-bold text-purple-500">{{ $totalEquipos ?? 0 }}</p>
                        <p class="mt-3 text-sm text-gray-500">Ver mis equipos</p>
                    </a>

                    <a href="{{ route('ordenes.index') }}" class="rounded-xl border border-zinc-800 bg-white p-6 shadow transition hover:border-purple-600 dark:bg-zinc-900">
                        <p class="text-sm font-medium text-gray-500">Reparaciones activas</p>
                        <p class="mt-3 text-4xl font-bold text-purple-500">{{ $reparacionesActivas ?? 0 }}</p>
                        <p class="mt-3 text-sm text-gray-500">En proceso actualmente</p>
                    </a>

                    <div class="rounded-xl border border-zinc-800 bg-white p-6 shadow dark:bg-zinc-900">
                        <p class="text-sm font-medium text-gray-500">Reparaciones terminadas</p>
                        <p class="mt-3 text-4xl font-bold text-green-500">{{ $reparacionesTerminadas ?? 0 }}</p>
                        <p class="mt-3 text-sm text-gray-500">Equipos entregados</p>
                    </div>

                    <div class="rounded-xl border border-zinc-800 bg-white p-6 shadow dark:bg-zinc-900">
                        <p class="text-sm font-medium text-gray-500">Historial total</p>
                        <p class="mt-3 text-4xl font-bold text-blue-500">{{ $totalReparaciones ?? 0 }}</p>
                        <p class="mt-3 text-sm text-gray-500">Solicitudes registradas</p>
                    </div>
                </section>

                @if (auth()->user()->hasRole('administrador'))
                    <section class="rounded-xl border border-purple-800 bg-purple-950 p-6">
                        <div class="flex flex-col justify-between gap-5 md:flex-row md:items-center">
                            <div>
                                <h3 class="text-xl font-bold text-purple-100">Panel administrativo</h3>
                                <p class="mt-2 text-purple-200">Administra reparaciones, diagnósticos, estados y precios.</p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('admin.ordenes.index') }}" class="rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700">
                                    Administrar reparaciones
                                </a>
                                <a href="{{ route('admin.servicios.index') }}" class="rounded-lg bg-zinc-800 px-5 py-3 font-semibold text-white transition hover:bg-zinc-700">
                                    Servicios y precios
                                </a>
                            </div>
                        </div>
                    </section>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>
