<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                    Administración
                </p>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Panel del Administrador
                </h2>
            </div>

            @if(isset($empresa))
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-800/80 px-3.5 py-1 text-xs text-slate-300 backdrop-blur">
                    <span class="inline-block h-2 w-2 rounded-full {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'bg-amber-400' : 'bg-emerald-400' }}"></span>
                    <span class="font-semibold text-white">{{ $empresa->nombre }}</span>
                    <span class="text-slate-500">|</span>
                    <span class="capitalize text-slate-400">
                        {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'Giro Restaurante' : 'Giro Tecnología' }}
                    </span>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto w-full max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

            {{-- ============================================================= --}}
            {{-- 1. TARJETAS DE ESTADÍSTICAS DEL PANEL DE ADMINISTRACIÓN       --}}
            {{-- ============================================================= --}}
            <section class="grid w-full gap-6 sm:grid-cols-2 xl:grid-cols-4">
                {{-- Tarjeta 1: Órdenes / Comandas --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'Comandas / Pedidos' : 'Total de reparaciones' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $totalOrdenes }}
                    </p>
                </div>

                {{-- Tarjeta 2: Pendientes / Sin asignar --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'Comandas por atender' : 'Sin asignar' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-amber-500">
                        {{ $ordenesSinAsignar }}
                    </p>
                </div>

                {{-- Tarjeta 3: Servicios / Platillos del Menú --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'Platillos en menú' : 'Servicios registrados' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $totalServicios }}
                    </p>
                </div>

                {{-- Tarjeta 4: Personal / Empleados registrados --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'Personal de sucursal' : 'Usuarios registrados' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-green-500">
                        {{ $totalUsuarios }}
                    </p>
                </div>
            </section>

            {{-- ============================================================= --}}
            {{-- 2. MÓDULOS ADMINISTRATIVOS DEL GERENTE (RESTAURANTE)           --}}
            {{-- ============================================================= --}}
            @if(($giro ?? '') === 'restaurantes_alimentos' && ($esGerente ?? true) && isset($empresa))
                <section class="w-full rounded-2xl border border-slate-800 bg-zinc-900 p-6 shadow space-y-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-amber-400">
                                Gestión Gerencial de Restaurante
                            </p>
                            <h3 class="text-lg font-bold text-white">
                                Recetas, Inventarios, Compras, Caja y KDS
                            </h3>
                        </div>
                        <span class="text-xs text-slate-400">
                            Mesas ocupadas actuales: <strong class="text-emerald-400">{{ $mesasOcupadas ?? 0 }}</strong>
                        </span>
                    </div>

                    <div class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {{-- 1. Recetas (Composición y Edición) --}}
                        <a href="{{ route('restaurante.recetas.index') }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-amber-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-amber-300">
                                    📖 Recetas y Composición
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Consulta cómo están compuestas las recetas e ingresa a editar ingredientes y cantidades por platillo.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-amber-400">Administrar recetas →</span>
                        </a>

                        {{-- 2. Inventarios y Entrada de Insumos --}}
                        <a href="{{ route('inventario.existencias.index', ['empresa' => $empresa->id]) }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-emerald-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-emerald-300">
                                    📦 Inventarios de Insumos
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Revisa existencias actuales, almacenes y movimientos de insumos del restaurante.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-emerald-400">Ver existencias →</span>
                        </a>

                        {{-- 3. Registrar Entrada de Insumos --}}
                        <a href="{{ route('inventario.entradas.create', ['empresa' => $empresa->id]) }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-cyan-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-cyan-300">
                                    📥 Entrada a Inventario
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Da entrada directa a insumos o mercancía al inventario de la sucursal.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-cyan-400">Registrar entrada →</span>
                        </a>

                        {{-- 4. Órdenes de Compra --}}
                        <a href="{{ route('compras.ordenes.index') }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-purple-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-purple-300">
                                    🧾 Órdenes de Compra
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Revisa, crea y autoriza órdenes de compra a proveedores y recepciones.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-purple-400">Ver órdenes de compra →</span>
                        </a>

                        {{-- 5. Cortes y Auditoría de Caja --}}
                        <a href="{{ route('pos.cajas.index') }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-indigo-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-indigo-300">
                                    💰 Cortes y Auditoría de Caja
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Consulta turnos, retiros, ingresos y cortes de caja de cajeros y meseros.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-indigo-400">Auditar cajas →</span>
                        </a>

                        {{-- 6. Monitor KDS --}}
                        <a href="{{ route('restaurante.kds.index') }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-rose-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-rose-300">
                                    👨‍🍳 Monitor KDS de Cocina
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Supervisa comandas en preparación y despachos por estación en tiempo real.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-rose-400">Abrir KDS →</span>
                        </a>
                    </div>
                </section>
            @endif

        </div>
    </div>
</x-app-layout>
