<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Mis equipos
            </h2>
            <a
                href="{{ route('equipos.create') }}"
                class="rounded-lg bg-purple-600 px-4 py-2 text-white hover:bg-purple-700">
                Registrar equipo
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

            @if ($equipos->isEmpty())
                <div class="rounded-xl bg-white p-10 text-center shadow dark:bg-zinc-900">
                    <h3 class="mb-2 text-xl font-bold text-gray-900 dark:text-white">
                        Todavía no tienes equipos registrados
                    </h3>

                    <p class="mb-6 text-gray-600 dark:text-gray-400">
                        Registra una laptop o computadora para asociarla con futuras reparaciones.
                    </p>

                     <a
                        href="{{ route('equipos.create') }}"
                        class="inline-block rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white hover:bg-purple-700"
                    >
                        Registrar mi primer equipo
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">

                    @foreach ($equipos as $equipo)
                        <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">

                            <div class="mb-4 flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-purple-600 dark:text-purple-400">
                                        {{ $equipo->tipo }}
                                    </p>

                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                        {{ $equipo->marca }} {{ $equipo->modelo }}
                                    </h3>
                                </div>
                            </div>

                            <div class="space-y-2 text-gray-600 dark:text-gray-300">
                                <p>
                                    <strong>Número de serie:</strong>
                                    {{ $equipo->numero_serie ?: 'No registrado' }}
                                </p>

                                <p>
                                    <strong>Descripción:</strong>
                                    {{ $equipo->descripcion ?: 'Sin descripción' }}
                                </p>
                            </div>

                            <div class="mt-6 flex gap-3">
                                 <a href="{{ route('equipos.edit', $equipo) }}" class="rounded-lg bg-zinc-700 px-4 py-2 text-white hover:bg-zinc-600">
                                    Editar
                                </a>

                                 <form
                                    action="{{ route('equipos.destroy', $equipo) }}"
                                    method="POST"
                                    onsubmit="return confirm('¿Deseas eliminar este equipo?');">
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

                        </div>
                    @endforeach

                </div>
            @endif

        </div>
    </div>
</x-app-layout>