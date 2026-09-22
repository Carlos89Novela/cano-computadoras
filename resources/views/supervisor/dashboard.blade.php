<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-500">
                Supervisión operativa
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Panel del supervisor
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-6">
            @if (session('success'))
                <div class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-700 bg-red-950 p-4 text-red-200">
                    <p class="font-semibold">
                        No fue posible completar la operación.
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
                        Total de reparaciones
                    </p>

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $totalOrdenes }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Reparaciones activas
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $ordenesActivas }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Sin asignar
                    </p>

                    <p class="mt-3 text-4xl font-bold text-amber-500">
                        {{ $ordenesSinAsignar }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Cotizaciones pendientes
                    </p>

                    <p class="mt-3 text-4xl font-bold text-red-500">
                        {{ $cotizacionesPendientes }}
                    </p>
                </div>
            </section>

            <nav
                class="rounded-xl border border-zinc-800 bg-zinc-900 p-2 shadow"
                aria-label="Secciones del panel del supervisor"
            >
                <div
                    class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4"
                    role="tablist"
                >
                    <button
                        type="button"
                        id="tab-cotizaciones"
                        class="supervisor-tab rounded-lg px-4 py-3 text-left font-semibold transition"
                        data-supervisor-tab="cotizaciones"
                        role="tab"
                        aria-controls="panel-cotizaciones"
                    >
                        <span class="block text-sm">
                            Cotizaciones pendientes
                        </span>

                        <span class="mt-1 block text-xs opacity-75">
                            {{ $cotizacionesPendientes }} pendientes
                        </span>
                    </button>

                    <button
                        type="button"
                        id="tab-sin-asignar"
                        class="supervisor-tab rounded-lg px-4 py-3 text-left font-semibold transition"
                        data-supervisor-tab="sin-asignar"
                        role="tab"
                        aria-controls="panel-sin-asignar"
                    >
                        <span class="block text-sm">
                            Sin asignar
                        </span>

                        <span class="mt-1 block text-xs opacity-75">
                            {{ $ordenesSinAsignar }} pendientes
                        </span>
                    </button>

                    <button
                        type="button"
                        id="tab-asignadas"
                        class="supervisor-tab rounded-lg px-4 py-3 text-left font-semibold transition"
                        data-supervisor-tab="asignadas"
                        role="tab"
                        aria-controls="panel-asignadas"
                    >
                        <span class="block text-sm">
                            Reparaciones asignadas
                        </span>

                        <span class="mt-1 block text-xs opacity-75">
                            {{ $ordenesAsignadas->count() }} mostradas
                        </span>
                    </button>

                    <button
                        type="button"
                        id="tab-entregas"
                        class="supervisor-tab rounded-lg px-4 py-3 text-left font-semibold transition"
                        data-supervisor-tab="entregas"
                        role="tab"
                        aria-controls="panel-entregas"
                    >
                        <span class="block text-sm">
                            Equipos para entrega
                        </span>

                        <span class="mt-1 block text-xs opacity-75">
                            {{ $ordenesListasEntrega->count() }} pendientes
                        </span>
                    </button>
                </div>
            </nav>

            <section
                id="panel-cotizaciones"
                data-supervisor-panel="cotizaciones"
                role="tabpanel"
                aria-labelledby="tab-cotizaciones"
                class="supervisor-panel rounded-xl bg-white p-6 shadow dark:bg-zinc-900"
            >
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            Cotizaciones pendientes de revisión
                        </h3>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Diagnósticos y costos estimados enviados por el personal técnico.
                        </p>
                    </div>

                    <div class="w-full md:max-w-sm">
                        <label
                            for="cotizaciones-pendientes-search"
                            class="sr-only"
                        >
                            Buscar cotizaciones pendientes
                        </label>

                        <input
                            id="cotizaciones-pendientes-search"
                            type="search"
                            placeholder="Buscar por folio, cliente, empleado o equipo"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-3 text-white placeholder:text-zinc-500 focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table
                        id="cotizaciones-pendientes-table"
                        class="w-full table-auto text-left"
                    >
                        <thead class="bg-zinc-800 text-white">
                            <tr>
                                <th class="p-4">
                                    Folio
                                </th>

                                <th class="p-4">
                                    Empleado
                                </th>

                                <th class="p-4">
                                    Cliente
                                </th>

                                <th class="p-4">
                                    Equipo
                                </th>

                                <th class="p-4">
                                    Diagnóstico
                                </th>

                                <th class="p-4">
                                    Costo estimado
                                </th>

                                <th class="p-4">
                                    Fecha de solicitud
                                </th>
                                <th class="p-4 text-center">
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                        </tbody>
                    </table>
                </div>
            </section>

            <script>
                (function waitForSupervisorQuoteTable(callback) {
                    if (
                        window.jQuery
                        && window.jQuery.fn
                        && window.jQuery.fn.DataTable
                    ) {
                        callback();

                        return;
                    }

                    window.setTimeout(function () {
                        waitForSupervisorQuoteTable(callback);
                    }, 50);
                })(function () {
                    var tableSelector = '#cotizaciones-pendientes-table';

                    if (
                        window.jQuery.fn.DataTable.isDataTable(
                            tableSelector
                        )
                    ) {
                        return;
                    }

                    var table = window.jQuery(tableSelector).DataTable({
                        serverSide: true,
                        processing: true,
                        responsive: true,
                        pageLength: 10,
                        order: [
                            [6, 'desc']
                        ],
                        ajax: {
                            url: @json(route('supervisor.cotizaciones.data')),
                            type: 'GET'
                        },
                        columns: [
                            {
                                data: 'folio',
                                name: 'folio'
                            },
                            {
                                data: 'empleado',
                                name: 'empleado',
                                orderable: false
                            },
                            {
                                data: 'cliente',
                                name: 'cliente',
                                orderable: false
                            },
                            {
                                data: 'equipo',
                                name: 'equipo',
                                orderable: false
                            },
                            {
                                data: 'diagnostico',
                                name: 'diagnostico',
                                orderable: false
                            },
                            {
                                data: 'costo_estimado',
                                name: 'costo_estimado'
                            },
                            {
                                data: 'fecha_solicitud',
                                name: 'fecha_solicitud'
                            },
                            {
                                data: 'acciones',
                                name: 'acciones',
                                orderable: false,
                                searchable: false
                            }
                        ],
                        language: {
                            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                            processing: 'Cargando cotizaciones pendientes...',
                            emptyTable: 'No existen cotizaciones pendientes de revisión.',
                            zeroRecords: 'No se encontraron cotizaciones con ese criterio.'
                        },
                        dom: "<'flex items-center justify-between mb-3'<'flex items-center'l>>"
                            + "<'table-wrap't>"
                            + "<'flex items-center justify-between mt-3'<'text-sm'i><'pagination'p>>",
                        columnDefs: [
                            {
                                targets: 4,
                                render: function (data, type) {
                                    if (type !== 'display') {
                                        return data;
                                    }

                                    var limite = 90;

                                    if (data.length <= limite) {
                                        return data;
                                    }

                                    return data.substring(0, limite) + '...';
                                }
                            },
                            {
                                targets: 5,
                                className: 'whitespace-nowrap',
                                render: function (data, type) {
                                    var costo = Number(data || 0);

                                    if (type !== 'display') {
                                        return costo;
                                    }

                                    return costo.toLocaleString(
                                        'es-MX',
                                        {
                                            style: 'currency',
                                            currency: 'MXN',
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        }
                                    );
                                }
                            },
                            {
                                targets: 6,
                                className: 'whitespace-nowrap'
                            },
                            {
                                targets: 7,
                                className: 'whitespace-nowrap text-center',
                                orderable: false,
                                searchable: false
                            }
                        ]
                    });

                    window.jQuery('#cotizaciones-pendientes-search')
                        .on('input', function () {
                            table.search(this.value).draw();
                        });
                });
            </script>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            Carga de empleados
                        </h3>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Reparaciones activas asignadas a cada integrante del área técnica.
                        </p>
                    </div>

                    <span class="rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-200">
                        {{ $empleados->count() }} empleados
                    </span>
                </div>

                @if ($empleados->isEmpty())
                    <div class="mt-6 rounded-lg border border-amber-700 bg-amber-950 p-5 text-amber-200">
                        No hay empleados registrados. Asigna el rol empleado a una cuenta antes de distribuir reparaciones.
                    </div>
                @else
                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($empleados as $empleado)
                            <div class="rounded-lg border border-gray-200 p-5 dark:border-zinc-700">
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    {{ $empleado->name }}
                                </p>

                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    Carga activa:
                                    <span class="font-bold text-blue-500">
                                        {{ $empleado->carga_activa }}
                                    </span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section
                id="panel-sin-asignar"
                data-supervisor-panel="sin-asignar"
                role="tabpanel"
                aria-labelledby="tab-sin-asignar"
                class="supervisor-panel hidden rounded-xl bg-white p-6 shadow dark:bg-zinc-900"
            >
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            Reparaciones sin asignar
                        </h3>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Se muestran primero las reparaciones con mayor tiempo de espera.
                        </p>
                    </div>

                    <span class="rounded-full bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-200">
                        {{ $ordenesPendientes->count() }} mostradas
                    </span>
                </div>

                @if ($ordenesPendientes->isEmpty())
                    <div class="mt-6 rounded-lg bg-gray-100 p-6 text-center text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                        No hay reparaciones pendientes de asignación.
                    </div>
                @else
                    <div class="mt-6 space-y-5">
                        @foreach ($ordenesPendientes as $orden)
                            <article class="rounded-xl border border-gray-200 p-5 dark:border-zinc-700">
                                <div class="grid gap-5 lg:grid-cols-2">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <p class="text-lg font-bold text-purple-500">
                                                {{ $orden->folio }}
                                            </p>

                                            <span class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">
                                                {{ $orden->estado }}
                                            </span>
                                        </div>

                                        <dl class="mt-4 space-y-2 text-sm">
                                            <div>
                                                <dt class="inline font-semibold text-gray-700 dark:text-gray-300">
                                                    Cliente:
                                                </dt>

                                                <dd class="inline text-gray-600 dark:text-gray-400">
                                                    {{ $orden->user->name }}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt class="inline font-semibold text-gray-700 dark:text-gray-300">
                                                    Equipo:
                                                </dt>

                                                <dd class="inline text-gray-600 dark:text-gray-400">
                                                    {{ $orden->equipo->marca }}
                                                    {{ $orden->equipo->modelo }}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt class="inline font-semibold text-gray-700 dark:text-gray-300">
                                                    Servicio:
                                                </dt>

                                                <dd class="inline text-gray-600 dark:text-gray-400">
                                                    {{ $orden->servicio?->nombre ?? 'Diagnóstico general' }}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt class="inline font-semibold text-gray-700 dark:text-gray-300">
                                                    Fecha de ingreso:
                                                </dt>

                                                <dd class="inline text-gray-600 dark:text-gray-400">
                                                    {{ $orden->fecha_ingreso->format('d/m/Y') }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <form
                                        action="{{ route('operacion.ordenes.asignar', ['orden' => $orden->id]) }}"
                                        method="POST"
                                    >
                                        @csrf

                                        <div>
                                            <label
                                                for="empleado-{{ $orden->id }}"
                                                class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                                            >
                                                Empleado responsable
                                            </label>

                                            <select
                                                id="empleado-{{ $orden->id }}"
                                                name="empleado_id"
                                                required
                                                class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                                @disabled($empleados->isEmpty())
                                            >
                                                <option value="">
                                                    Selecciona un empleado
                                                </option>

                                                @foreach ($empleados as $empleado)
                                                    <option value="{{ $empleado->id }}">
                                                        {{ $empleado->name }}
                                                        · Carga activa: {{ $empleado->carga_activa }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                for="observaciones-{{ $orden->id }}"
                                                class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                                            >
                                                Observaciones
                                            </label>

                                            <textarea
                                                id="observaciones-{{ $orden->id }}"
                                                name="observaciones"
                                                rows="3"
                                                maxlength="2000"
                                                class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                                placeholder="Indicaciones internas para el empleado."
                                            ></textarea>
                                        </div>

                                        <button
                                            type="submit"
                                            class="rounded-lg bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            @disabled($empleados->isEmpty())
                                        >
                                            Asignar reparación
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section
                id="panel-asignadas"
                data-supervisor-panel="asignadas"
                role="tabpanel"
                aria-labelledby="tab-asignadas"
                class="supervisor-panel hidden rounded-xl bg-white p-6 shadow dark:bg-zinc-900"
            >
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            Reparaciones asignadas
                        </h3>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            El supervisor puede cambiar al empleado responsable cuando sea necesario.
                        </p>
                    </div>

                    <span class="rounded-full bg-green-100 px-4 py-2 text-sm font-semibold text-green-700 dark:bg-green-950 dark:text-green-200">
                        {{ $ordenesAsignadas->count() }} mostradas
                    </span>
                </div>

                @if ($ordenesAsignadas->isEmpty())
                    <div class="mt-6 rounded-lg bg-gray-100 p-6 text-center text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                        Todavía no existen reparaciones asignadas.
                    </div>
                @else
                    <div class="mt-6 space-y-5">
                        @foreach ($ordenesAsignadas as $orden)
                            <article class="rounded-xl border border-gray-200 p-5 dark:border-zinc-700">
                                <div class="grid gap-5 lg:grid-cols-2">
                                    <div>
                                        <p class="text-lg font-bold text-purple-500">
                                            {{ $orden->folio }}
                                        </p>

                                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                            Empleado actual:
                                            <span class="font-bold text-green-500">
                                                {{ $orden->asignacionActiva?->empleado?->name ?? 'No disponible' }}
                                            </span>
                                        </p>

                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            Equipo:
                                            {{ $orden->equipo->marca }}
                                            {{ $orden->equipo->modelo }}
                                        </p>

                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            Estado:
                                            {{ $orden->estado }}
                                        </p>
                                    </div>

                                    <form
                                        method="POST"
                                        action="{{ route('operacion.ordenes.asignar', ['orden' => $orden->id]) }}"
                                        class="space-y-4"
                                    >
                                        @csrf

                                        <div>
                                            <label
                                                for="reasignar-empleado-{{ $orden->id }}"
                                                class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                                            >
                                                Reasignar a
                                            </label>

                                            <select
                                                id="reasignar-empleado-{{ $orden->id }}"
                                                name="empleado_id"
                                                required
                                                class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                            >
                                                <option value="">
                                                    Selecciona un empleado
                                                </option>

                                                @foreach ($empleados as $empleado)
                                                    <option
                                                        value="{{ $empleado->id }}"
                                                        @selected(
                                                            $orden->asignacionActiva?->empleado_id
                                                                === $empleado->id
                                                        )
                                                    >
                                                        {{ $empleado->name }}
                                                        · Carga activa: {{ $empleado->carga_activa }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                for="reasignar-observaciones-{{ $orden->id }}"
                                                class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                                            >
                                                Motivo u observaciones
                                            </label>

                                            <textarea
                                                id="reasignar-observaciones-{{ $orden->id }}"
                                                name="observaciones"
                                                rows="3"
                                                maxlength="2000"
                                                class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                                placeholder="Motivo de la reasignación."
                                            ></textarea>
                                        </div>

                                        <button
                                            type="submit"
                                            class="rounded-lg bg-amber-600 px-5 py-3 font-semibold text-white hover:bg-amber-700"
                                        >
                                            Guardar reasignación
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section
                id="panel-entregas"
                data-supervisor-panel="entregas"
                role="tabpanel"
                aria-labelledby="tab-entregas"
                class="supervisor-panel hidden rounded-xl bg-white p-6 shadow dark:bg-zinc-900"
            >
                <div
                    class="flex flex-col justify-between gap-3 md:flex-row md:items-center"
                >
                    <div>
                        <p
                            class="text-sm font-semibold uppercase tracking-wide text-emerald-500"
                        >
                            Entregas
                        </p>

                        <h3 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                            Equipos listos para entrega
                        </h3>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Confirma la entrega física del equipo al cliente.
                        </p>
                    </div>

                    <span
                        class="rounded-full bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200"
                    >
                        {{ $ordenesListasEntrega->count() }} pendientes
                    </span>
                </div>

                @if ($ordenesListasEntrega->isEmpty())
                    <div
                        class="mt-6 rounded-lg bg-gray-100 p-6 text-center text-gray-600 dark:bg-zinc-800 dark:text-gray-300"
                    >
                        No existen equipos pendientes de entrega.
                    </div>
                @else
                    <div class="mt-6 grid gap-5 xl:grid-cols-2">
                        @foreach ($ordenesListasEntrega as $orden)
                            @can('deliver', $orden)
                                <article
                                    class="rounded-xl border border-emerald-800 bg-emerald-950/40 p-5"
                                >
                                    <div
                                        class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"
                                    >
                                        <div>
                                            <p class="text-lg font-bold text-emerald-300">
                                                {{ $orden->folio }}
                                            </p>

                                            <p class="mt-1 text-sm text-emerald-100">
                                                {{ $orden->user?->name ?? 'Cliente no disponible' }}
                                            </p>
                                        </div>

                                        <span
                                            class="w-fit rounded-full bg-emerald-900 px-3 py-1 text-xs font-semibold text-emerald-100"
                                        >
                                            {{ $orden->estado }}
                                        </span>
                                    </div>

                                    <dl class="mt-5 grid gap-3 sm:grid-cols-3">
                                        <div class="rounded-lg bg-zinc-900 p-3">
                                            <dt
                                                class="text-xs font-semibold uppercase text-zinc-400"
                                            >
                                                Equipo
                                            </dt>

                                            <dd class="mt-1 text-sm font-medium text-white">
                                                {{ $orden->equipo?->marca }}
                                                {{ $orden->equipo?->modelo }}
                                            </dd>
                                        </div>

                                        <div class="rounded-lg bg-zinc-900 p-3">
                                            <dt
                                                class="text-xs font-semibold uppercase text-zinc-400"
                                            >
                                                Costo final
                                            </dt>

                                            <dd class="mt-1 text-sm font-medium text-white">
                                                ${{ number_format(
                                                    (float) $orden->costo_final,
                                                    2
                                                ) }}
                                            </dd>
                                        </div>

                                        <div class="rounded-lg bg-zinc-900 p-3">
                                            <dt
                                                class="text-xs font-semibold uppercase text-zinc-400"
                                            >
                                                Ingreso
                                            </dt>

                                            <dd class="mt-1 text-sm font-medium text-white">
                                                {{ $orden->fecha_ingreso->format('d/m/Y') }}
                                            </dd>
                                        </div>
                                    </dl>

                                    <form method="POST"
                                        action="{{ route('operacion.ordenes.entregar', ['orden' => $orden->id]) }}"
                                        class="space-y-4">
                                        @csrf

                                        <div>
                                            <label
                                                for="comentario-entrega-{{ $orden->id }}"
                                                class="block text-sm font-semibold text-emerald-100"
                                            >
                                                Comentario de entrega opcional
                                            </label>

                                            <textarea
                                                id="comentario-entrega-{{ $orden->id }}"
                                                name="comentario"
                                                rows="3"
                                                maxlength="2000"
                                                class="mt-2 w-full rounded-lg border border-emerald-700 bg-white p-3 text-gray-900 placeholder:text-gray-400 focus:border-emerald-500 focus:ring-emerald-500"
                                                placeholder="Ejemplo: equipo recibido y revisado por el cliente."
                                            ></textarea>
                                        </div>

                                        <button
                                            type="submit"
                                            class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white transition hover:bg-emerald-700"
                                            onclick="return confirm('¿Confirmas que el equipo fue entregado al cliente?');"
                                        >
                                            Confirmar entrega
                                        </button>
                                    </form>
                                </article>
                            @endcan
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const pestañas = Array.from(
                document.querySelectorAll('[data-supervisor-tab]')
            );

            const paneles = Array.from(
                document.querySelectorAll('[data-supervisor-panel]')
            );

            const nombresPermitidos = pestañas.map(function (pestaña) {
                return pestaña.dataset.supervisorTab;
            });

            function nombreInicial() {
                const hash = window.location.hash.replace('#', '');

                if (nombresPermitidos.includes(hash)) {
                    return hash;
                }

                return 'cotizaciones';
            }

            function ajustarTablaCotizaciones() {
                if (
                    typeof window.jQuery === 'undefined'
                    || ! window.jQuery.fn.dataTable
                ) {
                    return;
                }

                const tabla = window.jQuery(
                    '#tab-cotizaciones'
                );

                if (
                    tabla.length > 0
                    && window.jQuery.fn.dataTable.isDataTable(tabla)
                ) {
                    tabla.DataTable()
                        .columns
                        .adjust()
                        .draw(false);
                }
            }

            function mostrarPanel(nombre) {
                paneles.forEach(function (panel) {
                    const activo =
                        panel.dataset.supervisorPanel === nombre;

                    panel.classList.toggle('hidden', ! activo);
                    panel.hidden = ! activo;
                });

                pestañas.forEach(function (pestaña) {
                    const activa =
                        pestaña.dataset.supervisorTab === nombre;

                    pestaña.setAttribute(
                        'aria-selected',
                        activa ? 'true' : 'false'
                    );

                    pestaña.setAttribute(
                        'tabindex',
                        activa ? '0' : '-1'
                    );

                    pestaña.classList.toggle(
                        'bg-purple-600',
                        activa
                    );

                    pestaña.classList.toggle(
                        'text-white',
                        activa
                    );

                    pestaña.classList.toggle(
                        'bg-zinc-800',
                        ! activa
                    );

                    pestaña.classList.toggle(
                        'text-zinc-300',
                        ! activa
                    );

                    pestaña.classList.toggle(
                        'hover:bg-zinc-700',
                        ! activa
                    );
                });

                window.history.replaceState(
                    null,
                    '',
                    window.location.pathname
                        + window.location.search
                        + '#'
                        + nombre
                );

                if (nombre === 'cotizaciones') {
                    window.setTimeout(
                        ajustarTablaCotizaciones,
                        50
                    );
                }
            }

            pestañas.forEach(function (pestaña, indice) {
                pestaña.addEventListener('click', function () {
                    mostrarPanel(
                        pestaña.dataset.supervisorTab
                    );
                });

                pestaña.addEventListener(
                    'keydown',
                    function (evento) {
                        if (
                            evento.key !== 'ArrowRight'
                            && evento.key !== 'ArrowLeft'
                        ) {
                            return;
                        }

                        evento.preventDefault();

                        const desplazamiento =
                            evento.key === 'ArrowRight'
                                ? 1
                                : -1;

                        const siguienteIndice =
                            (
                                indice
                                + desplazamiento
                                + pestañas.length
                            ) % pestañas.length;

                        const siguiente = pestañas[
                            siguienteIndice
                        ];

                        mostrarPanel(
                            siguiente.dataset.supervisorTab
                        );

                        siguiente.focus();
                    }
                );
            });

            mostrarPanel(nombreInicial());
        });
    </script>
</x-app-layout>
