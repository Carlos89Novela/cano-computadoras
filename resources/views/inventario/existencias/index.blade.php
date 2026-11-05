<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-white">
                    Existencias y Control de Stock
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    Empresa: <span class="font-medium text-purple-300">{{ $empresa->nombre }}</span> | Consulta de saldos por almacén
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="rounded-lg bg-zinc-800 px-3.5 py-2 text-xs font-semibold text-zinc-300 transition hover:bg-zinc-700">
                ← Volver al panel
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">

                <!-- Filtros superiores -->
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-white">Saldos Físicos y Valuación</h3>
                        <p class="text-xs text-zinc-400">Valuación bajo Costo Promedio Ponderado.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <label for="filtro-almacen" class="text-xs text-zinc-400 font-medium">Almacén:</label>
                        <select id="filtro-almacen" onchange="recargarTabla()"
                            class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-xs text-white focus:border-purple-500 focus:outline-none">
                            <option value="">-- Todos los almacenes autorizados --</option>
                            @foreach($almacenes as $alm)
                                <option value="{{ $alm->id }}">{{ $alm->nombre }} ({{ $alm->codigo }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="tabla-existencias" class="w-full text-left text-sm text-zinc-300 stripe hover">
                        <thead class="border-b border-zinc-800 text-xs uppercase tracking-wider text-zinc-400 bg-zinc-900/60">
                            <tr>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3">Almacén / Sucursal</th>
                                <th class="px-4 py-3 text-center">Físico</th>
                                <th class="px-4 py-3 text-center">Reservado</th>
                                <th class="px-4 py-3 text-center">Disponible</th>
                                <th class="px-4 py-3 text-right">Costo Promedio</th>
                                <th class="px-4 py-3 text-right">Valor Total</th>
                                <th class="px-4 py-3 text-center">Kardex</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let tablaExistencias = null;

        $(document).ready(function () {
            tablaExistencias = $('#tabla-existencias').DataTable({
                ajax: {
                    url: "{{ route('inventario.existencias.data', $empresa) }}",
                    data: function (d) {
                        d.almacen_id = $('#filtro-almacen').val();
                    }
                },
                columns: [
                    { data: 'sku', className: 'px-4 py-3 font-mono font-semibold text-purple-300' },
                    { data: 'producto', className: 'px-4 py-3 text-white font-medium' },
                    {
                        data: null,
                        className: 'px-4 py-3 text-xs',
                        render: function (row) {
                            return `<span class="block text-zinc-200">${row.almacen}</span><span class="text-[11px] text-zinc-500">${row.sucursal}</span>`;
                        }
                    },
                    { data: 'cantidad', className: 'px-4 py-3 text-center font-mono text-zinc-200' },
                    { data: 'reservada', className: 'px-4 py-3 text-center font-mono text-amber-300' },
                    { data: 'disponible', className: 'px-4 py-3 text-center font-mono font-bold text-emerald-400' },
                    { data: 'costo_promedio', className: 'px-4 py-3 text-right font-mono text-zinc-300' },
                    { data: 'valor_total', className: 'px-4 py-3 text-right font-mono text-purple-300 font-medium' },
                    {
                        data: 'url_kardex',
                        className: 'px-4 py-3 text-center',
                        orderable: false,
                        render: function (url) {
                            return `<a href="${url}" class="rounded bg-zinc-800 hover:bg-zinc-700 px-2.5 py-1 text-xs text-zinc-300 border border-zinc-700/60 transition">
                                Ver Kardex
                            </a>`;
                        }
                    }
                ],
                order: [[1, 'asc']],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true
            });
        });

        function recargarTabla() {
            tablaExistencias.ajax.reload();
        }
    </script>
    @endpush
</x-app-layout>
