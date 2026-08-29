<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Reparación {{ $orden->folio }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl px-6">

            @if (session('success'))
                <div class="mb-6 rounded-lg border border-green-700 bg-green-950 p-4 text-green-200">
                    {{ session('success') }}
                </div>
            @endif

             <form
                action="{{ route('admin.ordenes.update', ['orden' => $orden->id]) }}"
                method="POST"
                class="space-y-6 rounded-xl bg-white p-8 shadow dark:bg-zinc-900"
            >
                @csrf
                @method('PUT')

                <div class="grid gap-6 md:grid-cols-2">

                    <div>
                        <p class="text-sm text-gray-500">
                            Cliente
                        </p>

                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ $orden->user->name }}
                        </p>

                        <p class="text-sm text-gray-500">
                            {{ $orden->user->email }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">
                            Equipo
                        </p>

                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ $orden->equipo->marca }}
                            {{ $orden->equipo->modelo }}
                        </p>

                        <p class="text-sm text-gray-500">
                            {{ $orden->equipo->tipo }}
                        </p>
                    </div>

                </div>

                <div>
                    <p class="mb-2 text-sm text-gray-500">
                        Problema reportado
                    </p>

                    <div class="rounded-lg bg-zinc-800 p-4 text-white">
                        {{ $orden->problema_reportado }}
                    </div>
                </div>

                <div>
                    <label
                        for="estado"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Estado de la reparación
                    </label>

                    <select
                        id="estado"
                        name="estado"
                        required
                        class="w-full rounded-lg border-zinc-700 bg-zinc-800 text-white"
                    >
                        @foreach ($estados as $estado)
                            <option
                                value="{{ $estado }}"
                                @selected(old('estado', $orden->estado) === $estado)
                            >
                                {{ $estado }}
                            </option>
                        @endforeach
                    </select>

                    @error('estado')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="diagnostico"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Diagnóstico
                    </label>

                    <textarea
                        id="diagnostico"
                        name="diagnostico"
                        rows="5"
                        maxlength="3000"
                        class="w-full rounded-lg border-zinc-700 bg-zinc-800 text-white"
                        placeholder="Describe el diagnóstico técnico."
                    >{{ old('diagnostico', $orden->diagnostico) }}</textarea>

                    @error('diagnostico')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="grid gap-6 md:grid-cols-2">

                    <div>
                        <label
                            for="costo_estimado"
                            class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                        >
                            Costo estimado
                        </label>

                        <input
                            id="costo_estimado"
                            name="costo_estimado"
                            type="number"
                            min="0"
                            step="0.01"
                            value="{{ old('costo_estimado', $orden->costo_estimado) }}"
                            class="w-full rounded-lg border-zinc-700 bg-zinc-800 text-white"
                            placeholder="0.00"
                        >

                        @error('costo_estimado')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="costo_final"
                            class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                        >
                            Costo final
                        </label>

                        <input
                            id="costo_final"
                            name="costo_final"
                            type="number"
                            min="0"
                            step="0.01"
                            value="{{ old('costo_final', $orden->costo_final) }}"
                            class="w-full rounded-lg border-zinc-700 bg-zinc-800 text-white"
                            placeholder="0.00"
                        >

                        @error('costo_final')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                </div>

                <div>
                    <label
                        for="comentario"
                        class="mb-2 block font-medium text-gray-700 dark:text-gray-200"
                    >
                        Comentario del avance
                    </label>

                    <textarea
                        id="comentario"
                        name="comentario"
                        rows="3"
                        maxlength="2000"
                        class="w-full rounded-lg border-zinc-700 bg-zinc-800 text-white"
                        placeholder="Este comentario aparecerá en el historial."
                    >{{ old('comentario') }}</textarea>

                    @error('comentario')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-4">

                    <button
                        type="submit"
                        class="rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                    >
                        Guardar cambios
                    </button>

                    <a
                        href="{{ route('admin.ordenes.index') }}"
                        class="inline-block rounded-lg bg-zinc-700 px-5 py-3 font-semibold text-white transition hover:bg-zinc-600"
                    >
                        Cancelar
                    </a>

                    <a
                        href="{{ route('admin.ordenes.pdf', ['orden' => $orden->id]) }}"
                        target="_blank"
                        class="inline-block rounded-lg bg-zinc-700 px-5 py-3 font-semibold text-white transition hover:bg-zinc-600"
                    >
                        Descargar comprobante PDF
                    </a>

                </div>

            </form>

        </div>
    </div>

</x-app-layout>