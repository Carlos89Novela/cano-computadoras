<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-green-500">
                Área técnica
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Panel del empleado
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-6">
            <section>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                    Bienvenido, {{ $usuario->name }}
                </h3>

                <p class="mt-2 text-gray-600 dark:text-gray-300">
                    Consulta y actualiza únicamente las reparaciones
                    que te hayan sido asignadas.
                </p>
            </section>

            <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        Reparaciones asignadas
                    </p>

                    <p class="mt-3 text-4xl font-bold text-green-500">
                        {{ $reparacionesAsignadas }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        En proceso
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $reparacionesEnProceso }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        En pruebas
                    </p>

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $reparacionesEnPruebas }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500">
                        Devueltas para corrección
                    </p>

                    <p class="mt-3 text-4xl font-bold text-red-500">
                        {{ $cierresDevueltos }}
                    </p>
                </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            Mis reparaciones
                        </h3>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Consulta únicamente las reparaciones que tienes asignadas.
                        </p>
                    </div>

                    <div class="w-full md:max-w-sm">
                        <label
                            for="empleado-ordenes-search"
                            class="sr-only"
                        >
                            Buscar en mis reparaciones
                        </label>

                        <input
                            id="empleado-ordenes-search"
                            type="search"
                            placeholder="Buscar por folio, cliente, equipo o estado"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-3 text-white placeholder:text-zinc-500 focus:border-green-500 focus:ring-green-500"
                        >
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table
                        id="empleado-ordenes-table"
                        class="w-full table-auto text-left"
                    >
                        <thead class="bg-zinc-800 text-white">
                            <tr>
                                <th class="p-4">
                                    Folio
                                </th>

                                <th class="p-4">
                                    Cliente
                                </th>

                                <th class="p-4">
                                    Equipo
                                </th>

                                <th class="p-4">
                                    Servicio
                                </th>

                                <th class="p-4">
                                    Estado
                                </th>

                                <th class="p-4">
                                    Fecha de asignación
                                </th>

                                <th class="p-4">
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
                (function waitForEmployeeDataTable(callback) {
                    if (
                        window.jQuery
                        && window.jQuery.fn
                        && window.jQuery.fn.DataTable
                    ) {
                        callback();

                        return;
                    }

                    window.setTimeout(function () {
                        waitForEmployeeDataTable(callback);
                    }, 50);
                })(function () {
                    var tableElement = window.jQuery(
                        '#empleado-ordenes-table'
                    );

                    if (
                        window.jQuery.fn.DataTable.isDataTable(
                            '#empleado-ordenes-table'
                        )
                    ) {
                        return;
                    }

                    var table = tableElement.DataTable({
                        serverSide: true,
                        processing: true,
                        responsive: true,
                        pageLength: 10,
                        order: [
                            [5, 'desc']
                        ],
                        ajax: {
                            url: @json(route('empleado.ordenes.data')),
                            type: 'GET'
                        },
                        columns: [
                            {
                                data: 'folio',
                                name: 'folio'
                            },
                            {
                                data: 'cliente',
                                name: 'cliente'
                            },
                            {
                                data: 'equipo',
                                name: 'equipo'
                            },
                            {
                                data: 'servicio',
                                name: 'servicio'
                            },
                            {
                                data: 'estado',
                                name: 'estado'
                            },
                            {
                                data: 'fecha_asignacion',
                                name: 'fecha_asignacion'
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
                            processing: 'Cargando reparaciones asignadas...',
                            emptyTable: 'No tienes reparaciones asignadas.',
                            zeroRecords: 'No se encontraron reparaciones con ese criterio.'
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

                                    return '<span class="inline-flex rounded-full bg-purple-950 px-3 py-1 text-xs font-semibold text-purple-200">'
                                        + data
                                        + '</span>';
                                }
                            },
                            {
                                targets: 6,
                                className: 'text-center'
                            }
                        ]
                    });

                    window.jQuery('#empleado-ordenes-search')
                        .on('input', function () {
                            table.search(this.value).draw();
                        });
                });
            </script>
        </div>
    </div>
</x-app-layout>
