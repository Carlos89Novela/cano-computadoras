<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                Administración
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Panel del administrador
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-6">
            <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        Total de reparaciones
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $totalOrdenes }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        Sin asignar
                    </p>

                    <p class="mt-3 text-4xl font-bold text-amber-500">
                        {{ $ordenesSinAsignar }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        Servicios registrados
                    </p>

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $totalServicios }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        Usuarios registrados
                    </p>

                    <p class="mt-3 text-4xl font-bold text-green-500">
                        {{ $totalUsuarios }}
                    </p>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
