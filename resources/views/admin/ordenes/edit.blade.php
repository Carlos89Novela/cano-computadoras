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

            <div class="mb-6 rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-purple-300">
                            Resumen de la reparación
                        </p>
                        <h3 class="mt-2 text-2xl font-bold text-white">
                            {{ $orden->folio }}
                        </h3>
                    </div>

                    <span class="inline-flex rounded-full border border-purple-500 bg-purple-600/20 px-4 py-2 text-sm font-semibold text-purple-100">
                        {{ $orden->estado }}
                    </span>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Cliente</p>
                        <p class="mt-2 text-sm font-semibold text-white">{{ $orden->user->name }}</p>
                    </div>

                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Equipo</p>
                        <p class="mt-2 text-sm font-semibold text-white">
                            {{ $orden->equipo->marca }} {{ $orden->equipo->modelo }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Costo estimado</p>
                        <p class="mt-2 text-sm font-semibold text-white">
                            @if ($orden->costo_estimado !== null)
                                ${{ number_format((float) $orden->costo_estimado, 2) }}
                            @else
                                Pendiente
                            @endif
                        </p>
                    </div>

                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Costo final</p>
                        <p class="mt-2 text-sm font-semibold text-white">
                            @if ($orden->costo_final !== null)
                                ${{ number_format((float) $orden->costo_final, 2) }}
                            @else
                                Pendiente
                            @endif
                        </p>
                    </div>
                </div>
            </div>

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

            <section class="mt-8 rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-400">
                            Actividad
                        </p>
                        <h3 class="mt-2 text-xl font-bold text-white">
                            Historial de trabajo
                        </h3>
                    </div>
                </div>

                @if ($orden->historial->isEmpty())
                    <div class="rounded-xl border border-dashed border-zinc-700 bg-zinc-900 p-5 text-sm text-zinc-300">
                        Aún no hay cambios registrados en esta reparación.
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($orden->historial->sortByDesc('created_at') as $registro)
                            <div class="flex gap-4 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                                <div class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-purple-600/20 text-purple-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </div>

                                <div class="flex-1">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="font-semibold text-white">
                                            {{ $registro->estado ?? 'Actualización' }}
                                        </p>
                                        <span class="text-xs text-zinc-400">
                                            {{ $registro->created_at?->format('d/m/Y H:i') ?? 'Fecha no disponible' }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-zinc-300">
                                        {{ $registro->comentarios ?? 'Sin comentarios adicionales.' }}
                                    </p>

                                    <p class="mt-2 text-xs text-zinc-400">
                                        Por {{ $registro->usuario?->name ?? 'Administrador' }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

        </div>
    </div>

</x-app-layout>