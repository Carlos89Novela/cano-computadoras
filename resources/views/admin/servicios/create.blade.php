<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Registrar servicio
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-6">

             <form
                action="{{ route('admin.servicios.store') }}"
                method="POST"
                class="space-y-6 rounded-xl bg-white p-8 shadow dark:bg-zinc-900"
            >
                @csrf

                <div>
                    <label
                        for="nombre"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Nombre del servicio
                    </label>

                    <input
                        id="nombre"
                        name="nombre"
                        type="text"
                        required
                        maxlength="150"
                        value="{{ old('nombre') }}"
                        placeholder="Ejemplo: Formateo de Windows"
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >

                    @error('nombre')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

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
                        rows="5"
                        maxlength="2000"
                        placeholder="Describe lo que incluye el servicio."
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >{{ old('descripcion') }}</textarea>

                    @error('descripcion')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="precio"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Precio
                    </label>

                    <input
                        id="precio"
                        name="precio"
                        type="number"
                        required
                        min="0"
                        max="99999999.99"
                        step="0.01"
                        value="{{ old('precio') }}"
                        placeholder="0.00"
                        class="w-full rounded-lg border-gray-300 bg-white text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >

                    @error('precio')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <label class="flex items-center gap-3">
                    <input
                        name="activo"
                        type="checkbox"
                        value="1"
                        @checked(old('activo', true))
                        class="rounded border-zinc-600 bg-zinc-800 text-purple-600 focus:ring-purple-500"
                    >

                    <span class="text-gray-700 dark:text-gray-200">
                        Mostrar este servicio en la página pública
                    </span>
                </label>

                <div class="flex flex-wrap gap-4">

                    <button
                        type="submit"
                        class="rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                    >
                        Guardar servicio
                    </button>

                     <a
                        href="{{ route('admin.servicios.index') }}"
                        class="rounded-lg bg-zinc-700 px-5 py-3 font-semibold text-white transition hover:bg-zinc-600"
                    >
                        Cancelar
                    </a>

                </div>

            </form>

        </div>
    </div>

</x-app-layout>