<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Editar equipo
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-6">

                <form
                     action="{{ route('equipos.update', $equipo) }}"
                method="POST"
                class="space-y-6 rounded-xl bg-white p-8 shadow dark:bg-zinc-900"
            >
                @csrf
                @method('PUT')

                <div>
                    <label for="tipo" class="mb-2 block font-medium dark:text-gray-200">
                        Tipo de equipo
                    </label>

                    <select
                        id="tipo"
                        name="tipo"
                        required
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >
                        @foreach (['Laptop', 'Computadora de escritorio', 'Todo en uno', 'Otro'] as $tipo)
                            <option
                                value="{{ $tipo }}"
                                @selected(old('tipo', $equipo->tipo) === $tipo)
                            >
                                {{ $tipo }}
                            </option>
                        @endforeach
                    </select>

                    @error('tipo')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="marca" class="mb-2 block font-medium dark:text-gray-200">
                        Marca
                    </label>

                    <input
                        id="marca"
                        name="marca"
                        type="text"
                        value="{{ old('marca', $equipo->marca) }}"
                        required
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >

                    @error('marca')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="modelo" class="mb-2 block font-medium dark:text-gray-200">
                        Modelo
                    </label>

                    <input
                        id="modelo"
                        name="modelo"
                        type="text"
                        value="{{ old('modelo', $equipo->modelo) }}"
                        required
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >

                    @error('modelo')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="numero_serie" class="mb-2 block font-medium dark:text-gray-200">
                        Número de serie
                    </label>

                    <input
                        id="numero_serie"
                        name="numero_serie"
                        type="text"
                        value="{{ old('numero_serie', $equipo->numero_serie) }}"
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >

                    @error('numero_serie')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="descripcion" class="mb-2 block font-medium dark:text-gray-200">
                        Descripción
                    </label>

                    <textarea
                        id="descripcion"
                        name="descripcion"
                        rows="4"
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >{{ old('descripcion', $equipo->descripcion) }}</textarea>

                    @error('descripcion')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-4">
                    <button
                        type="submit"
                        class="rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white hover:bg-purple-700"
                    >
                        Guardar cambios
                    </button>

                          <a
                                href="{{ route('equipos.index') }}"
                        class="rounded-lg bg-zinc-700 px-5 py-3 text-white hover:bg-zinc-600"
                    >
                        Cancelar
                    </a>
                </div>

            </form>

        </div>
    </div>
</x-app-layout>
