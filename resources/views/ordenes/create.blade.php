<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Solicitar reparación
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-6">

             <form
                action="{{ route('ordenes.store') }}"
                method="POST"
                class="space-y-6 rounded-xl bg-white p-8 shadow dark:bg-zinc-900"
            >
                @csrf

                <div>
                    <label
                        for="equipo_id"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Equipo
                    </label>

                    <select
                        id="equipo_id"
                        name="equipo_id"
                        required
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >
                        <option value="">
                            Selecciona un equipo
                        </option>

                        @foreach ($equipos as $equipo)
                            <option
                                value="{{ $equipo->id }}"
                                @selected(old('equipo_id') == $equipo->id)
                            >
                                {{ $equipo->tipo }}
                                |
                                {{ $equipo->marca }}
                                {{ $equipo->modelo }}
                            </option>
                        @endforeach
                    </select>

                    @error('equipo_id')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="servicio_id"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200">
                        Servicio solicitado
                    </label>

                    <select
                        id="servicio_id"
                        name="servicio_id"
                        class="w-full rounded-lg border-gray-300 bg-white text-black
                            dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        <option value="">
                            Diagnóstico general o servicio por definir
                        </option>

                        @foreach ($servicios as $servicio)
                            <option
                                value="{{ $servicio->id }}"
                                @selected(old('servicio_id') == $servicio->id)>
                                {{ $servicio->nombre }}
                                - ${{ number_format((float) $servicio->precio, 2) }}
                            </option>
                        @endforeach
                    </select>

                    <p class="mt-2 text-sm text-gray-500">
                        El precio mostrado es informativo. El costo final dependerá del diagnóstico.
                    </p>

                    @error('servicio_id')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="problema_reportado"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Describe el problema
                    </label>

                    <textarea
                        id="problema_reportado"
                        name="problema_reportado"
                        rows="6"
                        required
                        maxlength="2000"
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        placeholder="Describe qué falla presenta el equipo, desde cuándo ocurre y cualquier detalle importante."
                    >{{ old('problema_reportado') }}</textarea>

                    @error('problema_reportado')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <button
                        type="submit"
                        class="rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white hover:bg-purple-700"
                    >
                        Registrar solicitud
                    </button>

                     <a
                        href="{{ route('ordenes.index') }}"
                        class="rounded-lg bg-zinc-700 px-5 py-3 font-semibold text-white hover:bg-zinc-600"
                    >
                        Cancelar
                    </a>
                </div>

            </form>

        </div>
    </div>
</x-app-layout>