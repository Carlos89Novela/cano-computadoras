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

            @if ($ordenes->isEmpty())
                <div class="rounded-xl bg-white p-10 text-center shadow dark:bg-zinc-900">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        No existen órdenes de reparación
                    </h3>

                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Las solicitudes realizadas por los clientes aparecerán aquí.
                    </p>
                </div>
            @else
                <div class="overflow-hidden rounded-xl bg-white shadow dark:bg-zinc-900">
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
                                $('#ordenes-table').DataTable({
                                    serverSide: true,
                                    processing: true,
                                    ajax: {
                                        url: '{{ route('admin.ordenes.data') }}',
                                        type: 'GET'
                                    },
                                    columns: [
                                        { data: 'folio', name: 'folio' },
                                        { data: 'cliente', name: 'cliente' },
                                        { data: 'equipo', name: 'equipo' },
                                        { data: 'estado', name: 'estado' },
                                        { data: 'fecha_ingreso', name: 'fecha_ingreso' },
                                        { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
                                    ],
                                    pageLength: 10,
                                    responsive: true,
                                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                                    dom: "<'flex items-center justify-between mb-3'<'flex items-center' l><'flex items-center' f>>t<'flex items-center justify-between mt-3'<'text-sm'i><'pagination'p>>",
                                    initComplete: function () {
                                        var lengthSel = $(this).closest('.dataTables_wrapper').find('select');
                                        lengthSel.addClass('rounded-md bg-zinc-900 border border-zinc-700 text-white px-2 py-1');

                                        var searchInput = $(this).closest('.dataTables_wrapper').find('input[type="search"]');
                                        searchInput.addClass('rounded-md bg-zinc-900 border border-zinc-700 text-white px-3 py-2');

                                        var paginate = $(this).closest('.dataTables_wrapper').find('.dataTables_paginate');
                                        paginate.find('a').addClass('mx-1 inline-flex items-center rounded-md bg-transparent border border-zinc-700 px-3 py-1 text-sm text-white');
                                        paginate.find('a.current').addClass('bg-purple-600 text-white border-transparent');
                                    }
                                });
                            });
                        </script>

                    </div>
                </div>
            @endif

        </div>
    </div>

</x-app-layout>