<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-green-500">
                    Reparación asignada
                </p>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $orden->folio }}
                </h2>
            </div>

            <a href="{{ route('empleado.dashboard') }}" class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Volver a mis reparaciones
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-6xl space-y-8 px-6">
            @if (session('success'))
                <div
                    class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200"
                    role="status"
                >
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="rounded-lg border border-red-700 bg-red-950 p-4 text-red-200"
                    role="alert"
                >
                    <p class="font-semibold">
                        No fue posible guardar la información técnica.
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Estado actual
                    </p>

                    <div class="mt-3">
                        @include('admin.ordenes.partials.estado-badge', [
                            'estado' => $orden->estado,
                        ])
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Fecha de ingreso
                    </p>

                    <p class="mt-3 text-lg font-bold text-gray-900 dark:text-white">
                        {{ $orden->fecha_ingreso->format('d/m/Y') }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Costo estimado
                    </p>

                    <p class="mt-3 text-lg font-bold text-gray-900 dark:text-white">
                        @if ($orden->costo_estimado !== null)
                            ${{ number_format((float) $orden->costo_estimado, 2) }}
                        @else
                            Pendiente
                        @endif
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Costo final
                    </p>

                    <p class="mt-3 text-lg font-bold text-gray-900 dark:text-white">
                        @if ($orden->costo_final !== null)
                            ${{ number_format((float) $orden->costo_final, 2) }}
                        @else
                            Pendiente
                        @endif
                    </p>
                </div>
            </section>

            <section class="grid gap-8 lg:grid-cols-2">
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Información del cliente y equipo
                    </h3>

                    <dl class="mt-6 space-y-5">
                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Cliente
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->user->name }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Tipo de equipo
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->equipo->tipo }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Marca y modelo
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->equipo->marca }}
                                {{ $orden->equipo->modelo }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                NÃºmero de serie
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->equipo->numero_serie ?? 'No registrado' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Servicio solicitado
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->servicio?->nombre ?? 'Diagnóstico general' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Asignación actual
                    </h3>

                    <dl class="mt-6 space-y-5">
                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Empleado responsable
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->asignacionActiva?->empleado?->name ?? 'No disponible' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Asignada por
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->asignacionActiva?->asignadoPor?->name ?? 'No disponible' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Fecha de asignación
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->asignacionActiva?->asignado_at?->format('d/m/Y H:i') ?? 'No disponible' }}
                            </dd>
                        </div>
                    </dl>

                    @if (filled($orden->asignacionActiva?->observaciones))
                        <div class="mt-6 rounded-lg border border-blue-800 bg-blue-950 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-300">
                                Indicaciones del supervisor
                            </p>

                            <p class="mt-2 text-sm text-blue-100">
                                {{ $orden->asignacionActiva->observaciones }}
                            </p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                    Detalle técnico
                </h3>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                            Problema reportado
                        </p>

                        <div class="mt-2 rounded-lg bg-gray-100 p-4 text-gray-800 dark:bg-zinc-800 dark:text-gray-200">
                            {{ $orden->problema_reportado }}
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                            Diagnóstico
                        </p>

                        <div class="mt-2 rounded-lg bg-gray-100 p-4 text-gray-800 dark:bg-zinc-800 dark:text-gray-200">
                            {{ $orden->diagnostico ?: 'Diagnóstico pendiente.' }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-green-500">
                        Actividad técnica
                    </p>

                    <h3 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                        Historial de la reparación
                    </h3>
                </div>

                @if ($orden->historial->isEmpty())
                    <div class="mt-6 rounded-lg border border-dashed border-zinc-700 bg-zinc-800 p-6 text-center text-gray-300">
                        TodavÃ­a no hay avances registrados.
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach ($orden->historial as $registro)
                            <article class="rounded-xl border border-zinc-700 bg-zinc-800 p-5">
                                <div class="flex flex-col justify-between gap-2 md:flex-row md:items-center">
                                    <div>
                                        @include('admin.ordenes.partials.estado-badge', [
                                            'estado' => $registro->estado,
                                        ])
                                    </div>

                                    <time class="text-sm text-gray-400">
                                        {{ $registro->created_at->format('d/m/Y H:i') }}
                                    </time>
                                </div>

                                @if (filled($registro->comentarios))
                                    <div class="mt-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Comentario interno
                                        </p>

                                        <p class="mt-2 text-sm text-gray-200">
                                            {{ $registro->comentarios }}
                                        </p>
                                    </div>
                                @endif

                                <p class="mt-4 text-xs text-gray-500">
                                    Registrado por:
                                    {{ $registro->usuario?->name ?? 'Sistema' }}
                                </p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-green-500">
                        Trabajo técnico
                    </p>

                    <h3 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                        Actualizar diagnóstico y avance
                    </h3>

                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Esta información es interna y no se enviarÃ¡ directamente al cliente.
                    </p>
                </div>

                <form
                    action="{{ route('empleado.ordenes.tecnica.update', ['orden' => $orden->id]) }}"
                    method="POST"
                >
                    @csrf
                    @method('PATCH')

                    <div>
                        <label
                            for="diagnostico"
                            class="mb-2 block font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Diagnóstico
                        </label>

                        <textarea
                            id="diagnostico"
                            name="diagnostico"
                            rows="6"
                            maxlength="3000"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-900 placeholder:text-gray-400 focus:border-green-500 focus:ring-green-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500"
                            placeholder="Describe las fallas encontradas y las pruebas realizadas."
                        >{{ old('diagnostico', $orden->diagnostico) }}</textarea>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            MÃ¡ximo 3000 caracteres.
                        </p>

                        @error('diagnostico')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="costo_estimado"
                            class="mb-2 block font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Costo estimado
                        </label>

                        <input
                            id="costo_estimado"
                            name="costo_estimado"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            value="{{ old('costo_estimado', $orden->costo_estimado) }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-900 focus:border-green-500 focus:ring-green-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            placeholder="0.00"
                        >

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Importe preliminar sujeto a revisión y autorización.
                        </p>

                        @error('costo_estimado')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="comentario"
                            class="mb-2 block font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Comentario interno del avance
                        </label>

                        <textarea
                            id="comentario"
                            name="comentario"
                            rows="4"
                            maxlength="2000"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-900 placeholder:text-gray-400 focus:border-green-500 focus:ring-green-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500"
                            placeholder="Registra las pruebas, acciones o avances realizados."
                        >{{ old('comentario') }}</textarea>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Este comentario solo serÃ¡ visible para el personal autorizado.
                            MÃ¡ximo 2000 caracteres.
                        </p>

                        @error('comentario')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="rounded-lg border border-amber-800 bg-amber-950 p-4">
                        <p class="font-semibold text-amber-200">
                            Acciones restringidas
                        </p>

                        <p class="mt-2 text-sm text-amber-100">
                            Este formulario no permite cambiar el estado, el costo final,
                            la autorización del presupuesto, la asignación ni el cierre
                            de la reparación.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-3 font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-zinc-900"
                        >
                            Guardar trabajo técnico
                        </button>

                        <a
                            href="{{ route('empleado.dashboard') }}"
                            class="inline-flex items-center justify-center rounded-lg border border-zinc-600 px-6 py-3 font-semibold text-gray-700 transition hover:bg-zinc-100 dark:text-gray-200 dark:hover:bg-zinc-800 dark:focus:ring-offset-zinc-900"
                        >
                            Volver a mis reparaciones
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
