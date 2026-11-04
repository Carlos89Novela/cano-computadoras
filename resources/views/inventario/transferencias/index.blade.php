<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-white">
                    Transferencias entre Almacenes
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    Empresa: <span class="font-medium text-purple-300">{{ $empresa->nombre }}</span>
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('inventario.transferencias.create', $empresa) }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    + Nueva Transferencia
                </a>
                <a href="{{ route('dashboard') }}"
                   class="rounded-lg bg-zinc-800 px-3.5 py-2 text-xs font-semibold text-zinc-300 transition hover:bg-zinc-700">
                    ← Volver al panel
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-white">Bitácora de Reubicaciones y Transferencias</h3>
                        <p class="text-xs text-zinc-400">Movimientos de salida y entrada entre depósitos y sucursales con trazabilidad de costos.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="tabla-transferencias" class="w-full text-left text-sm text-zinc-300 stripe hover">
                        <thead class="border-b border-zinc-800 text-xs uppercase tracking-wider text-zinc-400 bg-zinc-900/60">
                            <tr>
                                <th class="px-4 py-3">Folio</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Origen</th>
                                <th class="px-4 py-3">Destino</th>
                                <th class="px-4 py-3 text-center">Líneas</th>
                                <th class="px-4 py-3 text-right">Valor Total</th>
                                <th class="px-4 py-3">Autorizado Por</th>
                                <th class="px-4 py-3 text-center">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalle -->
    <div id="modal-detalle" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/70 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="w-full max-w-2xl rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-purple-300 font-semibold">Detalle de Transferencia</p>
                        <h4 id="modal-folio" class="text-lg font-bold text-white mt-0.5">---</h4>
                    </div>
                    <button type="button" onclick="cerrarModalDetalle()" class="text-zinc-400 hover:text-white text-lg font-bold p-1">✕</button>
                </div>

                <div class="mt-4">
                    <div class="mb-3 rounded-lg bg-zinc-950 p-3 text-xs text-zinc-400" id="modal-motivo"></div>
                    <div class="overflow-x-auto max-h-72">
                        <table class="w-full text-left text-xs text-zinc-300">
                            <thead class="border-b border-zinc-800 bg-zinc-950/80 uppercase tracking-wider text-zinc-400">
                                <tr>
                                    <th class="px-3 py-2">Producto</th>
                                    <th class="px-3 py-2 text-right">Cantidad</th>
                                    <th class="px-3 py-2 text-right">Costo Unit.</th>
                                    <th class="px-3 py-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="modal-lineas-body" class="divide-y divide-zinc-800/60"></tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 flex justify-end border-t border-zinc-800 pt-3">
                    <button type="button" onclick="cerrarModalDetalle()" class="rounded-lg bg-zinc-800 px-4 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 transition">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let transferenciasCache = [];

        $(document).ready(function () {
            $('#tabla-transferencias').DataTable({
                ajax: {
                    url: "{{ route('inventario.transferencias.data', $empresa) }}",
                    dataSrc: function (json) {
                        transferenciasCache = json.data;
                        return json.data;
                    }
                },
                columns: [
                    {
                        data: 'folio',
                        className: 'px-4 py-3',
                        render: function (data) {
                            return `<span class="font-semibold text-white tracking-wide">${data}</span>`;
                        }
                    },
                    { data: 'transferido_at', className: 'px-4 py-3 whitespace-nowrap text-zinc-300' },
                    {
                        data: 'almacen_origen',
                        className: 'px-4 py-3 text-rose-300 font-medium'
                    },
                    {
                        data: 'almacen_destino',
                        className: 'px-4 py-3 text-emerald-300 font-medium'
                    },
                    {
                        data: 'total_lineas',
                        className: 'px-4 py-3 text-center',
                        render: function (data) {
                            return `<span class="rounded bg-zinc-900 px-2 py-0.5 text-xs text-zinc-300 border border-zinc-800">${data}</span>`;
                        }
                    },
                    {
                        data: 'costo_total',
                        className: 'px-4 py-3 text-right font-semibold text-purple-300 whitespace-nowrap'
                    },
                    { data: 'creado_por', className: 'px-4 py-3 text-zinc-300 whitespace-nowrap' },
                    {
                        data: null,
                        className: 'px-4 py-3 text-center',
                        orderable: false,
                        render: function (row) {
                            return `<button type="button" onclick="verDetalle(${row.id})"
                                class="rounded-lg bg-zinc-800 hover:bg-zinc-700 px-2.5 py-1 text-xs text-purple-300 font-semibold border border-zinc-700/60 transition whitespace-nowrap">
                                Ver líneas
                            </button>`;
                        }
                    }
                ],
                order: [[1, 'desc']],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true
            });
        });

        function verDetalle(id) {
            const tr = transferenciasCache.find(t => t.id === id);
            if (!tr) return;

            document.getElementById('modal-folio').innerText = tr.folio;
            document.getElementById('modal-motivo').innerHTML = `<strong>Motivo:</strong> ${tr.motivo}`;

            const tbody = document.getElementById('modal-lineas-body');
            tbody.innerHTML = '';

            tr.detalles.forEach(linea => {
                const fila = document.createElement('tr');
                fila.innerHTML = `
                    <td class="px-3 py-2 text-zinc-200">${linea.producto}</td>
                    <td class="px-3 py-2 text-right font-medium text-white">${linea.cantidad}</td>
                    <td class="px-3 py-2 text-right text-zinc-400">${linea.costo_unitario}</td>
                    <td class="px-3 py-2 text-right font-semibold text-purple-300">${linea.subtotal}</td>
                `;
                tbody.appendChild(fila);
            });

            document.getElementById('modal-detalle').classList.remove('hidden');
        }

        function cerrarModalDetalle() {
            document.getElementById('modal-detalle').classList.add('hidden');
        }
    </script>
    @endpush
</x-app-layout>
