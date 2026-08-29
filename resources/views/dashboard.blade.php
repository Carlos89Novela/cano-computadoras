<x-app-layout>

    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">

            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Dashboard
                </h2>

                <p class="text-sm text-gray-500">
                    Bienvenido, {{ auth()->user()->name }}
                </p>
            </div>

            <a
                href="{{ route('ordenes.create') }}"
                class="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:bg-purple-500 dark:hover:bg-purple-600 dark:focus:ring-purple-500 dark:focus:ring-offset-2"
            >
                Solicitar reparación
            </a>

        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 px-6">

            <!-- Indicadores -->
            <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">

                <!-- Equipos -->
                 <a
                    href="{{ route('equipos.index') }}"
                    class="rounded-xl border border-zinc-800 bg-white p-6 shadow transition hover:border-purple-600 dark:bg-zinc-900"
                >
                    <p class="text-sm font-medium text-gray-500">
                        Equipos registrados
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $totalEquipos }}
                    </p>

                    <p class="mt-3 text-sm text-gray-500">
                        Ver mis equipos
                    </p>
                </a>

                <!-- Reparaciones activas -->
                 <a
                    href="{{ route('ordenes.index') }}"
                    class="rounded-xl border border-zinc-800 bg-white p-6 shadow transition hover:border-purple-600 dark:bg-zinc-900"
                >
                    <p class="text-sm font-medium text-gray-500">
                        Reparaciones activas
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $reparacionesActivas }}
                    </p>

                    <p class="mt-3 text-sm text-gray-500">
                        En proceso actualmente
                    </p>
                </a>

                <!-- Reparaciones terminadas -->
                <div class="rounded-xl border border-zinc-800 bg-white p-6 shadow dark:bg-zinc-900">

                    <p class="text-sm font-medium text-gray-500">
                        Reparaciones terminadas
                    </p>

                    <p class="mt-3 text-4xl font-bold text-green-500">
                        {{ $reparacionesTerminadas }}
                    </p>

                    <p class="mt-3 text-sm text-gray-500">
                        Equipos entregados
                    </p>

                </div>

                <!-- Total -->
                <div class="rounded-xl border border-zinc-800 bg-white p-6 shadow dark:bg-zinc-900">

                    <p class="text-sm font-medium text-gray-500">
                        Historial total
                    </p>

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $totalReparaciones }}
                    </p>

                    <p class="mt-3 text-sm text-gray-500">
                        Solicitudes registradas
                    </p>

                </div>

            </section>

            <!-- Accesos rápidos -->
            <section>

                <h3 class="mb-5 text-2xl font-bold text-gray-900 dark:text-white">
                    Accesos rápidos
                </h3>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-3">

                     <a
                        href="{{ route('equipos.create') }}"
                        class="rounded-xl bg-zinc-800 p-6 transition hover:bg-zinc-700"
                    >
                        <h4 class="text-lg font-bold text-white">
                            Registrar equipo
                        </h4>

                        <p class="mt-2 text-sm text-gray-400">
                            Agrega una laptop o computadora a tu cuenta.
                        </p>
                    </a>

                     <a
                        href="{{ route('ordenes.create') }}"
                        class="rounded-xl bg-zinc-800 p-6 transition hover:bg-zinc-700"
                    >
                        <h4 class="text-lg font-bold text-white">
                            Solicitar reparación
                        </h4>

                        <p class="mt-2 text-sm text-gray-400">
                            Registra una nueva solicitud de servicio técnico.
                        </p>
                    </a>

                     <a
                        href="{{ route('ordenes.index') }}"
                        class="rounded-xl bg-zinc-800 p-6 transition hover:bg-zinc-700"
                    >
                        <h4 class="text-lg font-bold text-white">
                            Consultar reparaciones
                        </h4>

                        <p class="mt-2 text-sm text-gray-400">
                            Revisa el estado y el historial de tus equipos.
                        </p>
                    </a>

                </div>

            </section>

            <!-- Reparaciones recientes -->
            <section>

                <div class="mb-5 flex items-center justify-between">

                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Reparaciones recientes
                    </h3>

                     <a
                        href="{{ route('ordenes.index') }}"
                        class="text-sm font-semibold text-purple-500 hover:text-purple-400"
                    >
                        Ver todas
                    </a>

                </div>

                @if ($ordenesRecientes->isEmpty())

                    <div class="rounded-xl bg-white p-10 text-center shadow dark:bg-zinc-900">

                        <h4 class="text-xl font-bold text-gray-900 dark:text-white">
                            Todavía no tienes reparaciones
                        </h4>

                        <p class="mt-2 text-gray-500">
                            Registra un equipo y solicita tu primer servicio.
                        </p>

                         <a
                            href="{{ route('ordenes.create') }}"
                            class="mt-6 inline-block rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                        >
                            Solicitar reparación
                        </a>

                    </div>

                @else

                    <div class="overflow-hidden rounded-xl bg-white shadow dark:bg-zinc-900">

                        <div class="overflow-x-auto">

                            <table class="w-full text-left">

                                <thead class="bg-zinc-800 text-white">
                                    <tr>
                                        <th class="p-4">
                                            Folio
                                        </th>

                                        <th class="p-4">
                                            Equipo
                                        </th>

                                        <th class="p-4">
                                            Servicio
                                        </th>

                                        <th class="p-4">
                                            Estado
                                        </th>

                                        <th class="p-4">
                                            Fecha
                                        </th>

                                        <th class="p-4">
                                            Acción
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>

                                    @foreach ($ordenesRecientes as $orden)

                                        <tr class="border-b border-zinc-700 last:border-b-0">

                                            <td class="p-4 font-semibold text-purple-500">
                                                {{ $orden->folio }}
                                            </td>

                                            <td class="p-4 text-gray-900 dark:text-white">
                                                {{ $orden->equipo->marca }}
                                                {{ $orden->equipo->modelo }}
                                            </td>

                                            <td class="p-4 text-gray-600 dark:text-gray-300">
                                                {{ $orden->servicio?->nombre ?? 'Diagnóstico general' }}
                                            </td>

                                            <td class="p-4">
                                                <span class="inline-block rounded-full bg-purple-950 px-3 py-1 text-sm text-purple-200">
                                                    {{ $orden->estado }}
                                                </span>
                                            </td>

                                            <td class="p-4 text-gray-600 dark:text-gray-300">
                                                {{ $orden->fecha_ingreso->format('d/m/Y') }}
                                            </td>

                                            <td class="p-4">
                                                 <a
                                                    href="{{ route('ordenes.show', $orden->id) }}"
                                                    class="inline-block rounded-lg bg-zinc-700 px-4 py-2 text-white transition hover:bg-zinc-600"
                                                >
                                                    Ver
                                                </a>
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    </div>

                @endif

            </section>

            <!-- Panel administrativo -->
            @if (auth()->user()->hasRole('administrador'))

                <section class="rounded-xl border border-purple-800 bg-purple-950 p-6">

                    <div class="flex flex-col justify-between gap-5 md:flex-row md:items-center">

                        <div>
                            <h3 class="text-xl font-bold text-purple-100">
                                Panel administrativo
                            </h3>

                            <p class="mt-2 text-purple-200">
                                Administra reparaciones, diagnósticos, estados y precios.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">

                             <a
                                href="{{ route('admin.ordenes.index') }}"
                                class="rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                            >
                                Administrar reparaciones
                            </a>

                             <a
                                href="{{ route('admin.servicios.index') }}"
                                class="rounded-lg bg-zinc-800 px-5 py-3 font-semibold text-white transition hover:bg-zinc-700"
                            >
                                Servicios y precios
                            </a>

                        </div>

                    </div>

                </section>

            @endif

        </div>
    </div>

</x-app-layout>