<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                Catálogo
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Productos de {{ $empresa->nombre }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-6">
            {{-- -------------------------------------------------
                ENCABEZADO
                Presenta el catálogo y permite registrar productos.
            -------------------------------------------------- --}}

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                            Productos
                        </p>

                        <h3 class="mt-2 text-xl font-bold text-gray-900 dark:text-white">
                            Catálogo de productos
                        </h3>

                        <p class="mt-2 text-sm text-gray-500 dark:text-zinc-400">
                            Busca y consulta los productos registrados para esta empresa.
                        </p>
                    </div>

                    @can('productos.crear')
                        <a
                            href="{{ route('productos.productos.create', [
                                'empresa' => $empresa->id,
                            ]) }}"
                            class="inline-flex w-fit shrink-0 self-start items-center gap-2 whitespace-nowrap rounded-md border border-transparent bg-purple-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 lg:self-auto"
                        >
                            Crear producto
                        </a>
                    @endcan
                </div>
            </section>

            {{-- -------------------------------------------------
                FILTROS
                Permite buscar por texto, categoría y estado.
            -------------------------------------------------- --}}

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label
                            for="productos-search"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-200"
                        >
                            Buscar
                        </label>

                        <input
                            id="productos-search"
                            type="search"
                            class="admin-form-control"
                            placeholder="SKU, nombre, marca, modelo o código"
                        >
                    </div>

                    <div>
                        <label
                            for="categoria-filtro"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-200"
                        >
                            Categoría
                        </label>

                        <select
                            id="categoria-filtro"
                            class="admin-form-control"
                        >
                            <option value="">
                                Todas las categorías
                            </option>

                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->id }}">
                                    {{ $categoria->nombre }}

                                    @if (! $categoria->activo)
                                        (Inactiva)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label
                            for="estado-filtro"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-200"
                        >
                            Estado
                        </label>

                        <select
                            id="estado-filtro"
                            class="admin-form-control"
                        >
                            <option value="all">
                                Todos
                            </option>

                            <option value="activo">
                                Activos
                            </option>

                            <option value="inactivo">
                                Inactivos
                            </option>
                        </select>
                    </div>
                </div>
            </section>

            {{-- -------------------------------------------------
                DATATABLE
                Los productos serán cargados mediante AJAX.
            -------------------------------------------------- --}}

            <section class="admin-table-panel overflow-hidden rounded-2xl bg-white shadow dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table
                        id="productos-table"
                        class="w-full table-auto text-left"
                    >
                        <thead class="bg-zinc-800 text-white">
                            <tr>
                                <th class="p-4">
                                    SKU
                                </th>

                                <th class="p-4">
                                    Producto
                                </th>

                                <th class="p-4">
                                    Categoría
                                </th>

                                <th class="p-4">
                                    Proveedor
                                </th>

                                <th class="p-4">
                                    Unidad
                                </th>

                                <th class="p-4">
                                    Precio base
                                </th>

                                <th class="p-4">
                                    Estado
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            {{-- Los registros se cargarán mediante DataTables. --}}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // -------------------------------------------------
            // ESPERAR DATATABLES
            // La biblioteca se carga globalmente desde el layout.
            // -------------------------------------------------

            function iniciarCuandoDisponible(callback) {
                if (
                    window.jQuery
                    && window.jQuery.fn
                    && window.jQuery.fn.DataTable
                ) {
                    callback();

                    return;
                }

                window.setTimeout(function () {
                    iniciarCuandoDisponible(callback);
                }, 50);
            }

            iniciarCuandoDisponible(function () {
                const selectorTabla = '#productos-table';
                const tablaElemento = window.jQuery(selectorTabla);

                // Evita inicializar dos veces el mismo DataTable.
                if (
                    window.jQuery.fn.DataTable.isDataTable(
                        selectorTabla
                    )
                ) {
                    return;
                }

                // -------------------------------------------------
                // DATATABLE DE PRODUCTOS
                // Carga los productos mediante el endpoint JSON.
                // -------------------------------------------------

                const tabla = tablaElemento.DataTable({
                    serverSide: true,
                    processing: true,
                    responsive: true,
                    pageLength: 10,

                    order: [
                        [1, 'asc']
                    ],

                    ajax: {
                        url: @json(route('productos.productos.data', [
                            'empresa' => $empresa->id,
                        ])),

                        type: 'GET',

                        data: function (datos) {
                            datos.categoria_id = window.jQuery(
                                '#categoria-filtro'
                            ).val();

                            datos.estado = window.jQuery(
                                '#estado-filtro'
                            ).val();

                            datos.search = {
                                value: window.jQuery(
                                    '#productos-search'
                                ).val() || ''
                            };

                            return datos;
                        }
                    },

                    columns: [
                        {
                            data: 'sku',
                            name: 'sku'
                        },
                        {
                            data: 'nombre',
                            name: 'nombre'
                        },
                        {
                            data: 'categoria',
                            name: 'categoria'
                        },
                        {
                            data: 'proveedor',
                            name: 'proveedor'
                        },
                        {
                            data: 'unidad_medida',
                            name: 'unidad_medida'
                        },
                        {
                            data: 'precio_base',
                            name: 'precio_base',
                            render: function (valor, tipo) {
                                if (
                                    valor === null
                                    || valor === ''
                                ) {
                                    return tipo === 'display'
                                        ? 'Sin precio'
                                        : 0;
                                }

                                const precio = Number(valor);

                                if (tipo === 'display') {
                                    return precio.toLocaleString(
                                        'es-MX',
                                        {
                                            style: 'currency',
                                            currency: 'MXN',
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        }
                                    );
                                }

                                return precio;
                            }
                        },
                        {
                            data: 'estado',
                            name: 'estado',
                            render: function (valor, tipo) {
                                if (tipo !== 'display') {
                                    return valor;
                                }

                                if (valor === 'Activo') {
                                    return [
                                        '<span class="inline-flex rounded-full',
                                        ' bg-emerald-100 px-3 py-1 text-xs',
                                        ' font-semibold text-emerald-700',
                                        ' dark:bg-emerald-950',
                                        ' dark:text-emerald-300">',
                                        'Activo',
                                        '</span>'
                                    ].join('');
                                }

                                return [
                                    '<span class="inline-flex rounded-full',
                                    ' bg-red-100 px-3 py-1 text-xs',
                                    ' font-semibold text-red-700',
                                    ' dark:bg-red-950',
                                    ' dark:text-red-300">',
                                    'Inactivo',
                                    '</span>'
                                ].join('');
                            }
                        }
                    ],

                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                        processing: 'Cargando productos...',
                        emptyTable: 'No hay productos registrados.',
                        zeroRecords: 'No se encontraron productos con esos criterios.'
                    },

                    dom:
                        "<'flex items-center justify-between mb-3'"
                        + "<'flex items-center'l>>"
                        + "<'table-wrap't>"
                        + "<'flex items-center justify-between mt-3'"
                        + "<'text-sm'i><'pagination'p>>"
                });

                            // -------------------------------------------------
                // FILTROS
                // Redibuja la tabla al cambiar la categoría o el
                // estado seleccionado.
                // -------------------------------------------------

                window.jQuery(
                    '#categoria-filtro, #estado-filtro'
                ).on('change', function () {
                    tabla.draw();
                });

                // -------------------------------------------------
                // BÚSQUEDA CON RETARDO
                // Espera 300 milisegundos antes de consultar para
                // evitar una solicitud por cada pulsación rápida.
                // -------------------------------------------------

                let temporizadorBusqueda = null;

                window.jQuery(
                    '#productos-search'
                ).on('input', function () {
                    window.clearTimeout(
                        temporizadorBusqueda
                    );

                    temporizadorBusqueda = window.setTimeout(
                        function () {
                            tabla.draw();
                        },
                        300
                    );
                });
            });
        });
    </script>

</x-app-layout>
