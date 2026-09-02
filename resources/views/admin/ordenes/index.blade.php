<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Administrar reparaciones
            </h2>

            <span class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white">
                {{ $ordenes->count() }} órdenes
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-6">

            @if (session('success'))
                <div class="mb-6 rounded-lg border border-green-700 bg-green-950 p-4 text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            <div class="admin-toolbar mb-6 overflow-hidden rounded-2xl bg-white shadow dark:bg-zinc-900">
                <div class="p-4 border-b border-zinc-700 bg-zinc-900/80">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex flex-1 flex-col gap-3 xl:flex-row xl:items-center">
                            <label class="flex items-center gap-2 text-sm font-medium text-zinc-300" for="estado-filtro">
                                Filtro por estado
                            </label>
                            <select id="estado-filtro" class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-purple-500 focus:outline-none">
                                <option value="all">Todos</option>
                                <option value="Recibido">Recibido</option>
                                <option value="En diagnóstico">En diagnóstico</option>
                                <option value="Esperando autorización">Esperando autorización</option>
                                <option value="Esperando refacción">Esperando refacción</option>
                                <option value="En reparación">En reparación</option>
                                <option value="En pruebas">En pruebas</option>
                                <option value="Listo para entrega">Listo para entrega</option>
                                <option value="Entregado">Entregado</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>

                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="estado-chip rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-200 transition hover:border-purple-500 hover:text-white data-[active=true]:border-purple-500 data-[active=true]:bg-purple-600 data-[active=true]:text-white" data-status="all" data-active="true">Todos</button>
                                <button type="button" class="estado-chip rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-200 transition hover:border-purple-500 hover:text-white data-[active=true]:border-purple-500 data-[active=true]:bg-purple-600 data-[active=true]:text-white" data-status="Recibido" data-active="false">Recibido</button>
                                <button type="button" class="estado-chip rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-200 transition hover:border-purple-500 hover:text-white data-[active=true]:border-purple-500 data-[active=true]:bg-purple-600 data-[active=true]:text-white" data-status="En diagnóstico" data-active="false">En diagnóstico</button>
                                <button type="button" class="estado-chip rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-200 transition hover:border-purple-500 hover:text-white data-[active=true]:border-purple-500 data-[active=true]:bg-purple-600 data-[active=true]:text-white" data-status="En reparación" data-active="false">En reparación</button>
                                <button type="button" class="estado-chip rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-200 transition hover:border-purple-500 hover:text-white data-[active=true]:border-purple-500 data-[active=true]:bg-purple-600 data-[active=true]:text-white" data-status="Listo para entrega" data-active="false">Listo</button>
                                <button type="button" class="estado-chip rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-200 transition hover:border-purple-500 hover:text-white data-[active=true]:border-purple-500 data-[active=true]:bg-purple-600 data-[active=true]:text-white" data-status="Entregado" data-active="false">Entregado</button>
                            </div>
                        </div>

                        <div class="w-full max-w-md">
                            <label for="ordenes-search" class="mb-2 block text-sm font-medium text-zinc-300">
                                Buscar por folio, cliente o equipo
                            </label>
                            <div class="relative">
                                <input id="ordenes-search" type="search" placeholder="Buscar por folio, cliente o equipo" class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white placeholder:text-zinc-400 focus:border-purple-500 focus:outline-none" />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button id="export-csv" type="button" class="rounded-xl border border-emerald-600 bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-emerald-900/25 transition hover:-translate-y-0.5 hover:bg-emerald-500">
                                Exportar CSV
                            </button>
                            <button id="export-pdf" type="button" class="rounded-xl border border-red-600 bg-red-600 px-3 py-2 text-sm font-medium text-white shadow-lg shadow-red-900/25 transition hover:-translate-y-0.5 hover:bg-red-500">
                                Exportar PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($ordenes->isEmpty())
                <div class="rounded-xl bg-white p-10 text-center shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        No existen órdenes de reparación
                    </h3>

                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Las solicitudes realizadas por los clientes aparecerán aquí.
                    </p>

                    <div class="mt-4 inline-flex rounded-md border border-purple-600 bg-purple-600 px-3 py-2 text-sm font-medium text-white">
                        Ver detalle
                    </div>
                </div>
            @else
                <div class="admin-table-panel overflow-hidden rounded-2xl bg-white shadow dark:bg-zinc-900">
                    <div class="overflow-x-auto">

                        <!-- Use Tailwind for table styling; avoid DataTables default CSS -->
                        <table id="ordenes-table" class="w-full text-left table-auto">
                            <thead class="bg-zinc-800 text-white">
                                <tr>
                                    <th class="p-4">Folio</th>
                                    <th class="p-4">Cliente</th>
                                    <th class="p-4">Equipo</th>
                                    <th class="p-4">Estado</th>
                                    <th class="p-4">Fecha</th>
                                    <th class="p-4">Costo</th>
                                    <th class="p-4">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <!-- Data will be loaded via AJAX -->
                            </tbody>
                        </table>

                        <script>
                            (function waitForDT(fn){
                                if (window.jQuery && $.fn.DataTable) return fn();
                                setTimeout(function(){ waitForDT(fn); }, 50);
                            })(function(){
                                var statusBadges = {
                                    'Recibido': 'bg-sky-900 text-sky-200 border border-sky-700',
                                    'En diagnóstico': 'bg-cyan-900 text-cyan-200 border border-cyan-700',
                                    'Esperando autorización': 'bg-amber-900 text-amber-200 border border-amber-700',
                                    'Esperando refacción': 'bg-orange-900 text-orange-200 border border-orange-700',
                                    'En reparación': 'bg-violet-900 text-violet-200 border border-violet-700',
                                    'En pruebas': 'bg-indigo-900 text-indigo-200 border border-indigo-700',
                                    'Listo para entrega': 'bg-emerald-900 text-emerald-200 border border-emerald-700',
                                    'Entregado': 'bg-teal-900 text-teal-200 border border-teal-700',
                                    'Cancelado': 'bg-rose-900 text-rose-200 border border-rose-700'
                                };

                                var table = $('#ordenes-table').DataTable({
                                    serverSide: true,
                                    processing: true,
                                    ajax: {
                                        url: '{{ route('admin.ordenes.data') }}',
                                        type: 'GET',
                                        data: function (d) {
                                            d.estado = $('#estado-filtro').val();
                                            d.search = { value: $('#ordenes-search').val() || '' };
                                            return d;
                                        }
                                    },
                                    columns: [
                                        { data: 'folio', name: 'folio' },
                                        { data: 'cliente', name: 'cliente' },
                                        { data: 'equipo', name: 'equipo' },
                                        { data: 'estado', name: 'estado' },
                                        { data: 'fecha_ingreso', name: 'fecha_ingreso' },
                                        { data: 'costo_final', name: 'costo_final' },
                                        { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
                                    ],
                                    order: [[4, 'desc']],
                                    pageLength: 10,
                                    responsive: true,
                                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                                    dom: "<'flex items-center justify-between mb-3'<'flex items-center' l>><'table-wrap't><'flex items-center justify-between mt-3'<'text-sm'i><'pagination'p>>",
                                    initComplete: function () {
                                        var lengthSel = $(this).closest('.dataTables_wrapper').find('select');
                                        lengthSel.addClass('rounded-md bg-zinc-900 border border-zinc-700 text-white px-2 py-1');

                                        var paginate = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
                                        paginate.find('a').addClass('mx-1 inline-flex items-center rounded-md bg-transparent border border-zinc-700 px-3 py-1 text-sm text-white');
                                        paginate.find('a.current').addClass('bg-purple-600 text-white border-transparent');
                                    },
                                    columnDefs: [
                                        {
                                            targets: 3,
                                            render: function(data, type, row) {
                                                if (type === 'display') {
                                                    var estado = row.estado || data;
                                                    var classes = statusBadges[estado] || 'bg-slate-700 text-slate-200 border border-slate-600';
                                                    return '<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ' + classes + '">' + estado + '</span>';
                                                }
                                                return data;
                                            }
                                        },
                                        {
                                            targets: 5,
                                            render: function(data, type, row) {
                                                var cost = Number(data || 0);
                                                if (type === 'display') {
                                                    return '$' + cost.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                                }
                                                return cost;
                                            }
                                        }
                                    ]
                                });

                                function updateStatusChipState(selectedStatus) {
                                    $('.estado-chip').attr('data-active', 'false').removeClass('border-purple-500 bg-purple-600 text-white').addClass('border-zinc-700 bg-zinc-800 text-zinc-200');
                                    $('.estado-chip[data-status="' + selectedStatus + '"]').attr('data-active', 'true').removeClass('border-zinc-700 bg-zinc-800 text-zinc-200').addClass('border-purple-500 bg-purple-600 text-white');
                                }

                                $('#estado-filtro').on('change', function () {
                                    var selectedStatus = $(this).val();
                                    updateStatusChipState(selectedStatus || 'all');
                                    table.draw();
                                });

                                $('.estado-chip').on('click', function () {
                                    var selectedStatus = $(this).data('status');
                                    $('#estado-filtro').val(selectedStatus);
                                    updateStatusChipState(selectedStatus);
                                    table.draw();
                                });

                                $('#ordenes-search').on('input', function () {
                                    table.search($(this).val()).draw();
                                });

                                function sanitizeExportValue(value) {
                                    return String(value ?? '')
                                        .replace(/<[^>]*>/g, '')
                                        .replace(/\s+/g, ' ')
                                        .trim();
                                }

                                function buildExportFilename(prefix, extension) {
                                    var stamp = new Date().toISOString().slice(0, 10);
                                    var state = $('#estado-filtro').val();
                                    var suffix = state && state !== 'all' ? '-' + state.toLowerCase().replace(/[^a-z0-9]+/g, '-') : '';
                                    return prefix + suffix + '-' + stamp + '.' + extension;
                                }

                                $('#export-csv').on('click', function () {
                                    var url = new URL('{{ route('admin.ordenes.exportar.csv') }}');
                                    var estado = $('#estado-filtro').val();
                                    var search = $('#ordenes-search').val();
                                    if (estado && estado !== 'all') {
                                        url.searchParams.set('estado', estado);
                                    }
                                    if (search && search.trim() !== '') {
                                        url.searchParams.set('search', search.trim());
                                    }
                                    window.location.href = url.toString();
                                });

                                $('#export-pdf').on('click', function () {
                                    var url = new URL('{{ route('admin.ordenes.exportar.pdf') }}');
                                    var estado = $('#estado-filtro').val();
                                    var search = $('#ordenes-search').val();
                                    if (estado && estado !== 'all') {
                                        url.searchParams.set('estado', estado);
                                    }
                                    if (search && search.trim() !== '') {
                                        url.searchParams.set('search', search.trim());
                                    }
                                    window.location.href = url.toString();
                                });
                            });
                        </script>

                    </div>
                </div>
            @endif

        </div>
    </div>

</x-app-layout>