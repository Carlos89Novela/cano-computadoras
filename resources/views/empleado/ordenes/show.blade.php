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

            <section
                id="siguiente-accion"
                class="space-y-6"
                aria-labelledby="siguiente-accion-titulo"
            >
                <div>
                    <p
                        class="text-sm font-semibold uppercase tracking-wide text-indigo-400"
                    >
                        Flujo de reparación
                    </p>

                    <h3
                        id="siguiente-accion-titulo"
                        class="mt-1 text-xl font-bold text-gray-900 dark:text-white"
                    >
                        Siguiente acción
                    </h3>

                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Aquí aparecerá la acción disponible de acuerdo con
                        el estado actual de la reparación.
                    </p>
                </div>

                @can('requestQuoteReview', $orden)
                    @php
                        $cotizacionCompleta =
                            filled($orden->diagnostico)
                            && $orden->costo_estimado !== null;
                    @endphp

                    @if (! $cotizacionCompleta)
                        <div class="mt-6 rounded-lg border border-amber-700 bg-amber-950 p-5">
                            <p class="font-semibold text-amber-200">
                                Información incompleta
                            </p>

                            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-amber-100">
                                @if (blank($orden->diagnostico))
                                    <li>
                                        Debes registrar un diagnóstico.
                                    </li>
                                @endif

                                @if ($orden->costo_estimado === null)
                                    <li>
                                        Debes registrar el costo estimado.
                                    </li>
                                @endif
                            </ul>

                            <p class="mt-3 text-sm text-amber-100">
                                Guarda primero el trabajo técnico y después solicita
                                la revisión.
                            </p>
                        </div>
                    @endif

                    <form
                        action="{{ route('empleado.ordenes.cotizacion.revision', ['orden' => $orden->id]) }}"
                        method="POST"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:cursor-not-allowed disabled:opacity-50"
                            @disabled(! $cotizacionCompleta)
                        >
                            @if (
                                $orden->estado_revision_cotizacion ===
                                App\Enums\EstadoRevisionCotizacion::RECHAZADA
                            )
                                Volver a solicitar revisión
                            @else
                                Solicitar revisión de cotización
                            @endif
                        </button>
                    </form>
                @endcan

                @can('startAuthorizedRepair', $orden)
                <section
                    class="rounded-xl border border-green-700 bg-green-950 p-6"
                >
                    <div
                        class="flex flex-col justify-between gap-5 md:flex-row md:items-center"
                    >
                        <div>
                            <p
                                class="text-sm font-semibold uppercase tracking-wide text-green-300"
                            >
                                Presupuesto autorizado
                            </p>

                            <h3 class="mt-1 text-xl font-bold text-green-100">
                                La reparación está lista para comenzar
                            </h3>

                            <p class="mt-2 text-sm text-green-200">
                                El cliente autorizó el presupuesto. Al iniciar la
                                reparación, el estado cambiará a En reparación y el
                                cliente recibirá una notificación.
                            </p>
                        </div>

                        <form
                            action="{{ route('empleado.ordenes.iniciar', ['orden' => $orden->id]) }}"
                            method="POST"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-green-600 px-6 py-3 font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-green-950"
                            >
                                Iniciar reparación
                            </button>
                            </form>
                        </div>
                    </section>
                @endcan

                @can('sendRepairToTesting', $orden)
                <section class="rounded-xl border border-blue-700 bg-blue-950 p-6">
                    <div>
                        <p class="text-sm font-semibold uppercase text-blue-300">
                            Reparación en proceso
                        </p>

                        <h3 class="mt-1 text-xl font-bold text-blue-100">
                            Enviar reparación a pruebas
                        </h3>

                        <p class="mt-2 text-sm text-blue-200">
                            Confirma que el equipo está listo para las pruebas.
                        </p>
                    </div>

                    <form
                        action="{{ route('empleado.ordenes.pruebas', ['orden' => $orden->id]) }}"
                        method="POST"
                        >
                        @csrf

                        <div>
                            <label
                                for="comentario_pruebas"
                                class="block font-semibold text-blue-100"
                            >
                                Comentario interno opcional
                            </label>

                            <textarea
                                id="comentario_pruebas"
                                name="comentario"
                                rows="4"
                                maxlength="2000"
                                class="mt-2 w-full rounded-lg border border-blue-700 bg-blue-900 p-3 text-white"
                                placeholder="Describe la reparación realizada."
                            >{{ old('comentario') }}</textarea>

                            <p class="mt-2 text-xs text-blue-300">
                                Máximo 2000 caracteres.
                            </p>

                            @error('comentario')
                                <p class="mt-2 text-sm text-red-300">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700"
                        >
                            Enviar a pruebas
                        </button>
                        </form>
                    </section>
                @endcan

                @can('markRepairReadyForDelivery', $orden)
                <section class="rounded-xl border border-purple-700 bg-purple-950 p-6">
                    <div>
                        <p class="text-sm font-semibold uppercase text-purple-300">
                            Pruebas en proceso
                        </p>

                        <h3 class="mt-1 text-xl font-bold text-purple-100">
                            Marcar reparación como lista para entrega
                        </h3>

                        <p class="mt-2 text-sm text-purple-200">
                            Confirma que el equipo superó las pruebas y registra
                            el costo final antes de finalizar el trabajo técnico.
                        </p>
                    </div>

                    <form
                        id="lista-para-entrega-form"
                        action="{{ route(
                        'empleado.ordenes.lista-para-entrega',['orden' => $orden->id]) }}"
                        >
                        @csrf

                        <div>
                            <label
                                for="costo_final_entrega"
                                class="block font-semibold text-purple-100"
                            >
                                Costo final
                            </label>

                            <input
                                id="costo_final_entrega"
                                name="costo_final"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                max="99999999.99"
                                step="0.01"
                                required
                                value="{{ old('costo_final', $orden->costo_estimado) }}"
                                class="mt-2 w-full rounded-lg border border-purple-700 bg-purple-900 p-3 text-gray-600"
                                placeholder="0.00"
                            >

                            <p class="mt-2 text-xs text-purple-300">
                                Registra el importe final con un máximo de dos decimales.
                            </p>

                            @error('costo_final')
                                <p class="mt-2 text-sm text-red-300">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="comentario_entrega"
                                class="block font-semibold text-purple-100"
                            >
                                Comentario interno opcional
                            </label>

                            <textarea
                                id="comentario_entrega"
                                name="comentario"
                                rows="4"
                                maxlength="2000"
                                class="mt-2 w-full rounded-lg border border-purple-700 bg-purple-900 p-3 text-gray-900"
                                placeholder="Describe el resultado de las pruebas."
                            >{{ old('comentario') }}</textarea>

                            <p class="mt-2 text-xs text-purple-300">
                                Máximo 2000 caracteres.
                            </p>

                            @error('comentario')
                                <p class="mt-2 text-sm text-red-300">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            form="lista-para-entrega-form"
                            formmethod="POST"
                            formaction="{{ route('empleado.ordenes.lista-para-entrega',['orden' => $orden->id]) }}"
                            class="rounded-lg bg-purple-600 px-6 py-3 font-semibold text-white hover:bg-purple-700"
                        >
                            Marcar como lista para entrega
                        </button>
                        </form>
                    </section>
                @endcan

                {{-- Las acciones se moverán dentro de este espacio. --}}
            </section>

            <section class="grid gap-8 lg:grid-cols-2">
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Información del cliente y equipo
                    </h3>

                    <dl class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="rounded-lg bg-gray-100 p-4 dark:bg-zinc-800">
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Cliente
                            </dt>

                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ $orden->user->name }}
                            </dd>
                        </div>

                        <div class="rounded-lg bg-gray-100 p-4 dark:bg-zinc-800">
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Tipo de equipo
                            </dt>

                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ $orden->equipo->tipo }}
                            </dd>
                        </div>

                        <div class="rounded-lg bg-gray-100 p-4 dark:bg-zinc-800">
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Marca y modelo
                            </dt>

                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ $orden->equipo->marca }}
                                {{ $orden->equipo->modelo }}
                            </dd>
                        </div>

                        <div class="rounded-lg bg-gray-100 p-4 dark:bg-zinc-800">
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Número de serie
                            </dt>

                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ $orden->equipo->numero_serie ?? 'No registrado' }}
                            </dd>
                        </div>

                        <div class="rounded-lg bg-gray-100 p-4 dark:bg-zinc-800 md:col-span-2">
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Servicio solicitado
                            </dt>

                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ $orden->servicio?->nombre ?? 'Diagnóstico general' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Asignación actual
                    </h3>

                    <dl class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="rounded-lg bg-gray-100 p-4 dark:bg-zinc-800">
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Empleado responsable
                            </dt>

                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ $orden->asignacionActiva?->empleado?->name ?? 'No disponible' }}
                            </dd>
                        </div>

                        <div class="rounded-lg bg-gray-100 p-4 dark:bg-zinc-800">
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Asignada por
                            </dt>

                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ $orden->asignacionActiva?->asignadoPor?->name ?? 'No disponible' }}
                            </dd>
                        </div>

                        <div class="rounded-lg bg-gray-100 p-4 dark:bg-zinc-800">
                            <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Fecha de asignación
                            </dt>

                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">
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
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Desplázate dentro del historial para consultar registros anteriores.
                </p>

                @if ($orden->historial->isEmpty())
                    <div class="mt-6 rounded-lg border border-dashed border-zinc-700 bg-zinc-800 p-6 text-center text-gray-300">
                        Todavía no hay avances registrados.
                    </div>
                @else
                    <div class="mt-6 rounded-xl border border-zinc-700 bg-zinc-900 p-3">
                        <div class="space-y-3 overflow-y-auto overscroll-contain pr2" style="height: 360px; max-height: 360px; overflow-y: auto;"
                            tabindex="0" aria-label="Historial de la reparación">
                            @foreach ($orden->historial as $registro)
                                <article class="rounded-lg border border-zinc-700 bg-zinc-800 p-4">
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
                    </div>
                @endif
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-500">
                            Cotización
                        </p>

                        <h3 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                            Revisión interna de la cotización
                        </h3>

                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            El supervisor debe revisar el diagnóstico y el costo estimado
                            antes de enviar el presupuesto al cliente.
                        </p>
                    </div>

                    @php
                        $claseEstadoRevision = match (
                            $orden->estado_revision_cotizacion
                        ) {
                            App\Enums\EstadoRevisionCotizacion::SIN_SOLICITAR =>
                                'bg-gray-200 text-gray-700 dark:bg-zinc-800 dark:text-gray-200',

                            App\Enums\EstadoRevisionCotizacion::PENDIENTE =>
                                'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200',

                            App\Enums\EstadoRevisionCotizacion::APROBADA =>
                                'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200',

                            App\Enums\EstadoRevisionCotizacion::RECHAZADA =>
                                'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200',
                        };
                    @endphp

                    <span
                        class="inline-flex h-fit w-fit shrink-0 items-center rounded-full px-4 py-2 text-sm font-semibold leading-none {{ $claseEstadoRevision }}"
                    >
                        {{ $orden->estado_revision_cotizacion->etiqueta() }}
                    </span>
                </div>

                @if (
                    $orden->estado_revision_cotizacion ===
                    App\Enums\EstadoRevisionCotizacion::PENDIENTE
                )
                    <div class="mt-6 rounded-lg border border-amber-700 bg-amber-950 p-5">
                        <p class="font-semibold text-amber-200">
                            Cotización pendiente de revisión
                        </p>

                        <p class="mt-2 text-sm text-amber-100">
                            El supervisor revisará el diagnóstico y el costo estimado.
                            No es necesario enviar otra solicitud.
                        </p>
                    </div>
                @elseif (
                    $orden->estado_revision_cotizacion ===
                    App\Enums\EstadoRevisionCotizacion::APROBADA
                )
                    <div class="mt-6 rounded-lg border border-green-700 bg-green-950 p-5">
                        <p class="font-semibold text-green-200">
                            Cotización aprobada internamente
                        </p>

                        <p class="mt-2 text-sm text-green-100">
                            La revisión del supervisor fue aprobada.
                            El siguiente paso será presentar el presupuesto al cliente.
                        </p>

                        @if ($orden->cotizacion_revisada_at !== null)
                            <p class="mt-3 text-xs text-green-300">
                                Revisada el
                                {{ $orden->cotizacion_revisada_at->format('d/m/Y H:i') }}.
                            </p>
                        @endif
                    </div>
                @elseif (
                    $orden->estado_revision_cotizacion ===
                    App\Enums\EstadoRevisionCotizacion::RECHAZADA
                )
                    <div class="mt-6 rounded-lg border border-red-700 bg-red-950 p-5">
                        <p class="font-semibold text-red-200">
                            Cotización devuelta para corrección
                        </p>

                        <p class="mt-2 text-sm text-red-100">
                            Corrige el diagnóstico o el costo estimado y vuelve a solicitar
                            la revisión.
                        </p>

                        @if (filled($orden->observacion_revision_cotizacion))
                            <div class="mt-4 rounded-lg bg-red-900 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-red-300">
                                    Observación del supervisor
                                </p>

                                <p class="mt-2 text-sm text-red-100">
                                    {{ $orden->observacion_revision_cotizacion }}
                                </p>
                            </div>
                        @endif
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
                        Esta información es interna y no se enviará directamente al cliente.
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
                            Máximo 3000 caracteres.
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
                            Este comentario solo será visible para el personal autorizado.
                            Máximo 2000 caracteres.
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
