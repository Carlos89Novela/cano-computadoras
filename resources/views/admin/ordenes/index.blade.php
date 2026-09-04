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

            <div class="admin-toolbar no-print mb-6 overflow-hidden rounded-2xl bg-white shadow dark:bg-zinc-900">
                <div class="border-b border-zinc-700 bg-zinc-900/80 p-5">
                    <div class="space-y-5">
                        <div class="grid gap-4 lg:grid-cols-[minmax(220px,280px)_minmax(320px,1fr)_auto] lg:items-end">
                            <div>
                                <label
                                    for="estado-filtro"
                                    class="mb-2 block text-sm font-medium text-zinc-300"
                                >
                                    Filtro por estado
                                </label>

                                <select
                                    id="estado-filtro"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-sm text-white focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                                >
                                    <option value="all">Todos los estados</option>

                                    @foreach ($estados as $estado)
                                        <option value="{{ $estado }}">
                                            {{ $estado }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label
                                    for="ordenes-search"
                                    class="mb-2 block text-sm font-medium text-zinc-300"
                                >
                                    Buscar orden
                                </label>

                                <input
                                    id="ordenes-search"
                                    type="search"
                                    placeholder="Buscar por folio, cliente o equipo"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-sm text-white placeholder:text-zinc-500 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                                >
                            </div>

                            <div class="flex flex-wrap gap-2 lg:justify-end">
                                <button
                                    id="export-csv"
                                    type="button"
                                    class="rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-900/25 transition hover:-translate-y-0.5 hover:bg-emerald-500"
                                >
                                    Exportar CSV
                                </button>

                                <button
                                    id="export-pdf"
                                    type="button"
                                    class="rounded-lg border border-red-600 bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-900/25 transition hover:-translate-y-0.5 hover:bg-red-500"
                                >
                                    Exportar PDF
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-zinc-800 pt-4">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                Filtros rápidos
                            </p>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="estado-chip rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-200 transition hover:border-purple-500 hover:text-white data-[active=true]:border-purple-500 data-[active=true]:bg-purple-600 data-[active=true]:text-white"
                                    data-status="all"
                                    data-active="true"
                                >
                                    Todos
                                </button>

                                @foreach ($estadosRapidos as $valorEstado => $etiquetaEstado)
                                    <button
                                        type="button"
                                        class="estado-chip rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-200 transition hover:border-purple-500 hover:text-white data-[active=true]:border-purple-500 data-[active=true]:bg-purple-600 data-[active=true]:text-white"
                                        data-status="{{ $valorEstado }}"
                                        data-active="false"
                                    >
                                        {{ $etiquetaEstado }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div
                            id="bulk-actions-container"
                            class="border-t border-zinc-800 pt-4"
                        ></div>
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
                                    <th class="p-4"><input id="ordenes-select-all" type="checkbox" class="h-4 w-4" aria-label="Seleccionar todas las órdenes visibles" /></th>
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
                            const estadosOrden = {{ Illuminate\Support\Js::from($estados) }};
                            (function waitForDT(fn){
                                if (window.jQuery && $.fn.DataTable) return fn();
                                setTimeout(function(){ waitForDT(fn); }, 50);
                            })(function(){
                                var statusBadges = {{ Illuminate\Support\Js::from(
                                    collect(config('ordenes.clases_estado', []))
                                        ->map(fn (array $clases): string => $clases['badge'])
                                        ->all()
                                ) }};

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
                                        { data: 'select', name: 'select', orderable: false, searchable: false },
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
                                    language: {
                                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                                        processing: '<span class="table-loading" role="status" aria-live="polite">Cargando órdenes...</span>'
                                    },
                                    dom: "<'flex items-center justify-between mb-3'<'flex items-center' l>><'table-wrap't><'flex items-center justify-between mt-3'<'text-sm'i><'pagination'p>>",
                                    columnDefs: [
                                        {
                                            targets: 4,
                                            render: function(data, type, row) {
                                                if (type === 'display') {
                                                    var estado = row.estado || data;
                                                    var classes = statusBadges[estado]
                                                        || @js(config(
                                                            'ordenes.clase_estado_desconocido.badge',
                                                            'border border-slate-600 bg-slate-700 text-slate-200'
                                                        ));
                                                    return '<span class="' + classes + '">'
                                                        + estado
                                                        + '</span>';
                                                }
                                                return data;
                                            }
                                        },
                                        {
                                            targets: 6,
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

                                    // Selection and bulk actions
                                    $('#ordenes-select-all').on('change', function () {
                                        var checked = $(this).is(':checked');
                                        $('.orden-select').prop('checked', checked);
                                    });

                                    $(document).on('change', '.orden-select', function () {
                                        var all = $('.orden-select').length === $('.orden-select:checked').length;
                                        $('#ordenes-select-all').prop('checked', all);
                                    });

                                    function selectedIds() {
                                        return $('.orden-select:checked').map(function () { return $(this).data('id'); }).get();
                                    }

                                    // Bulk action UI
                                    var bulkToolbar = $('<div>', {
                                        class: 'flex flex-wrap items-center gap-2'
                                    });

                                    var bulkEstado = $('<select>', {
                                        id: 'bulk-estado',
                                        class: 'rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white'
                                    });

                                    bulkEstado.append(
                                        $('<option>', {
                                            value: '',
                                            text: 'Cambiar estado...'
                                        })
                                    );

                                    estadosOrden.forEach(function (estado) {
                                        bulkEstado.append(
                                            $('<option>', {
                                                value: estado,
                                                text: estado
                                            })
                                        );
                                    });

                                    var bulkApply = $('<button>', {
                                        id: 'bulk-apply',
                                        type: 'button',
                                        class: 'rounded-xl border border-purple-600 bg-purple-600 px-3 py-2 text-sm font-medium text-white',
                                        text: 'Aplicar'
                                    });

                                    var bulkClear = $('<button>', {
                                        id: 'bulk-clear',
                                        type: 'button',
                                        class: 'rounded-xl border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm font-medium text-white',
                                        text: 'Limpiar'
                                    });

                                    bulkToolbar.append(
                                        bulkEstado,
                                        bulkApply,
                                        bulkClear
                                    );

                                    $('#bulk-actions-container').append(bulkToolbar);

                                    $('#bulk-clear').on('click', function () {
                                        $('.orden-select').prop('checked', false);
                                        $('#ordenes-select-all').prop('checked', false);
                                    });

                                    $('#bulk-apply').on('click', function () {
                                        var ids = selectedIds();
                                        var estado = $('#bulk-estado').val();
                                        if (!ids.length) { window.showToast('Selecciona al menos una orden.', 'error'); return; }
                                        if (!estado) { window.showToast('Selecciona un estado.', 'error'); return; }

                                        $.ajax({
                                            url: '{{ route('admin.ordenes.bulk_update') }}',
                                            method: 'POST',
                                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                                            data: { ids: ids, estado: estado },
                                            success: function (res) {
                                                window.showToast('Se actualizaron ' + (res.updated || 0) + ' órdenes.', 'success');
                                                table.draw(false);
                                                $('#bulk-clear').click();
                                            },
                                            error: function (err) {
                                                window.showToast('Error al actualizar.', 'error');
                                            }
                                        });
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
