<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-500">
                Supervisión operativa
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Panel del supervisor
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

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $totalOrdenes }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        Reparaciones activas
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $ordenesActivas }}
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
                        Cierres pendientes
                    </p>

                    <p class="mt-3 text-4xl font-bold text-red-500">
                        {{ $cierresPendientes }}
                    </p>
                </div>
            </section>

            <section class="rounded-xl bg-white p-8 shadow dark:bg-zinc-900">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                    Tareas de supervisión
                </h3>

                <p class="mt-3 text-gray-600 dark:text-gray-300">
                    Aquí aparecerán las reparaciones sin asignar,
                    las cotizaciones pendientes y las solicitudes de cierre.
                </p>
            </section>
        </div>
    </div>
</x-app-layout>
