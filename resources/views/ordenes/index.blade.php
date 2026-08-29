<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Mis reparaciones
            </h2>

            <a href="{{ route('ordenes.create') }}"
                class="rounded-lg bg-purple-600 px-4 py-2 text-white hover:bg-purple-700"
            >
                Solicitar reparación
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

            @if ($ordenes->isEmpty())
                <div class="rounded-xl bg-white p-10 text-center shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        No tienes reparaciones registradas
                    </h3>

                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Solicita una reparación para comenzar el seguimiento.
                    </p>

                     <a href="{{ route('ordenes.create') }}"
                        class="mt-6 inline-block rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white hover:bg-purple-700"
                    >
                        Solicitar mi primera reparación
                    </a>
                </div>
            @else
                <div class="space-y-5">
                    @foreach ($ordenes as $orden)
                        <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">

                                <div>
                                    <p class="text-sm font-semibold text-purple-500">
                                        {{ $orden->folio }}
                                    </p>

                                    <h3 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                                        {{ $orden->equipo->marca }}
                                        {{ $orden->equipo->modelo }}
                                    </h3>

                                    <p class="mt-2 text-gray-600 dark:text-gray-300">
                                        {{ $orden->problema_reportado }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-4">
                                    <span class="rounded-full bg-purple-950 px-4 py-2 text-sm text-purple-200">
                                        {{ $orden->estado }}
                                    </span>

                                     <a href="{{ route('ordenes.show', ['orden' => $orden->id]) }}"
                                        class="rounded-lg bg-zinc-700 px-4 py-2 text-white hover:bg-zinc-600"
                                    >
                                        Ver detalles
                                    </a>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-app-layout>