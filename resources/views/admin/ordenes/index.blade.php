<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Administrar reparaciones
            </h2>

            <span class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white">
                {{ $ordenes->count() }} órdenes
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-6">

            @if (session('success'))
                <div class="mb-6 rounded-lg border border-green-700 bg-green-950 p-4 text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @if ($ordenes->isEmpty())
                <div class="rounded-xl bg-white p-10 text-center shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        No existen órdenes de reparación
                    </h3>

                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Las solicitudes realizadas por los clientes aparecerán aquí.
                    </p>
                </div>
            @else
                <div class="overflow-hidden rounded-xl bg-white shadow dark:bg-zinc-900">
                    <div class="overflow-x-auto">

                        <table class="w-full text-left">
                            <thead class="bg-zinc-800 text-white">
                                <tr>
                                    <th class="p-4">Folio</th>
                                    <th class="p-4">Cliente</th>
                                    <th class="p-4">Equipo</th>
                                    <th class="p-4">Estado</th>
                                    <th class="p-4">Fecha</th>
                                    <th class="p-4">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($ordenes as $orden)
                                    <tr class="border-b border-zinc-700 last:border-b-0">

                                        <td class="p-4 font-semibold text-purple-500">
                                            {{ $orden->folio }}
                                        </td>

                                        <td class="p-4 text-gray-900 dark:text-white">
                                            {{ $orden->user->name }}
                                        </td>

                                        <td class="p-4 text-gray-900 dark:text-white">
                                            <p class="font-semibold">
                                                {{ $orden->equipo->marca }}
                                                {{ $orden->equipo->modelo }}
                                            </p>

                                            <p class="text-sm text-gray-500">
                                                {{ $orden->equipo->tipo }}
                                            </p>
                                        </td>

                                        <td class="p-4">
                                            <span class="inline-block rounded-full bg-purple-950 px-3 py-1 text-sm text-purple-200">
                                                {{ $orden->estado }}
                                            </span>
                                        </td>

                                        <td class="p-4 text-gray-900 dark:text-white">
                                            {{ $orden->fecha_ingreso->format('d/m/Y') }}
                                        </td>

                                        <td class="p-4">
                                            <a
                                                href="{{ route('admin.ordenes.edit', ['orden' => $orden->id]) }}"
                                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                                Administrar
                                            </a> 
                                        </td>

                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
            @endif

        </div>
    </div>

</x-app-layout>