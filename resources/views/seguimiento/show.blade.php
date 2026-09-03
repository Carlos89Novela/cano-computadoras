<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Seguimiento {{ $orden->folio }} | Cano Computadoras
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-950 text-white">

    <header class="border-b border-zinc-800 bg-zinc-900">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">

            <a href="{{ url('/') }}">
                <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-purple-600 text-xl font-bold">
                    C
                </div>
            </a>

                <div>
                    <h1 class="text-xl font-bold text-purple-400">
                        Cano Computadoras
                    </h1>

                    <p class="text-xs text-gray-400">
                        Seguimiento de reparaciones
                    </p>
                </div>

            <a
                href="{{ url('/') }}"
                class="rounded-lg bg-zinc-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-zinc-600"
            >
                Volver al inicio
            </a>

        </div>
    </header>

    <main class="mx-auto max-w-4xl px-6 py-12">

        <section class="mb-8 text-center">

            <p class="mb-2 text-sm font-semibold uppercase tracking-wider text-purple-400">
                Consulta de reparación
            </p>

            <h2 class="text-3xl font-bold md:text-4xl">
                Estado de tu equipo
            </h2>

            <p class="mt-3 text-gray-400">
                Consulta el avance utilizando el folio asignado.
            </p>

        </section>

        <section class="rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl md:p-8">

            <div class="mb-8 flex flex-col justify-between gap-5 border-b border-zinc-700 pb-7 md:flex-row md:items-center">

                <div>
                    <p class="text-sm text-gray-400">
                        Folio de reparación
                    </p>

                    <h3 class="mt-1 text-2xl font-bold text-purple-400">
                        {{ $orden->folio }}
                    </h3>
                </div>

                <div>
                    <p class="mb-2 text-sm text-gray-400">
                        Estado actual
                    </p>

                    <span class="inline-block rounded-full bg-purple-950 px-5 py-2 font-semibold text-purple-200">
                        {{ $orden->estado }}
                    </span>
                </div>

            </div>

            <div class="grid gap-6 md:grid-cols-2">

                <div>
                    <p class="text-sm text-gray-400">
                        Equipo
                    </p>

                    <p class="mt-1 font-semibold">
                        {{ $orden->equipo->tipo }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-400">
                        Marca y modelo
                    </p>

                    <p class="mt-1 font-semibold">
                        {{ $orden->equipo->marca }}
                        {{ $orden->equipo->modelo }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-400">
                        Servicio solicitado
                    </p>

                    <p class="mt-1 font-semibold">
                        @if ($orden->servicio)
                            {{ $orden->servicio->nombre }}
                        @else
                            Diagnóstico general
                        @endif
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-400">
                        Fecha de ingreso
                    </p>

                    <p class="mt-1 font-semibold">
                        {{ $orden->fecha_ingreso->format('d/m/Y') }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-400">
                        Fecha de entrega
                    </p>

                    <p class="mt-1 font-semibold">
                        @if ($orden->fecha_entrega)
                            {{ $orden->fecha_entrega->format('d/m/Y') }}
                        @else
                            Pendiente
                        @endif
                    </p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-sm text-gray-400">
                        Problema reportado
                    </p>

                    <div class="mt-2 rounded-lg bg-zinc-800 p-4 text-gray-200">
                        {{ $orden->problema_reportado }}
                    </div>
                </div>

                <div class="md:col-span-2">
                    <p class="text-sm text-gray-400">
                        Diagnóstico
                    </p>

                    <div class="mt-2 rounded-lg bg-zinc-800 p-4 text-gray-200">
                        {{ $orden->diagnostico ?: 'Diagnóstico pendiente.' }}
                    </div>
                </div>

                <div>
                    <p class="text-sm text-gray-400">
                        Costo estimado
                    </p>

                    <p class="mt-1 text-lg font-bold">
                        @if ($orden->costo_estimado !== null)
                            ${{ number_format((float) $orden->costo_estimado, 2) }}
                        @else
                            Pendiente
                        @endif
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-400">
                        Costo final
                    </p>

                    <p class="mt-1 text-lg font-bold">
                        @if ($orden->costo_final !== null)
                            ${{ number_format((float) $orden->costo_final, 2) }}
                        @else
                            Pendiente
                        @endif
                    </p>
                </div>

            </div>

            <div class="mt-10 border-t border-zinc-700 pt-8">

                <h3 class="mb-7 text-2xl font-bold">
                    Historial de avances
                </h3>

                @if ($orden->historial->isEmpty())

                    <div class="rounded-lg bg-zinc-800 p-5 text-gray-300">
                        Todavía no hay avances registrados.
                    </div>

                @else

                    <div class="relative ml-3 border-l-2 border-purple-700">

                        @foreach ($orden->historial as $registro)

                            <div class="relative mb-7 ml-8">

                                <span
                                    class="absolute -left-11 top-1 h-5 w-5 rounded-full border-4 border-zinc-900 bg-purple-500"
                                ></span>

                                <div class="rounded-xl bg-zinc-800 p-5">

                                    <div class="flex flex-col justify-between gap-2 md:flex-row md:items-center">

                                        <h4 class="font-bold text-purple-300">
                                            {{ $registro->estado }}
                                        </h4>

                                        <span class="text-sm text-gray-400">
                                            {{ $registro->created_at->format('d/m/Y H:i') }}
                                        </span>

                                    </div>

                                    @if ($registro->comentario)
                                        <p class="mt-3 text-gray-200">
                                            {{ $registro->comentario }}
                                        </p>
                                    @endif

                                </div>

                            </div>

                        @endforeach

                    </div>

                @endif

            </div>

        </section>

        <footer class="py-10 text-center">

            <p class="text-sm text-gray-500">
                © {{ date('Y') }} Cano Computadoras
            </p>

            <p class="mt-1 text-xs text-gray-600">
                Servicio profesional de reparación de computadoras
            </p>

        </footer>

    </main>

</body>

</html>
