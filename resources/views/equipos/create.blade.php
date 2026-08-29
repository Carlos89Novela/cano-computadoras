<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Registrar equipo
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-6">

            <form
                action="{{ route('equipos.store') }}"
                method="POST"
                class="space-y-6 rounded-xl bg-white p-8 shadow dark:bg-zinc-900">
                @csrf

                <!-- Tipo de equipo -->
                <div>
                    <label
                        for="tipo"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Tipo de equipo
                    </label>

                    <select
                        id="tipo"
                        name="tipo"
                        required
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >
                        <option value="">
                            Selecciona una opción
                        </option>

                        <option
                            value="Laptop"
                            @selected(old('tipo') === 'Laptop')
                        >
                            Laptop
                        </option>

                        <option
                            value="Computadora de escritorio"
                            @selected(old('tipo') === 'Computadora de escritorio')
                        >
                            Computadora de escritorio
                        </option>

                        <option
                            value="Todo en uno"
                            @selected(old('tipo') === 'Todo en uno')
                        >
                            Todo en uno
                        </option>

                        <option
                            value="Otro"
                            @selected(old('tipo') === 'Otro')
                        >
                            Otro
                        </option>
                    </select>

                    @error('tipo')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Marca -->
                <div>
                    <label
                        for="marca"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Marca
                    </label>

                    <input
                        id="marca"
                        name="marca"
                        type="text"
                        value="{{ old('marca') }}"
                        required
                        maxlength="100"
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        placeholder="Ejemplo: HP"
                    >

                    @error('marca')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Modelo -->
                <div>
                    <label
                        for="modelo"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Modelo
                    </label>

                    <input
                        id="modelo"
                        name="modelo"
                        type="text"
                        value="{{ old('modelo') }}"
                        required
                        maxlength="100"
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        placeholder="Ejemplo: Pavilion 15"
                    >

                    @error('modelo')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Número de serie -->
                <div>
                    <label
                        for="numero_serie"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Número de serie
                    </label>

                    <input
                        id="numero_serie"
                        name="numero_serie"
                        type="text"
                        value="{{ old('numero_serie') }}"
                        maxlength="150"
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        placeholder="Opcional"
                    >

                    @error('numero_serie')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Descripción -->
                <div>
                    <label
                        for="descripcion"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Descripción
                    </label>

                    <textarea
                        id="descripcion"
                        name="descripcion"
                        rows="4"
                        maxlength="1000"
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        placeholder="Color, características o detalles del equipo"
                    >{{ old('descripcion') }}</textarea>

                    @error('descripcion')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Botones -->
                <div class="flex flex-wrap items-center gap-4">

                    <button
                        type="submit"
                        class="rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                    >
                        Guardar equipo
                    </button>

                     <a
                        href="{{ route('equipos.index') }}"
                        class="rounded-lg bg-zinc-700 px-5 py-3 font-semibold text-white transition hover:bg-zinc-600"
                    >
                        Cancelar
                    </a>

                </div>

            </form>

        </div>
    </div>

</x-app-layout>