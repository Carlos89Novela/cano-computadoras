<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-white">
                    Transferencias entre Almacenes
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    Empresa: <span class="font-medium text-purple-300">{{ $empresa->nombre }}</span> | Flujo con custodia en tránsito
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('inventario.transferencias.create', $empresa) }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-purple-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    + Solicitar Mercancía
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

                <!-- Encabezado y Filtros de Estado -->
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-white">Bandeja de Reubicaciones y Transferencias</h3>
                        <p class="text-xs text-zinc-400">Ciclo operativo: Requisición → Despacho a Tránsito → Recepción en Destino.</p>
                    </div>

                    <!-- Pestañas de Filtro Rápido -->
                    <div class="flex items-center gap-1.5 rounded-lg border border-zinc-800 bg-zinc-900/60 p-1 text-xs">
                        <button type="button" onclick="filtrarEstado('')" id="btn-tab-todas"
                            class="tab-btn rounded-md px-3 py-1 font-semibold text-white bg-purple-600 shadow transition">
                            Todas
                        </button>
                        <button type="button" onclick="filtrarEstado('solicitada')" id="btn-tab-solicitada"
                            class="tab-btn rounded-md px-3 py-1 font-medium text-zinc-400 hover:text-white transition">
                            Por Despachar
                        </button>
                        <button type="button" onclick="filtrarEstado('en_transito')" id="btn-tab-en_transito"
                            class="tab-btn rounded-md px-3 py-1 font-medium text-zinc-400 hover:text-white transition">
                            En Camino
                        </button>
                        <button type="button" onclick="filtrarEstado('completada')" id="btn-tab-completada"
                            class="tab-btn rounded-md px-3 py-1 font-medium text-zinc-400 hover:text-white transition">
                            Completadas
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="tabla-transferencias" class="w-full text-left text-sm text-zinc-300 stripe hover">
                        <thead class="border-b border-zinc-800 text-xs uppercase tracking-wider text-zinc-400 bg-zinc-900/60">
                            <tr>
                                <th class="px-4 py-3">Folio</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Origen</th>
                                <th class="px-4 py-3">Destino</th>
                                <th class="px-4 py-3 text-center">Líneas</th>
                                <th class="px-4 py-3 text-right">Valor Aprox.</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
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
            <div class="w-full max-w-3xl rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-purple-300 font-semibold">Detalle de Requisición</p>
                        <h4 id="modal-folio" class="text-lg font-bold text-white mt-0.5">---</h4>
                    </div>
                    <button type="button" onclick="cerrarModalDetalle()" class="text-zinc-400 hover:text-white text-lg font-bold p-1">✕</button>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="rounded-lg bg-zinc-950 p-3 text-xs text-zinc-300 space-y-1">
                        <p><strong class="text-purple-300">Motivo:</strong> <span id="modal-motivo">---</span></p>
                        <p id="modal-trazabilidad" class="text-[11px] text-zinc-400">---</p>
                    </div>

                    <div class="overflow-x-auto max-h-72">
                        <table class="w-full text-left text-xs text-zinc-300">
                            <thead class="border-b border-zinc-800 bg-zinc-950/80 uppercase tracking-wider text-zinc-400 text-[11px]">
                                <tr>
                                    <th class="px-3 py-2">Producto</th>
                                    <th class="px-3 py-2 text-center">Solicitada</th>
                                    <th class="px-3 py-2 text-center">Despachada</th>
                                    <th class="px-3 py-2 text-center">Recibida</th>
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
        let dataTableInstance = null;

        $(document).ready(function () {
            dataTableInstance = $('#tabla-transferencias').DataTable({
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
                            return `<span class="font-mono font-semibold text-white tracking-wide">${data}</span>`;
                        }
                    },
                    {
                        data: 'estado',
                        className: 'px-4 py-3 whitespace-nowrap',
                        render: function (data) {
                            if (data === 'solicitada') {
                                return `<span class="inline-flex items-center gap-1 rounded-md bg-blue-500/10 px-2 py-0.5 text-xs font-medium text-blue-400 border border-blue-500/20">Solicitada</span>`;
                            }
                            if (data === 'en_transito') {
                                return `<span class="inline-flex items-center gap-1 rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-400 border border-amber-500/20">En Tránsito</span>`;
                            }
                            if (data === 'completada') {
                                return `<span class="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-400 border border-emerald-500/20">Completada</span>`;
                            }
                            return `<span class="inline-flex items-center gap-1 rounded-md bg-zinc-800 px-2 py-0.5 text-xs font-medium text-zinc-400">${data}</span>`;
                        }
                    },
                    { data: 'fecha_creacion', className: 'px-4 py-3 whitespace-nowrap text-zinc-400 text-xs' },
                    { data: 'almacen_origen', className: 'px-4 py-3 text-rose-300 font-medium text-xs' },
                    { data: 'almacen_destino', className: 'px-4 py-3 text-emerald-300 font-medium text-xs' },
                    {
                        data: 'total_lineas',
                        className: 'px-4 py-3 text-center',
                        render: function (data) {
                            return `<span class="rounded bg-zinc-900 px-2 py-0.5 text-xs text-zinc-300 border border-zinc-800">${data}</span>`;
                        }
                    },
                    {
                        data: 'costo_total',
                        className: 'px-4 py-3 text-right font-mono font-medium text-purple-300 whitespace-nowrap'
                    },
                    {
                        data: null,
                        className: 'px-4 py-3 text-center whitespace-nowrap',
                        orderable: false,
                        render: function (row) {
                            let botones = `<div class="inline-flex items-center gap-1.5">`;

                            if (row.acciones.puede_despachar) {
                                botones += `<a href="${row.acciones.url_despachar}"
                                    class="rounded bg-amber-600/90 hover:bg-amber-500 px-2.5 py-1 text-xs text-white font-semibold shadow-sm transition">
                                    Despachar
                                </a>`;
                            }

                            if (row.acciones.puede_recibir) {
                                botones += `<a href="${row.acciones.url_recibir}"
                                    class="rounded bg-emerald-600/90 hover:bg-emerald-500 px-2.5 py-1 text-xs text-white font-semibold shadow-sm transition">
                                    Recibir
                                </a>`;
                            }

                            botones += `<button type="button" onclick="verDetalle(${row.id})"
                                class="rounded bg-zinc-800 hover:bg-zinc-700 px-2.5 py-1 text-xs text-zinc-300 border border-zinc-700/60 transition">
                                Detalle
                            </button></div>`;

                            return botones;
                        }
                    }
                ],
                order: [[0, 'desc']],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                responsive: true
            });
        });

        function filtrarEstado(estado) {
            $('.tab-btn').removeClass('bg-purple-600 text-white shadow').addClass('text-zinc-400');
            const targetBtn = estado === '' ? '#btn-tab-todas' : `#btn-tab-${estado}`;
            $(targetBtn).addClass('bg-purple-600 text-white shadow').removeClass('text-zinc-400');

            if (estado === '') {
                dataTableInstance.column(1).search('').draw();
            } else {
                dataTableInstance.column(1).search(estado).draw();
            }
        }

        function verDetalle(id) {
            const tr = transferenciasCache.find(t => t.id === id);
            if (!tr) return;

            document.getElementById('modal-folio').innerText = tr.folio;
            document.getElementById('modal-motivo').innerText = tr.motivo;
            document.getElementById('modal-trazabilidad').innerText =
                `Solicitó: ${tr.solicitado_por} | Despachó: ${tr.despachado_por} | Recibió: ${tr.recibido_por}`;

            const tbody = document.getElementById('modal-lineas-body');
            tbody.innerHTML = '';

            tr.detalles.forEach(linea => {
                const fila = document.createElement('tr');
                fila.innerHTML = `
                    <td class="px-3 py-2 text-zinc-200 font-medium">${linea.producto}</td>
                    <td class="px-3 py-2 text-center text-zinc-300 font-mono">${linea.cantidad_solicitada}</td>
                    <td class="px-3 py-2 text-center text-amber-300 font-mono">${linea.cantidad_despachada}</td>
                    <td class="px-3 py-2 text-center text-emerald-300 font-mono">${linea.cantidad_recibida}</td>
                    <td class="px-3 py-2 text-right font-mono text-purple-300">${linea.subtotal}</td>
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
