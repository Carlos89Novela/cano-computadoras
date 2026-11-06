<x-app-layout>
    <div x-data="{ modalCancelar: false, motivo: '' }" class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6 space-y-6">

        {{-- Mensajes de estado y errores --}}
        @if (session('status'))
            <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-xs font-semibold">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs font-semibold">
                {{ $errors->first() }}
            </div>
        @endif

        <header class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-800">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold tracking-tight text-white font-mono">
                        {{ $orden->folio }}
                    </h1>
                    @php
                        $badgeColor = match ($orden->estado) {
                            'borrador' => 'bg-slate-800 text-slate-300 border-slate-700',
                            'solicitada' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                            'autorizada' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                            'recibida_parcial' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
                            'recibida_total' => 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
                            'cancelada' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                            default => 'bg-slate-800 text-slate-400 border-slate-700',
                        };
                    @endphp
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeColor }}">
                        {{ ucfirst(str_replace('_', ' ', $orden->estado)) }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    Emitida el {{ $orden->fecha_emision?->format('d/m/Y') }} por <span class="text-slate-200">{{ $orden->usuario?->name }}</span>
                    @if ($orden->autorizador)
                        | Autorizada por <span class="text-emerald-400 font-semibold">{{ $orden->autorizador->name }}</span> el {{ $orden->autorizada_at?->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>

            {{-- Botonera de Transición de Estados --}}
            <div class="flex flex-wrap items-center gap-2">
                @if ($orden->estado === 'borrador')
                    <form method="POST" action="{{ route('compras.ordenes.solicitar', $orden->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="px-3.5 py-2 bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30 rounded-xl text-xs font-semibold transition">
                            Solicitar Autorización
                        </button>
                    </form>
                @endif

                @if (in_array($orden->estado, ['borrador', 'solicitada'], true))
                    @can('compras.autorizar')
                        <form method="POST" action="{{ route('compras.ordenes.autorizar', $orden->id) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-600/20 transition">
                                ✓ Autorizar Orden
                            </button>
                        </form>
                    @endcan

                    <button type="button" @click="modalCancelar = true" class="px-3.5 py-2 bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 rounded-xl text-xs font-semibold transition">
                        ✕ Cancelar
                    </button>
                @endif

                <a href="{{ route('compras.ordenes.pdf', $orden->id) }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-semibold transition">
                    <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    Imprimir / PDF
                </a>

                <a href="{{ route('compras.ordenes.index') }}"
                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold border border-slate-700 transition">
                    ← Volver
                </a>
            </div>
        </header>

        @if ($orden->estado === 'cancelada')
            <div class="p-4 bg-rose-950/40 border border-rose-800/60 rounded-2xl text-xs space-y-1">
                <span class="font-bold text-rose-400 uppercase tracking-wider text-[10px]">Motivo de Cancelación</span>
                <p class="text-rose-200">{{ $orden->motivo_cancelacion }}</p>
            </div>
        @endif

        {{-- Tarjetas Informativas --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl space-y-1">
                <span class="text-slate-400 uppercase font-semibold text-[10px]">Proveedor</span>
                <div class="text-sm font-bold text-white">{{ $orden->proveedor?->razon_social ?? $orden->proveedor?->nombre }}</div>
                <div class="text-slate-400 font-mono">RFC: {{ $orden->proveedor?->rfc }}</div>
            </div>

            <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl space-y-1">
                <span class="text-slate-400 uppercase font-semibold text-[10px]">Destino</span>
                <div class="text-sm font-bold text-white">{{ $orden->sucursal?->nombre }}</div>
                <div class="text-slate-400">Almacén: {{ $orden->almacen?->nombre }}</div>
            </div>

            <div class="p-4 bg-slate-900 border border-slate-800 rounded-2xl space-y-1">
                <span class="text-slate-400 uppercase font-semibold text-[10px]">Entrega Esperada</span>
                <div class="text-sm font-bold text-white">
                    {{ $orden->fecha_entrega_esperada ? $orden->fecha_entrega_esperada->format('d/m/Y') : 'Sin fecha definida' }}
                </div>
                <div class="text-slate-400">Moneda: {{ $orden->moneda }}</div>
            </div>
        </div>

        {{-- Partidas Solicitadas --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">Partidas de la Orden</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase tracking-wider">
                        <tr>
                            <th class="py-2.5 px-3">SKU</th>
                            <th class="py-2.5 px-3">Producto</th>
                            <th class="py-2.5 px-3 text-right">Cant. Solicitada</th>
                            <th class="py-2.5 px-3 text-right">Cant. Recibida</th>
                            <th class="py-2.5 px-3 text-right">Costo Unit.</th>
                            <th class="py-2.5 px-3 text-right">Subtotal</th>
                            <th class="py-2.5 px-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @foreach ($orden->detalles as $detalle)
                            <tr class="hover:bg-slate-800/50 transition">
                                <td class="py-3 px-3 font-mono text-slate-400">{{ $detalle->producto?->sku }}</td>
                                <td class="py-3 px-3 font-semibold text-white">{{ $detalle->producto?->nombre }}</td>
                                <td class="py-3 px-3 text-right font-mono text-slate-200">{{ number_format($detalle->cantidad, 2) }}</td>
                                <td class="py-3 px-3 text-right font-mono {{ $detalle->cantidad_recibida >= $detalle->cantidad ? 'text-emerald-400 font-bold' : 'text-slate-400' }}">
                                    {{ number_format($detalle->cantidad_recibida, 2) }}
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-slate-300">${{ number_format($detalle->costo_unitario, 2) }}</td>
                                <td class="py-3 px-3 text-right font-mono text-slate-300">${{ number_format($detalle->subtotal, 2) }}</td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-emerald-400">${{ number_format($detalle->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Desglose Económico --}}
            <div class="flex justify-end pt-4 border-t border-slate-800">
                <div class="w-72 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>Subtotal:</span>
                        <span class="font-mono text-white">${{ number_format($orden->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>IVA (16%):</span>
                        <span class="font-mono text-white">${{ number_format($orden->iva_total, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold text-white pt-2 border-t border-slate-800">
                        <span>Total:</span>
                        <span class="font-mono text-emerald-400 text-lg">${{ number_format($orden->total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal de Cancelación --}}
        <div x-show="modalCancelar" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalCancelar = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-5 shadow-2xl space-y-4">
                <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                    <h3 class="text-sm font-bold text-white">Cancelar Orden de Compra</h3>
                    <button type="button" @click="modalCancelar = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form method="POST" action="{{ route('compras.ordenes.cancelar', $orden->id) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="text-xs text-slate-300 block mb-1 font-medium">Motivo de Cancelación *</label>
                        <textarea name="motivo_cancelacion" rows="3" required
                                  placeholder="Explique la causa de la cancelación..."
                                  style="background-color: #020617 !important; color: #ffffff !important; border-color: #334155 !important;"
                                  class="w-full rounded-xl px-3 py-2 text-xs focus:outline-none"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                        <button type="button" @click="modalCancelar = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">
                            Volver
                        </button>
                        <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-rose-600/30 transition">
                            Confirmar Cancelación
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
