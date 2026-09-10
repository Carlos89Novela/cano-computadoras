<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-green-500">
                Área técnica
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Panel del empleado
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-6">
            <section>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                    Bienvenido, {{ $usuario->name }}
                </h3>

                <p class="mt-2 text-gray-600 dark:text-gray-300">
                    Consulta y actualiza únicamente las reparaciones
                    que te hayan sido asignadas.
                </p>
            </section>

            <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        Reparaciones asignadas
                    </p>

                    <p class="mt-3 text-4xl font-bold text-green-500">
                        {{ $reparacionesAsignadas }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        En proceso
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $reparacionesEnProceso }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        En pruebas
                    </p>

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $reparacionesEnPruebas }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        Devueltas para corrección
                    </p>

                    <p class="mt-3 text-4xl font-bold text-red-500">
                        {{ $cierresDevueltos }}
                    </p>
                </div>
            </section>

            <section class="rounded-xl bg-white p-8 shadow dark:bg-zinc-900">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                    Mis reparaciones
                </h3>

                <p class="mt-3 text-gray-600 dark:text-gray-300">
                    La lista de reparaciones asignadas se incorporará
                    en la siguiente fase.
                </p>
            </section>
        </div>
    </div>
</x-app-layout>
