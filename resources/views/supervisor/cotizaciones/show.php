<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-500">
                    Revisión de cotización
                </p>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $orden->folio }}
                </h2>
            </div>

            {{ route('supervisor.dashboard') }}
                Volver al panel
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
                        No fue posible procesar la cotización.
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
                        Estado de revisión
                    </p>

                    <p class="mt-3 text-lg font-bold text-amber-500">
                        {{ $orden->estado_revision_cotizacion->etiqueta() }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Estado de reparación
                    </p>

                    <div class="mt-3">
                        @include('admin.ordenes.partials.estado-badge', [
                            'estado' => $orden->estado,
                        ])
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Costo estimado
                    </p>

                    <p class="mt-3 text-lg font-bold text-gray-900 dark:text-white">
                        @if ($orden->costo_estimado !== null)
                            ${{ number_format((float) $orden->costo_estimado, 2) }}
                        @else
                            No registrado
                        @endif
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Fecha de solicitud
                    </p>

                    <p class="mt-3 text-lg font-bold text-gray-900 dark:text-white">
                        {{ $orden->updated_at->format('d/m/Y H:i') }}
                    </p>
                </div>
            </section>

            <section class="grid gap-8 lg:grid-cols-2">
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Cliente y equipo
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
                                Equipo
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->equipo->tipo }}
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
                                Servicio
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                @if ($orden->servicio_id === null)
                                    Diagnóstico general
                                @else
                                    {{ $orden->servicio->nombre }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Personal técnico
                    </h3>

                    <dl class="mt-6 space-y-5">
                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Empleado responsable
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                @if ($orden->asignacionActiva === null)
                                    Sin empleado
                                @else
                                    {{ $orden->asignacionActiva->empleado->name }}
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Fecha de ingreso
                            </dt>

                            <dd class="mt-1 text-gray-900 dark:text-white">
                                {{ $orden->fecha_ingreso->format('d/m/Y') }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                    Diagnóstico y presupuesto
                </h3>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                            Problema reportado
                        </p>

                        <div class="mt-2 rounded-lg bg-gray-100 p-5 text-gray-800 dark:bg-zinc-800 dark:text-gray-200">
                            {{ $orden->problema_reportado }}
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                            Diagnóstico técnico
                        </p>

                        <div class="mt-2 rounded-lg bg-gray-100 p-5 text-gray-800 dark:bg-zinc-800 dark:text-gray-200">
                            {{ $orden->diagnostico ?? 'Sin diagnóstico registrado.' }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-8 lg:grid-cols-2">
                @can('approveQuoteReview', $orden)
                    <div class="rounded-xl border border-green-700 bg-green-950 p-6">
                        <h3 class="text-xl font-bold text-green-200">
                            Aprobar cotización
                        </h3>

                        <p class="mt-2 text-sm text-green-100">
                            Confirma que el diagnóstico y el costo estimado son correctos.
                        </p>

                        <form
                            action="{{ route('cotizaciones.aprobar', $orden->id) }}"
                            method="POST"
                            class="mt-6"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-3 font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-green-950"
                            >
                                Aprobar cotización
                            </button>
                        </form>
                    </div>
                @endcan

                @can('rejectQuoteReview', $orden)
                    <div class="rounded-xl border border-red-700 bg-red-950 p-6">
                        <h3 class="text-xl font-bold text-red-200">
                            Rechazar cotización
                        </h3>

                        <p class="mt-2 text-sm text-red-100">
                            Indica claramente qué debe corregir el empleado.
                        </p>

                         <form action="{{ route('cotizaciones.rechazar', $orden->id) }}" method="POST" class="mt-6 space-y-4">
                            @csrf


                            <div>
                                <label
                                    for="observacion"
                                    class="mb-2 block font-semibold text-red-100"
                                >
                                    Motivo del rechazo
                                </label>

                                <textarea
                                    id="observacion"
                                    name="observacion"
                                    rows="5"
                                    maxlength="2000"
                                    required
                                    class="w-full rounded-lg border border-red-800 bg-red-900 px-4 py-3 text-white placeholder:text-red-300 focus:border-red-500 focus:ring-red-500"
                                    placeholder="Describe los cambios necesarios."
                                >{{ old('observacion') }}</textarea>

                                @error('observacion')
                                    <p class="mt-2 text-sm text-red-200">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-red-600 px-6 py-3 font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-red-950"
                            >
                                Rechazar y devolver
                            </button>
                        </form>
                    </div>
                @endcan
            </section>
        </div>
    </div>
</x-app-layout>