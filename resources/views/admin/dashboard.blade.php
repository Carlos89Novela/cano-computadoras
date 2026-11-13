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

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-6">
            <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                {{-- Tarjeta 1: Órdenes / Comandas --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'Comandas / Pedidos' : 'Total de reparaciones' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $totalOrdenes }}
                    </p>
                </div>

                {{-- Tarjeta 2: Pendientes / Sin asignar --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'Comandas por atender' : 'Sin asignar' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-amber-500">
                        {{ $ordenesSinAsignar }}
                    </p>
                </div>

                {{-- Tarjeta 3: Servicios / Platillos del Menú --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'Platillos en menú' : 'Servicios registrados' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $totalServicios }}
                    </p>
                </div>

                {{-- Tarjeta 4: Personal / Empleados registrados --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        {{ ($giro ?? '') === 'restaurantes_alimentos' ? 'Personal de sucursal' : 'Usuarios registrados' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-green-500">
                        {{ $totalUsuarios }}
                    </p>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>