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
            <!-- Información de la orden de servicio -->
            <div class="rounded-xl bg-white p-8 shadow dark:bg-zinc-900">

                <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <p class="text-sm text-gray-500">
                            Folio
                        </p>

                        <h3 class="text-2xl font-bold text-purple-500">
                            {{ $orden->folio }}
                        </h3>
                    </div>

                    <span class="w-fit rounded-full bg-purple-950 px-5 py-2 text-purple-200">
                        {{ $orden->estado }}
                    </span>
                </div>

                <div class="grid gap-6 md:grid-cols-2">

                    <div>
                        <p class="text-sm text-gray-500">
                            Equipo
                        </p>

                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ $orden->equipo->tipo }},
                            {{ $orden->equipo->marca }}
                            {{ $orden->equipo->modelo }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">
                            Servicio solicitado
                        </p>

                        <p class="font-semibold text-gray-900 dark:text-white">
                            @if ($orden->servicio)
                                {{ $orden->servicio->nombre }}
                            @else
                                Diagnóstico general o servicio por definir
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">
                            Fecha de ingreso
                        </p>

                        <p class="font-semibold text-gray-900 dark:text-white">
                            {{ $orden->fecha_ingreso->format('d/m/Y') }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500">
                            Problema reportado
                        </p>

                        <p class="mt-1 text-gray-900 dark:text-white">
                            {{ $orden->problema_reportado }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500">
                            Diagnóstico
                        </p>

                        <p class="mt-1 text-gray-900 dark:text-white">
                            {{ $orden->diagnostico ?: 'Diagnóstico pendiente.' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">
                            Costo estimado
                        </p>

                        <p class="font-semibold text-gray-900 dark:text-white">
                            @if ($orden->costo_estimado !== null)
                                ${{ number_format($orden->costo_estimado, 2) }}
                            @else
                                Pendiente
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">
                            Costo final
                        </p>

                        <p class="font-semibold text-gray-900 dark:text-white">
                            @if ($orden->costo_final !== null)
                                ${{ number_format($orden->costo_final, 2) }}
                            @else
                                Pendiente
                            @endif
                        </p>
                    </div>

                </div>

                @if (
                    $orden->estado === 'Esperando autorización' &&
                    $orden->autorizacion === 'pendiente'
                )

                    <div class="mt-8 rounded-xl border border-yellow-700 bg-yellow-950 p-6">

                        <h3 class="text-xl font-bold text-yellow-200">
                            Autorización de presupuesto
                        </h3>

                        <p class="mt-2 text-yellow-100">
                            Revisa el diagnóstico y el costo estimado antes de tomar una decisión.
                        </p>

                        <p class="mt-4 text-2xl font-bold text-white">
                            ${{ number_format((float) $orden->costo_estimado, 2) }}
                        </p>

                        <div class="mt-6 flex flex-wrap gap-4">

                            <form
                                action="{{ route('ordenes.autorizar', ['orden' => $orden->id]) }}"
                                method="POST"
                            >
                                @csrf

                                <input
                                    type="hidden"
                                    name="decision"
                                    value="autorizada"
                                >

                                <button
                                    type="submit"
                                    class="rounded-lg bg-green-600 px-5 py-3 font-semibold text-white transition hover:bg-green-700"
                                >
                                    Autorizar reparación
                                </button>
                            </form>

                            <form
                                action="{{ route('ordenes.autorizar', ['orden' => $orden->id]) }}"
                                method="POST"
                                onsubmit="return confirm('¿Deseas rechazar este presupuesto?');"
                            >
                                @csrf

                                <input
                                    type="hidden"
                                    name="decision"
                                    value="rechazada"
                                >

                                <button
                                    type="submit"
                                    class="rounded-lg bg-red-700 px-5 py-3 font-semibold text-white transition hover:bg-red-800"
                                >
                                    Rechazar presupuesto
                                </button>
                            </form>

                        </div>

                    </div>

                @elseif ($orden->autorizacion === 'autorizada')

                    <div class="mt-8 rounded-lg border border-green-700 bg-green-950 p-5 text-green-200">
                        Presupuesto autorizado el
                        {{ $orden->fecha_autorizacion?->format('d/m/Y H:i') }}.
                    </div>

                @elseif ($orden->autorizacion === 'rechazada')

                    <div class="mt-8 rounded-lg border border-red-700 bg-red-950 p-5 text-red-200">
                        Presupuesto rechazado el
                        {{ $orden->fecha_autorizacion?->format('d/m/Y H:i') }}.
                    </div>

                @endif

                <!-- Código QR de seguimiento -->
                <div class="mt-10 border-t border-zinc-700 pt-8">

                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Código QR de seguimiento
                    </h3>

                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Escanea este código para consultar el estado de la reparación.
                    </p>

                    <div class="mt-6 flex flex-col items-start gap-5 md:flex-row md:items-center">

                        <div class="rounded-xl bg-white p-5">
                            {!!
                                \SimpleSoftwareIO\QrCode\Facades\QrCode::size(220)
                                    ->margin(1)
                                    ->generate(
                                        route('seguimiento.show', [
                                            'folio' => $orden->folio
                                        ])
                                    )
                            !!}
                        </div>

                        <div>
                            <p class="text-sm text-gray-500">
                                Folio
                            </p>

                            <p class="font-bold text-purple-500">
                                {{ $orden->folio }}
                            </p>

                            <a
                                href="{{ route('seguimiento.show', ['folio' => $orden->folio]) }}"
                                class="mt-2 inline-block rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-purple-500">
                                Abrir seguimiento público
                            </a>
                        </div>

                    </div>

                </div>
                <!-- Historial de la reparación -->
                <div class="mt-10 border-t border-zinc-700 pt-8">

                    <h3 class="mb-6 text-2xl font-bold text-gray-900 dark:text-white">
                        Historial de la reparación
                    </h3>

                    @if ($orden->historial->isEmpty())

                        <div class="rounded-lg bg-zinc-800 p-5">
                            <p class="text-gray-300">
                                Todavía no hay actualizaciones registradas.
                            </p>

                            <p class="mt-2 text-sm text-gray-500">
                                Estado actual: {{ $orden->estado }}
                            </p>
                        </div>

                    @else

                        <div class="relative ml-3 border-l-2 border-purple-700">

                            @foreach ($orden->historial as $registro)

                                <div class="relative mb-8 ml-8">

                                    <!-- Punto de la línea de tiempo -->
                                    <span
                                        class="absolute -left-11 top-1 h-5 w-5 rounded-full
                                            border-4 border-zinc-900 bg-purple-500"
                                    ></span>

                                    <div class="rounded-xl bg-zinc-800 p-5">

                                        <div
                                            class="flex flex-col justify-between gap-2
                                                md:flex-row md:items-center"
                                        >
                                            <h4 class="font-bold" style="color: #c084fc;">
                                                {{ $registro->estado }}
                                            </h4>

                                            <span class="text-sm text-gray-400">
                                                {{ $registro->created_at->format('d/m/Y H:i') }}
                                            </span>
                                        </div>

                                        @if ($registro->comentarios)
                                            <p class="mt-3 text-white">
                                                {{ $registro->comentarios }}
                                            </p>
                                        @endif

                                        <p class="mt-3 text-xs text-gray-500">
                                            Actualizado por:
                                            {{ $registro->usuario?->name ?? 'Sistema' }}
                                        </p>

                                    </div>

                                </div>

                            @endforeach

                        </div>

                    @endif

                </div>
                <!-- Botones de acción -->
                <div class="mt-8 flex flex-wrap gap-4">
                    <!-- Botón para descargar el comprobante PDF -->
                    <a href="{{ route('ordenes.pdf', $orden->id) }}"
                        class="inline-block rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                    >
                        Descargar comprobante PDF
                    </a>
                    <!-- Botón para volver a la lista de órdenes -->
                    <a href="{{ route('ordenes.index') }}"
                        class="inline-block rounded-lg bg-zinc-700 px-5 py-3 font-semibold text-white transition hover:bg-zinc-600"
                    >
                        Volver a mis reparaciones
                    </a>

                </div>

            </div>

        </div>
    </div>
</x-app-layout>