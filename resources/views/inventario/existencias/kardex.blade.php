<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white leading-tight">
                    Kardex de Movimientos: {{ $producto->nombre }}
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    SKU: <span class="font-mono text-purple-300">{{ $producto->sku }}</span>
                    @if($almacen)
                        | Almacén filtrado: <span class="text-emerald-300">{{ $almacen->nombre }}</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('inventario.kardex.pdf', ['empresa' => $empresa, 'producto' => $producto, 'almacen' => $almacen?->id]) }}"
                   class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/20 transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Descargar Kardex PDF
                </a>
                <a href="{{ route('inventario.existencias.index', $empresa) }}"
                   class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700 transition">
                    ← Volver a Existencias
                </a>
            </div>
            <a href="{{ route('inventario.existencias.index', $empresa) }}"
               class="rounded-lg bg-zinc-800 px-3.5 py-2 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 transition">
                ← Volver a existencias
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                <div class="overflow-x-auto">
                    <table id="tabla-kardex" class="w-full text-left text-sm text-zinc-300 stripe hover">
                        <thead class="border-b border-zinc-800 text-xs uppercase tracking-wider text-zinc-400 bg-zinc-900/60">
                            <tr>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Folio</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Almacén</th>
                                <th class="px-4 py-3 text-center">Cantidad</th>
                                <th class="px-4 py-3 text-center">Saldo Ant.</th>
                                <th class="px-4 py-3 text-center">Nuevo Saldo</th>
                                <th class="px-4 py-3 text-right">Costo Unitario</th>
                                <th class="px-4 py-3">Motivo / Usuario</th>
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
        $(document).ready(function () {
            $('#tabla-kardex').DataTable({
                ajax: {
                    url: "{{ route('inventario.kardex.data', ['empresa' => $empresa, 'producto' => $producto, 'almacen' => $almacen?->id]) }}"
                },
                columns: [
                    { data: 'fecha', className: 'px-4 py-3 whitespace-nowrap text-zinc-400 text-xs' },
                    { data: 'folio', className: 'px-4 py-3 font-mono text-xs text-white' },
                    {
                        data: 'tipo',
                        className: 'px-4 py-3 whitespace-nowrap',
                        render: function (tipo) {
                            return `<span class="rounded bg-zinc-900 px-2 py-0.5 text-xs text-zinc-300 border border-zinc-800">${tipo}</span>`;
                        }
                    },
                    { data: 'almacen', className: 'px-4 py-3 text-zinc-200 text-xs' },
                    { data: 'cantidad', className: 'px-4 py-3 text-center font-mono font-bold text-white' },
                    { data: 'existencia_anterior', className: 'px-4 py-3 text-center font-mono text-zinc-400' },
                    { data: 'existencia_nueva', className: 'px-4 py-3 text-center font-mono text-emerald-400' },
                    { data: 'costo_unitario', className: 'px-4 py-3 text-right font-mono text-purple-300' },
                    {
                        data: null,
                        className: 'px-4 py-3 text-xs',
                        render: function (row) {
                            return `<span>${row.motivo}</span><span class="block text-[11px] text-zinc-500">Por: ${row.usuario}</span>`;
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
    </script>
    @endpush
</x-app-layout>
