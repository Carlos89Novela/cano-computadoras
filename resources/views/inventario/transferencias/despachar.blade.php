<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white leading-tight">
                    Despachar Transferencia hacia Tránsito
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    Folio: <span class="font-mono font-bold text-purple-300">{{ $transferencia->folio }}</span> | Empresa: <span class="text-zinc-200">{{ $empresa->nombre }}</span>
                </p>
            </div>
            <a href="{{ route('inventario.transferencias.index', $empresa) }}"
               class="rounded-lg bg-zinc-800 px-3.5 py-2 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 transition">
                ← Volver a transferencias
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            <form action="{{ route('inventario.transferencias.despachar.store', [$empresa, $transferencia]) }}" method="POST" class="space-y-6">
                @csrf

                <!-- Resumen de Requisición -->
                <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-purple-300 mb-4">1. Resumen de la Solicitud</p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-3.5">
                            <span class="block text-[11px] font-medium text-zinc-400">Almacén Origen (Despachador)</span>
                            <span class="mt-1 block text-sm font-semibold text-rose-300">{{ $transferencia->almacenOrigen->nombre }}</span>
                            <span class="text-[11px] font-mono text-zinc-500">{{ $transferencia->almacenOrigen->codigo }}</span>
                        </div>
                        <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-3.5">
                            <span class="block text-[11px] font-medium text-zinc-400">Almacén Destino (Solicitante)</span>
                            <span class="mt-1 block text-sm font-semibold text-emerald-300">{{ $transferencia->almacenDestino->nombre }}</span>
                            <span class="text-[11px] font-mono text-zinc-500">{{ $transferencia->almacenDestino->codigo }}</span>
                        </div>
                        <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-3.5">
                            <span class="block text-[11px] font-medium text-zinc-400">Fecha de Solicitud</span>
                            <span class="mt-1 block text-sm font-medium text-zinc-200">{{ $transferencia->solicitado_at?->format('d/m/Y H:i') ?? '---' }}</span>
                            <span class="text-[11px] text-zinc-400">Por: {{ $transferencia->solicitadoPor->name ?? 'Sistema' }}</span>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-zinc-800/60 bg-zinc-900/40 p-3 text-xs text-zinc-300">
                        <strong class="text-purple-300">Motivo de requisición:</strong> {{ $transferencia->motivo }}
                    </div>

                    <div class="mt-4">
                        <label class="block text-xs font-medium text-zinc-300 mb-1">Notas u Observaciones del Despacho (Opcional)</label>
                        <textarea name="observaciones" rows="2" placeholder="Ej. Entregado al repartidor de ruta vespertina empaquetado en caja sellada"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white placeholder-zinc-500 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500">{{ old('observaciones') }}</textarea>
                    </div>
                </div>

                <!-- Líneas a Despachar -->
                <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-purple-300">2. Artículos a Entregar en Tránsito</p>
                        <p class="text-xs text-zinc-400 mt-0.5">Confirma la cantidad que sale físicamente de tu almacén hacia el vehículo de traslado.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-zinc-300">
                            <thead class="border-b border-zinc-800 bg-zinc-900/60 uppercase text-[11px] text-zinc-400 tracking-wider">
                                <tr>
                                    <th class="px-3 py-2.5">Producto</th>
                                    <th class="px-3 py-2.5 text-center">Cant. Solicitada</th>
                                    <th class="px-3 py-2.5 w-44 text-right">Cant. a Despachar *</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800/60">
                                @foreach($transferencia->detalles as $idx => $det)
                                    <tr>
                                        <td class="px-3 py-3">
                                            <input type="hidden" name="lineas[{{ $idx }}][detalle_id]" value="{{ $det->id }}">
                                            <span class="font-mono text-purple-300 font-semibold">{{ $det->producto->sku }}</span>
                                            <span class="block text-zinc-200 mt-0.5">{{ $det->producto->nombre }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-center font-mono font-medium text-zinc-400">
                                            {{ number_format((float) $det->cantidad_solicitada, 2) }}
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            <input type="number" step="0.0001" min="0.0001" required
                                                name="lineas[{{ $idx }}][cantidad_despachada]"
                                                value="{{ old("lineas.{$idx}.cantidad_despachada", $det->cantidad_solicitada) }}"
                                                class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-xs text-right font-mono text-white focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('inventario.transferencias.index', $empresa) }}"
                        class="rounded-lg px-4 py-2 text-xs font-semibold text-zinc-400 hover:text-white transition">
                        Cancelar
                    </a>
                    <button type="submit" class="rounded-lg bg-amber-600 px-6 py-2.5 text-xs font-semibold text-white shadow hover:bg-amber-500 transition">
                        Autorizar Despacho y Enviar a Tránsito
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
