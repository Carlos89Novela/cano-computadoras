<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white leading-tight">
                    Recepción y Validación de Mercancía en Destino
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    Folio: <span class="font-mono font-bold text-purple-300">{{ $transferencia->folio }}</span> | Almacén Receptor: <span class="text-emerald-300 font-semibold">{{ $transferencia->almacenDestino->nombre }}</span>
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
            <form action="{{ route('inventario.transferencias.recibir.store', [$empresa, $transferencia]) }}" method="POST" class="space-y-6">
                @csrf

                <!-- Información de Custodia y Traslado -->
                <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-purple-300 mb-4">1. Datos del Traslado</p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-3.5">
                            <span class="block text-[11px] font-medium text-zinc-400">Origen Emisor</span>
                            <span class="mt-1 block text-sm font-semibold text-rose-300">{{ $transferencia->almacenOrigen->nombre }}</span>
                            <span class="text-[11px] font-mono text-zinc-500">{{ $transferencia->almacenOrigen->codigo }}</span>
                        </div>
                        <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-3.5">
                            <span class="block text-[11px] font-medium text-zinc-400">Fecha y Hora de Despacho</span>
                            <span class="mt-1 block text-sm font-medium text-zinc-200">{{ $transferencia->despachado_at?->format('d/m/Y H:i') ?? '---' }}</span>
                            <span class="text-[11px] text-zinc-400">Despachó: {{ $transferencia->despachadoPor->name ?? 'Sistema' }}</span>
                        </div>
                        <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-3.5">
                            <span class="block text-[11px] font-medium text-zinc-400">Almacén Virtual Custodio</span>
                            <span class="mt-1 block text-sm font-semibold text-amber-300">En Tránsito</span>
                            <span class="text-[11px] text-zinc-500">Pendiente de liquidación</span>
                        </div>
                    </div>

                    @if($transferencia->observaciones_despacho)
                        <div class="mt-4 rounded-xl border border-zinc-800/60 bg-zinc-900/40 p-3 text-xs text-zinc-300">
                            <strong class="text-amber-300">Notas de salida del emisor:</strong> {{ $transferencia->observaciones_despacho }}
                        </div>
                    @endif

                    <div class="mt-4">
                        <label class="block text-xs font-medium text-zinc-300 mb-1">Observaciones de Recepción / Daños / Discrepancias (Opcional)</label>
                        <textarea name="observaciones" rows="2" placeholder="Ej. Paquete recibido en buenas condiciones por el repartidor asignado"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-white placeholder-zinc-500 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500">{{ old('observaciones') }}</textarea>
                    </div>
                </div>

                <!-- Validación de Artículos -->
                <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-purple-300">2. Cotejo de Artículos Recibidos</p>
                        <p class="text-xs text-zinc-400 mt-0.5">Ingresa las cantidades exactas que estás ingresando a tu estantería física.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-zinc-300">
                            <thead class="border-b border-zinc-800 bg-zinc-900/60 uppercase text-[11px] text-zinc-400 tracking-wider">
                                <tr>
                                    <th class="px-3 py-2.5">Producto</th>
                                    <th class="px-3 py-2.5 text-center">Cant. Despachada</th>
                                    <th class="px-3 py-2.5 w-44 text-right">Cant. Físicamente Recibida *</th>
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
                                        <td class="px-3 py-3 text-center font-mono font-medium text-amber-300">
                                            {{ number_format((float) $det->cantidad_despachada, 2) }}
                                        </td>
                                        <td class="px-3 py-3 text-right">
                                            <input type="number" step="0.0001" min="0" required
                                                name="lineas[{{ $idx }}][cantidad_recibida]"
                                                value="{{ old("lineas.{$idx}.cantidad_recibida", $det->cantidad_despachada) }}"
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
                    <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-xs font-semibold text-white shadow hover:bg-emerald-500 transition">
                        Confirmar Ingreso y Liquidar Tránsito
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
