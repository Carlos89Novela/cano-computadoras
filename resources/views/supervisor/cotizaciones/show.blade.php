<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-blue-500">
                    Revisión de cotización
                </p>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $orden->folio }}
                </h2>
            </div>

            <a
                href="{{ route('super            Volver al panel
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-6">
            @if ($errors->any())
                <div
                    class="rounded-lg border border-red-700 bg-red-950 p-4 text-red-200"
                    role="alert"
                >
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                    Diagnóstico y presupuesto
                </h3>

                <dl class="mt-6 space-y-4">
                    <div>
                        <dt class="font-semibold text-gray-500">
                            Cliente
                        </dt>

                        <dd class="text-gray-900 dark:text-white">
                            {{ $orden->user->name }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-semibold text-gray-500">
                            Diagnóstico
                        </dt>

                        <dd class="text-gray-900 dark:text-white">
                            {{ $orden->diagnostico }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-semibold text-gray-500">
                            Costo estimado
                        </dt>

                        <dd class="text-gray-900 dark:text-white">
                            ${{ number_format((float) $orden->costo_estimado, 2) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="grid gap-6 md:grid-cols-2">
                @can('approveQuoteReview', $orden)
                    <div class="rounded-xl border border-green-700 bg-green-950 p-6">
                        <h3 class="text-xl font-bold text-green-200">
                            Aprobar cotización
                        </h3>

                        <form
                            action="{{ route('operacion.cotizaciones.aprobar', ['orden' => $orden->id]) }}"
                            method="POST"
                            class="mt-5"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="rounded-lg bg-green-600 px-5 py-3 font-semibold text-white"
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

                        <form
                            action="{{ route('operacion.cotizaciones.rechazar', ['orden' => $orden->id]) }}"
                            method="POST"
                            class="mt-5 space-y-4"
                        >
                            @csrf

                            <div>
                                <label
                                    for="observacion"
                                    class="block font-semibold text-red-100"
                                >
                                    Motivo del rechazo
                                </label>

                                <textarea
                                    id="observacion"
                                    name="observacion"
                                    rows="5"
                                    maxlength="2000"
                                    required
                                    class="mt-2 w-full rounded-lg border border-red-700 bg-red-900 p-3 text-white"
                                >{{ old('observacion') }}</textarea>
                            </div>

                            <button
                                type="submit"
                                class="rounded-lg bg-red-600 px-5 py-3 font-semibold text-white"
                            >
                                Rechazar cotización
                            </button>
                        </form>
                    </div>
                @endcan
            </section>
        </div>
    </div>
</x-app-layout>