<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-400 font-mono">
                    Facturación SAT CFDI 4.0 • Ventas de Mostrador
                </p>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    Consolidación de Factura Global
                </h2>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('pos.index') }}" 
                   class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl border border-slate-700 transition">
                    ← Punto de Venta
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6"
         x-data="facturacionGlobal({
             csrfToken: '{{ csrf_token() }}',
             urlProcesar: '{{ route('facturacion.global.procesar') }}',
             fechaInicio: '{{ $fechaInicio->format('Y-m-d') }}',
             fechaFin: '{{ $fechaFin->format('Y-m-d') }}',
             totalVentas: {{ $totalVentas }},
             cantidadVentas: {{ $ventas->count() }}
         })">

        {{-- Filtros y Configuración del CFDI Global --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            {{-- Formulario de Criterios Fiscales --}}
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                    <span>⚙️ Configuración Fiscal del Corte</span>
                </h3>

                <form method="GET" action="{{ route('facturacion.global.index') }}" class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Rango de Ventas a Consolidar</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" name="fecha_inicio" value="{{ $fechaInicio->format('Y-m-d') }}"
                                   class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                            <input type="date" name="fecha_fin" value="{{ $fechaFin->format('Y-m-d') }}"
                                   class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-xl text-xs transition">
                        Actualizar Listado de Tickets
                    </button>
                </form>

                <div class="border-t border-slate-800 pt-3 space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Periodicidad SAT</label>
                        <select x-model="form.periodicidad" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            <option value="01">01 - Diario</option>
                            <option value="02">02 - Semanal</option>
                            <option value="03">03 - Quincenal</option>
                            <option value="04">04 - Mensual</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Mes</label>
                            <select x-model="form.meses" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                                @foreach (range(1, 12) as $m)
                                    @php $val = str_pad((string)$m, 2, '0', STR_PAD_LEFT); @endphp
                                    <option value="{{ $val }}" {{ $fechaInicio->format('m') === $val ? 'selected' : '' }}>
                                        {{ $val }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Año</label>
                            <input type="number" x-model.number="form.anio" min="2024" max="2030"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Forma de Pago Predeterminada</label>
                        <select x-model="form.forma_pago_predeterminada" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            <option value="01">01 - Efectivo</option>
                            <option value="04">04 - Tarjeta de Crédito</option>
                            <option value="28">28 - Tarjeta de Débito</option>
                            <option value="03">03 - Transferencia Electrónica</option>
                        </select>
                    </div>

                    <div class="pt-2">
                        <button type="button" 
                                @click="emitirFacturaGlobal()" 
                                :disabled="procesando || cantidadVentas === 0"
                                class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                            <span x-show="!procesando">⚡ Timbrar Factura Global CFDI 4.0</span>
                            <span x-show="procesando" class="animate-pulse">Timbrando ante el PAC...</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Resumen de Métricas del Lote --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                        <span class="text-xs uppercase font-bold text-slate-400 block">Tickets por Consolidar</span>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-3xl font-black font-mono text-white" x-text="cantidadVentas"></span>
                            <span class="text-xs text-slate-400">ventas</span>
                        </div>
                        <span class="text-[11px] text-slate-500 mt-1 block">Sin factura individual timbrada</span>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                        <span class="text-xs uppercase font-bold text-slate-400 block">Total a Facturar</span>
                        <div class="mt-2 flex items-baseline gap-1">
                            <span class="text-3xl font-black font-mono text-emerald-400">${{ number_format($totalVentas, 2) }}</span>
                            <span class="text-xs text-slate-400">MXN</span>
                        </div>
                        <span class="text-[11px] text-slate-500 mt-1 block">Receptor: PUBLICO EN GENERAL</span>
                    </div>
                </div>

                {{-- Tabla de Ventas Candidatas --}}
                <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-800 flex justify-between items-center">
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider">Detalle de Tickets Incluidos</h4>
                        <span class="text-[11px] text-slate-400 font-mono">{{ $fechaInicio->format('d/m/Y') }} - {{ $fechaFin->format('d/m/Y') }}</span>
                    </div>
                    <div class="overflow-x-auto max-h-[380px] overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-slate-950/90 backdrop-blur-sm border-b border-slate-800 text-[10px] uppercase font-semibold text-slate-400">
                                <tr>
                                    <th class="py-2 px-4">Folio Ticket</th>
                                    <th class="py-2 px-4">Fecha / Hora</th>
                                    <th class="py-2 px-4 text-right">Base Imponible</th>
                                    <th class="py-2 px-4 text-right">IVA (16%)</th>
                                    <th class="py-2 px-4 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 font-mono">
                                @forelse ($ventas as $v)
                                    <tr class="hover:bg-slate-950/30 transition">
                                        <td class="py-2.5 px-4 font-bold text-indigo-400">{{ $v->folio }}</td>
                                        <td class="py-2.5 px-4 text-slate-300">{{ $v->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="py-2.5 px-4 text-right text-slate-400">${{ number_format((float) $v->base_imponible, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right text-slate-400">${{ number_format((float) $v->iva_total, 2) }}</td>
                                        <td class="py-2.5 px-4 text-right font-bold text-white">${{ number_format((float) $v->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-500 font-sans">
                                            No hay ventas pendientes de facturar en el periodo seleccionado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function facturacionGlobal(config) {
            return {
                csrf: config.csrfToken,
                urlProcesar: config.urlProcesar,
                cantidadVentas: config.cantidadVentas,
                procesando: false,

                form: {
                    fecha_inicio: config.fechaInicio,
                    fecha_fin: config.fechaFin,
                    periodicidad: '01',
                    meses: '{{ $fechaInicio->format('m') }}',
                    anio: {{ (int) $fechaInicio->format('Y') }},
                    forma_pago_predeterminada: '01'
                },

                async emitirFacturaGlobal() {
                    if (!confirm(`¿Confirmas timbrar la Factura Global CFDI 4.0 por ${this.cantidadVentas} tickets?`)) {
                        return;
                    }

                    this.procesando = true;
                    try {
                        const res = await fetch(this.urlProcesar, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });

                        const data = await res.json();
                        if (res.ok) {
                            alert(`${data.message}\nFolio: ${data.folio}\nUUID: ${data.uuid_sat}`);
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error al timbrar factura global.');
                        }
                    } catch (e) {
                        alert('Error de conexión al procesar el comprobante fiscal.');
                    } finally {
                        this.procesando = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>