<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between">

            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Servicios y precios
            </h2>

            <a
                href="{{ route('admin.servicios.create') }}"
                class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-500">
                Nuevo servicio
            </a>

        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-6">

            @if (session('success'))
                <div class="mb-6 rounded-lg border border-green-700 bg-green-950 p-4 text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @if ($servicios->isEmpty())

                <div class="rounded-xl bg-white p-10 text-center shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        No hay servicios registrados
                    </h3>

                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Registra el primer servicio y su precio.
                    </p>
                </div>

            @else

                <div class="overflow-hidden rounded-xl bg-white shadow dark:bg-zinc-900">
                    <div class="overflow-x-auto">

                        <table class="w-full text-left">

                            <thead class="bg-zinc-800 text-white">
                                <tr>
                                    <th class="p-4">Servicio</th>
                                    <th class="p-4">Descripción</th>
                                    <th class="p-4">Precio</th>
                                    <th class="p-4">Estado</th>
                                    <th class="p-4">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($servicios as $servicio)

                                    <tr class="border-b border-zinc-700 last:border-b-0">

                                        <td class="p-4 font-semibold text-gray-900 dark:text-white">
                                            {{ $servicio->nombre }}
                                        </td>

                                        <td class="max-w-md p-4 text-gray-600 dark:text-gray-300">
                                            {{ $servicio->descripcion ?: 'Sin descripción' }}
                                        </td>

                                        <td class="p-4 font-bold text-purple-500">
                                            ${{ number_format((float) $servicio->precio, 2) }}
                                        </td>

                                        <td class="p-4">
                                            @if ($servicio->activo)
                                                <span class="rounded-full bg-green-950 px-3 py-1 text-sm text-green-200">
                                                    Activo
                                                </span>
                                            @else
                                                <span class="rounded-full bg-red-950 px-3 py-1 text-sm text-red-200">
                                                    Inactivo
                                                </span>
                                            @endif
                                        </td>

                                        <td class="p-4">

                                            <div class="flex flex-wrap gap-3">

                                                <a
                                                    href="{{ route('admin.servicios.edit', $servicio) }}"
                                                    class="rounded-lg bg-zinc-700 px-4 py-2 text-white hover:bg-zinc-600"
                                                >
                                                    Editar
                                                </a>

                                                 <form
                                                    action="{{ route('admin.servicios.destroy', $servicio) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('¿Deseas eliminar este servicio?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="submit"
                                                        class="rounded-lg bg-red-700 px-4 py-2 text-white hover:bg-red-800"
                                                    >
                                                        Eliminar
                                                    </button>
                                                </form>

                                            </div>

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