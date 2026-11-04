<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-gray-100 leading-tight">
                    Historial de Recepciones
                </h2>
                <p class="text-sm text-gray-400 mt-1">
                    Empresa: <span class="font-semibold text-purple-400">{{ $empresa->nombre }}</span>
                </p>
            </div>
            <a href="{{ route('inventario.recepciones.create', $empresa) }}"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg text-white bg-purple-600 hover:bg-purple-500 shadow-lg transition">
                + Nueva Recepción
            </a>
        </div>
    </x-slot>

    <div class="py-8 text-gray-200" x-data="historialRecepciones()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="p-4 bg-emerald-950/60 border border-emerald-600/50 text-emerald-300 rounded-lg shadow-lg">
                    <p class="font-medium flex items-center gap-2">
                        <span>✔</span> {{ session('success') }}
                    </p>
                </div>
            @endif

            <div class="bg-gray-900 border border-gray-800 overflow-hidden shadow-xl sm:rounded-xl p-6">
                <div class="overflow-x-auto">
                    <table id="tabla-recepciones" class="min-w-full divide-y divide-gray-800 text-sm text-gray-300">
                        <thead class="bg-gray-800/60 uppercase tracking-wider text-xs text-gray-400 font-semibold">
                            <tr>
                                <th class="px-4 py-3 text-left">Folio</th>
                                <th class="px-4 py-3 text-left">Fecha</th>
                                <th class="px-4 py-3 text-left">Tipo</th>
                                <th class="px-4 py-3 text-left">Almacén</th>
                                <th class="px-4 py-3 text-left">Proveedor</th>
                                <th class="px-4 py-3 text-right">Líneas</th>
                                <th class="px-4 py-3 text-right">Importe Total</th>
                                <th class="px-4 py-3 text-center">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal para Detalle de Líneas de la Recepción -->
        <div x-show="modalAbierto" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div @click.away="cerrarModal()"
                class="bg-gray-900 border border-gray-800 rounded-xl shadow-2xl max-w-2xl w-full p-6 space-y-4">
                
                <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            Detalle de Recepción: <span class="text-purple-400" x-text="recepcionSeleccionada?.folio"></span>
                        </h3>
                        <p class="text-xs text-gray-400" x-text="`Motivo: ${recepcionSeleccionada?.motivo}`"></p>
                    </div>
                    <button type="button" @click="cerrarModal()" class="text-gray-400 hover:text-white font-bold text-xl">
                        &times;
                    </button>
                </div>

                <div class="overflow-x-auto max-h-80 border border-gray-800 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-800 text-xs">
                        <thead class="bg-gray-800 text-gray-300">
                            <tr>
                                <th class="px-3 py-2 text-left">Producto</th>
                                <th class="px-3 py-2 text-right">Cantidad</th>
                                <th class="px-3 py-2 text-right">Costo Unit.</th>
                                <th class="px-3 py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800 bg-gray-900/50">
                            <template x-for="(linea, idx) in recepcionSeleccionada?.detalles || []" :key="idx">
                                <tr class="hover:bg-gray-800/30">
                                    <td class="px-3 py-2 text-gray-200" x-text="linea.producto"></td>
                                    <td class="px-3 py-2 text-right text-gray-300" x-text="linea.cantidad"></td>
                                    <td class="px-3 py-2 text-right text-gray-300" x-text="linea.costo_unitario"></td>
                                    <td class="px-3 py-2 text-right font-semibold text-purple-400" x-text="linea.subtotal"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-between items-center pt-2">
                    <span class="text-sm font-semibold text-gray-300">
                        Total: <span class="text-purple-400" x-text="recepcionSeleccionada?.importe_total"></span>
                    </span>
                    <button type="button" @click="cerrarModal()"
                        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-sm font-semibold rounded-lg text-gray-200 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function historialRecepciones() {
            return {
                modalAbierto: false,
                recepcionSeleccionada: null,
                abrirModal(recepcion) {
                    this.recepcionSeleccionada = recepcion;
                    this.modalAbierto = true;
                },
                cerrarModal() {
                    this.modalAbierto = false;
                    this.recepcionSeleccionada = null;
                }
            };
        }

        document.addEventListener('DOMContentLoaded', function () {
            let records = [];

            const tabla = $('#tabla-recepciones').DataTable({
                ajax: '{{ route('inventario.recepciones.data', $empresa) }}',
                columns: [
                    { data: 'folio', className: 'font-mono text-purple-400 font-semibold' },
                    { data: 'recibido_at' },
                    { 
                        data: 'tipo',
                        render: function(data) {
                            return `<span class="px-2 py-0.5 rounded text-xs bg-gray-800 border border-gray-700 text-gray-200">${data}</span>`;
                        }
                    },
                    { data: 'almacen' },
                    { data: 'proveedor' },
                    { data: 'total_lineas', className: 'text-right font-mono' },
                    { data: 'importe_total', className: 'text-right font-semibold text-emerald-400 font-mono' },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data, type, row, meta) {
                            return `<button type="button" class="btn-ver-detalle px-2.5 py-1 text-xs font-semibold rounded bg-purple-900/40 text-purple-200 border border-purple-500/30 hover:bg-purple-800/60 transition" data-index="${meta.row}">Ver Líneas</button>`;
                        }
                    }
                ],
                order: [[1, 'desc']],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                }
            });

            tabla.on('xhr', function () {
                const json = tabla.ajax.json();
                records = json.data || [];
            });

            $('#tabla-recepciones').on('click', '.btn-ver-detalle', function () {
                const index = $(this).data('index');
                const rowData = records[index];
                if (rowData) {
                    const alpineData = Alpine.$data(document.querySelector('[x-data]'));
                    alpineData.abrirModal(rowData);
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
