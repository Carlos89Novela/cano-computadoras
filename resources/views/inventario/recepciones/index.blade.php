<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-white">
                    Historial de Recepciones de Inventario
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    Empresa: <span class="font-medium text-purple-300">{{ $empresa->nombre }}</span>
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('inventario.recepciones.create', $empresa) }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    + Nueva Recepción
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
                        <h3 class="text-base font-bold text-white">Documentos de Entrada Registrados</h3>
                        <p class="text-xs text-zinc-400">Listado consolidado de compras, recepciones y ajustes con cálculo de costo promedio ponderado.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="tabla-recepciones" class="w-full text-left text-sm text-zinc-300 stripe hover">
                        <thead class="border-b border-zinc-800 text-xs uppercase tracking-wider text-zinc-400 bg-zinc-900/60">
                            <tr>
                                <th class="px-4 py-3">Folio</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Almacén</th>
                                <th class="px-4 py-3">Proveedor / Ref.</th>
                                <th class="px-4 py-3 text-center">Líneas</th>
                                <th class="px-4 py-3 text-right">Importe Total</th>
                                <th class="px-4 py-3">Recibido Por</th>
                                <th class="px-4 py-3 text-center">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/60">
                            <!-- DataTables carga las filas via AJAX -->
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal para Detalle de Líneas de la Recepción -->
    <div id="modal-detalle" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/70 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="w-full max-w-2xl rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-purple-300 font-semibold">Detalle de Recepción</p>
                        <h4 id="modal-folio" class="text-lg font-bold text-white mt-0.5">---</h4>
                    </div>
                    <button type="button" onclick="cerrarModalDetalle()" class="text-zinc-400 hover:text-white text-lg font-bold p-1">
                        ✕
                    </button>
                </div>

                <div class="mt-4">
                    <div class="mb-3 rounded-lg bg-zinc-950 p-3 text-xs text-zinc-400" id="modal-motivo">
                        <!-- Motivo dinámico -->
                    </div>

                    <div class="overflow-x-auto max-h-72">
                        <table class="w-full text-left text-xs text-zinc-300">
                            <thead class="border-b border-zinc-800 bg-zinc-950/80 uppercase tracking-wider text-zinc-400">
                                <tr>
                                    <th class="px-3 py-2">Producto / SKU</th>
                                    <th class="px-3 py-2 text-right">Cantidad</th>
                                    <th class="px-3 py-2 text-right">Costo Unit.</th>
                                    <th class="px-3 py-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="modal-lineas-body" class="divide-y divide-zinc-800/60">
                                <!-- Líneas dinámicas -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 flex justify-end border-t border-zinc-800 pt-3">
                    <button type="button" onclick="cerrarModalDetalle()" class="rounded-lg bg-zinc-800 px-4 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let recepcionesCache = [];

        $(document).ready(function () {
            const table = $('#tabla-recepciones').DataTable({
                ajax: {
                    url: "{{ route('inventario.recepciones.data', $empresa) }}",
                    dataSrc: function (json) {
                        recepcionesCache = json.data;
                        return json.data;
                    }
                },
                columns: [
                    {
                        data: 'folio',
                        render: function (data) {
                            return `<span class="font-semibold text-white tracking-wide">${data}</span>`;
                        }
                    },
                    { data: 'recibido_at' },
                    {
                        data: 'tipo',
                        render: function (data) {
                            let color = 'bg-purple-600/20 text-purple-300 border-purple-800/40';
                            if (data.toLowerCase() === 'compra') {
                                color = 'bg-emerald-600/20 text-emerald-300 border-emerald-800/40';
                            } else if (data.toLowerCase().includes('ajuste')) {
                                color = 'bg-blue-600/20 text-blue-300 border-blue-800/40';
                            }
                            return `<span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold ${color}">${data}</span>`;
                        }
                    },
                    { data: 'almacen' },
                    {
                        data: null,
                        render: function (row) {
                            return `<div>
                                <p class="text-zinc-200 font-medium">${row.proveedor}</p>
                                <p class="text-xs text-zinc-500">Ref: ${row.referencia_externa}</p>
                            </div>`;
                        }
                    },
                    {
                        data: 'total_lineas',
                        className: 'text-center',
                        render: function (data) {
                            return `<span class="rounded bg-zinc-900 px-2 py-0.5 text-xs text-zinc-300 border border-zinc-800">${data}</span>`;
                        }
                    },
                    {
                        data: 'importe_total',
                        className: 'text-right font-semibold text-emerald-400'
                    },
                    { data: 'recibido_por' },
                    {
                        data: null,
                        className: 'text-center',
                        orderable: false,
                        render: function (row) {
                            return `<button type="button" onclick="verDetalle(${row.id})"
                                class="rounded-lg bg-zinc-800 hover:bg-zinc-700 px-2.5 py-1 text-xs text-purple-300 font-semibold border border-zinc-700/60 transition">
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
            const recepcion = recepcionesCache.find(r => r.id === id);
            if (!recepcion) return;

            document.getElementById('modal-folio').innerText = recepcion.folio;
            document.getElementById('modal-motivo').innerHTML = `<strong>Motivo:</strong> ${recepcion.motivo || 'Sin observaciones registradas.'}`;

            const tbody = document.getElementById('modal-lineas-body');
            tbody.innerHTML = '';

            recepcion.detalles.forEach(linea => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-3 py-2 text-zinc-200">${linea.producto}</td>
                    <td class="px-3 py-2 text-right font-medium text-white">${linea.cantidad}</td>
                    <td class="px-3 py-2 text-right text-zinc-400">${linea.costo_unitario}</td>
                    <td class="px-3 py-2 text-right font-semibold text-emerald-400">${linea.subtotal}</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('modal-detalle').classList.remove('hidden');
        }

        function cerrarModalDetalle() {
            document.getElementById('modal-detalle').classList.add('hidden');
        }
    </script>
    @endpush
</x-app-layout>
