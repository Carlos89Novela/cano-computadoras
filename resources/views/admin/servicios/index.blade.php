<x-app-layout>

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Servicios y precios
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Administra el catálogo público de servicios.
                </p>
            </div>

             <a
                href="{{ route('admin.servicios.create') }}"
                class="inline-block rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
            >
                Nuevo servicio
            </a>

        </div>
    </x-slot>

    <div class="bg-gray-950 py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-6">

            @if (session('success'))
                <div class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @php
                $serviciosActivos = $servicios->where('activo', true)->count();
                $serviciosInactivos = $servicios->where('activo', false)->count();
                $precioPromedio = $servicios->avg('precio') ?? 0;
            @endphp

            <section class="overflow-visible rounded-xl border border-zinc-700 bg-zinc-900 p-6 shadow-xl">
                <div class="mb-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-purple-300">
                        Resumen del catálogo
                    </p>
                    <h3 class="mt-2 text-xl font-bold text-white">
                        Estado general de servicios
                    </h3>
                </div>

                <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-zinc-800 bg-zinc-950 p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-400">Total</p>
                            <p class="mt-2 text-2xl font-bold text-white">{{ $servicios->count() }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-emerald-700/40 bg-emerald-950/60 p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-emerald-300">Servicios activos</p>
                            <p class="mt-2 text-2xl font-bold text-emerald-100">{{ $serviciosActivos }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-red-700/40 bg-red-950/60 p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-red-300">Inactivos</p>
                            <p class="mt-2 text-2xl font-bold text-red-100">{{ $serviciosInactivos }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-violet-700/40 bg-violet-950/60 p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-violet-300">Precio promedio</p>
                            <p class="mt-2 text-2xl font-bold text-violet-100">${{ number_format((float) $precioPromedio, 2) }}</p>
                        </div>
                    </div>
                </div>

                @if ($servicios->isEmpty())
                    <div class="rounded-xl bg-zinc-950 p-10 text-center">
                        <h3 class="text-xl font-bold text-white">
                            No existen servicios registrados
                        </h3>

                        <p class="mt-2 text-gray-400">
                            Agrega el primer servicio para que aparezca en el catálogo público.
                        </p>
                    </div>
                @else
                    <div class="overflow-hidden rounded-xl bg-zinc-950 shadow-xl">
                        <div class="overflow-x-auto">
                            <table id="servicios-table" class="w-full text-left table-auto">
                                <thead class="bg-zinc-800 text-white">
                                    <tr>
                                        <th class="p-4">ID</th>
                                        <th class="p-4">Servicio</th>
                                        <th class="p-4">Precio</th>
                                        <th class="p-4">Estado</th>
                                        <th class="p-4">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($servicios as $servicio)
                                        <tr class="border-b border-zinc-800 text-zinc-200 hover:bg-zinc-800 transition-colors">
                                            <td class="p-4 text-zinc-400">#{{ $servicio->id }}</td>
                                            <td class="p-4">
                                                <div class="font-semibold text-white">{{ $servicio->nombre }}</div>
                                                @if ($servicio->descripcion)
                                                    <p class="mt-1 max-w-md text-sm text-zinc-400">
                                                        {{ Str::limit($servicio->descripcion, 110) }}
                                                    </p>
                                                @endif
                                            </td>
                                            <td class="p-4 font-medium text-purple-200">
                                                ${{ number_format($servicio->precio, 2) }}
                                            </td>
                                            <td class="p-4">
                                                @if ($servicio->activo)
                                                    <span class="inline-flex rounded-full bg-emerald-900/80 px-3 py-1 text-xs font-semibold text-emerald-200">
                                                        Activo
                                                    </span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-red-900/80 px-3 py-1 text-xs font-semibold text-red-200">
                                                        Inactivo
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="p-4">
                                                @include('admin.servicios.partials.acciones', ['servicio' => $servicio])
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <script>
                                (function waitForDT(fn){
                                    if (window.jQuery && $.fn.DataTable) return fn();
                                    setTimeout(function(){ waitForDT(fn); }, 50);
                                })(function(){
                                    $('#servicios-table').DataTable({
                                        pageLength: 10,
                                        order: [[1, 'asc']],
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
            </section>

        </div>
    </div>

</x-app-layout>