<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            Reparación {{ $orden->folio }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl px-6">

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

                    @include('admin.ordenes.partials.estado-badge', [
                        'estado' => $orden->estado,
                    ])
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Cliente</p>
                        <p class="mt-2 text-sm font-semibold text-white">
                            {{ $orden->user?->name ?? 'Cliente no disponible' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Equipo</p>
                        <p class="mt-2 text-sm font-semibold text-white">
                            {{ trim(
                                ($orden->equipo?->marca ?? '')
                                .' '
                                .($orden->equipo?->modelo ?? '')
                            ) ?: 'Equipo no disponible' }}
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
                            {{ $orden->user?->name ?? 'Cliente no disponible' }}
                        </p>

                        <p class="text-sm text-gray-500">
                            {{ $orden->user?->email ?? 'Correo no disponible' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">
                            Equipo
                        </p>

                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ trim(
                                ($orden->equipo?->marca ?? '')
                                .' '
                                .($orden->equipo?->modelo ?? '')
                            ) ?: 'Equipo no disponible' }}
                        </p>

                        <p class="text-sm text-gray-500">
                            {{ $orden->equipo?->tipo ?? 'Tipo no disponible' }}
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
                        aria-describedby="estado-error"
                        aria-invalid="{{ $errors->has('estado') ? 'true' : 'false' }}"
                        @class([
                            'admin-form-control',
                            'admin-form-control--error' => $errors->has('estado'),
                        ])
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
                        <p
                            id="estado-error"
                            class="mt-2 text-sm text-red-500"
                            role="alert"
                        >
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
                        aria-describedby="diagnostico-ayuda diagnostico-error"
                        aria-invalid="{{ $errors->has('diagnostico') ? 'true' : 'false' }}"
                        @class([
                            'admin-form-control',
                            'admin-form-control--error' => $errors->has('diagnostico'),
                        ])
                        placeholder="Describe el diagnóstico técnico."
                    >{{ old('diagnostico', $orden->diagnostico) }}</textarea>

                    <p
                        id="diagnostico-ayuda"
                        class="mt-2 text-xs text-gray-500 dark:text-zinc-400"
                    >
                        Máximo 3000 caracteres.
                    </p>

                    @error('diagnostico')
                        <p
                            id="diagnostico-error"
                            class="mt-2 text-sm text-red-500"
                            role="alert"
                        >
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
                            inputmode="decimal"
                            min="0"
                            max="999999.99"
                            step="0.01"
                            value="{{ old('costo_estimado', $orden->costo_estimado) }}"
                            aria-describedby="costo-estimado-ayuda costo-estimado-error"
                            aria-invalid="{{ $errors->has('costo_estimado') ? 'true' : 'false' }}"
                            @class(['admin-form-control', 'admin-form-control--error' => $errors->has('costo_estimado')])
                            placeholder="0.00"
                        >
                        <p
                            id="costo-estimado-ayuda"
                            class="mt-2 text-xs text-gray-500 dark:text-zinc-400"
                        >
                            Ingresa el importe sin símbolos, por ejemplo: 1250.00.
                        </p>

                        @error('costo_estimado')
                            <p
                                id="costo-estimado-error"
                                class="mt-2 text-sm text-red-500"
                                role="alert"
                            >
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
                            inputmode="decimal"
                            min="0"
                            max="999999.99"
                            step="0.01"
                            value="{{ old('costo_final', $orden->costo_final) }}"
                            aria-describedby="costo-final-ayuda costo-final-error"
                            aria-invalid="{{ $errors->has('costo_final') ? 'true' : 'false' }}"
                            @class(['admin-form-control', 'admin-form-control--error' => $errors->has('costo_final')])
                            placeholder="0.00"
                        >

                        <p
                            id="costo-final-ayuda"
                            class="mt-2 text-xs text-gray-500 dark:text-zinc-400"
                        >
                            Ingresa el importe sin símbolos, por ejemplo: 1250.00.
                        </p>

                        @error('costo_final')
                            <p
                                id="costo-final-error"
                                class="mt-2 text-sm text-red-500"
                                role="alert"
                            >
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
                        aria-describedby="comentario-ayuda comentario-error"
                        aria-invalid="{{ $errors->has('comentario') ? 'true' : 'false' }}"
                        @class([
                            'admin-form-control',
                            'admin-form-control--error' => $errors->has('comentario'),
                        ])
                        placeholder="Este comentario aparecerá en el historial."
                    >{{ old('comentario') }}</textarea>
                    <p
                        id="comentario-ayuda"
                        class="mt-2 text-xs text-gray-500 dark:text-zinc-400"
                    >
                        Opcional. Se registrará en el historial de la reparación.
                        Máximo 2000 caracteres.
                    </p>

                    @error('comentario')
                        <p
                            id="comentario-error"
                            class="mt-2 text-sm text-red-500"
                            role="alert"
                        >
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-4">

                    <button
                        type="submit"
                        class="ion-btn ion-btn--primary"
                    >
                        Guardar cambios
                    </button>

                    <a
                        href="{{ route('admin.ordenes.index') }}"
                        class="ion-btn ion-btn--secondary"
                    >
                        Cancelar
                    </a>

                    <a
                        href="{{ route('admin.ordenes.pdf', ['orden' => $orden->id]) }}"
                        target="_blank" rel="noopener noreferrer"
                        class="ion-btn ion-btn--secondary"
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
                        @foreach ($orden->historial as $registro)
                            <div class="flex gap-4 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                                <div class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-purple-600/20 text-purple-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </div>

                                <div class="flex-1">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        @if ($registro->estado)
                                            @include('admin.ordenes.partials.estado-badge', [
                                                'estado' => $registro->estado,
                                            ])
                                        @else
                                            <span class="text-sm font-semibold text-zinc-200">
                                                Actualización
                                            </span>
                                        @endif
                                        <span class="text-xs text-zinc-400">
                                            {{ $registro->created_at?->format('d/m/Y H:i') ?? 'Fecha no disponible' }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-zinc-300">
                                        {{ $registro->comentarios ?? 'Sin comentarios adicionales.' }}
                                    </p>

                                    <p class="mt-2 text-xs text-zinc-400">
                                        Por {{ $registro->usuario?->name ?? 'Usuario no disponible' }}
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