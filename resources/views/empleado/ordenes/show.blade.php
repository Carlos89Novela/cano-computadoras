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
                                Número de serie
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
                        Todavía no hay avances registrados.
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

            <section class="rounded-xl border border-amber-800 bg-amber-950 p-6">
                <h3 class="font-bold text-amber-200">
                    Controles técnicos
                </h3>

                <p class="mt-2 text-sm text-amber-100">
                    El registro de diagnóstico y avances se habilitará en la siguiente etapa.
                    El cierre definitivo seguirá reservado para supervisión.
                </p>
            </section>
        </div>
    </div>
</x-app-layout>