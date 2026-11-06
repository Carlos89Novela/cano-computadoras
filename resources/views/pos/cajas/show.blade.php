<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-white flex items-center gap-2">
                    Arqueo de Turno #{{ $turno->id }}
                    @if ($turno->estado === 'abierto')
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase bg-cyan-500/10 text-cyan-400 border-cyan-500/20">Abierta</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border uppercase bg-slate-800 text-slate-400 border-slate-700">Cerrada</span>
                    @endif
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    Sucursal: <span class="text-slate-200 font-semibold">{{ $turno->sucursal?->nombre }}</span> |
                    Cajero: <span class="text-indigo-400 font-semibold">{{ $turno->user?->name }}</span> |
                    Apertura: <span class="text-slate-300">{{ $turno->apertura_at?->format('d/m/Y H:i') }}</span>
                    @if($turno->cierre_at)
                        | Cierre: <span class="text-slate-300">{{ $turno->cierre_at->format('d/m/Y H:i') }}</span>
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('pos.cajas.index') }}"
                   class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold border border-slate-700 transition">
                    ← Volver
                </a>
                @if ($turno->estado === 'cerrado')
                    <a href="{{ route('pos.cajas.corte-pdf', $turno) }}" target="_blank"
                       class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/20 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Imprimir Corte
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6" x-data="{ modalMovimiento: false }">

            @if (session('status'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl text-xs font-semibold">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-2xl text-xs space-y-1">
                    @foreach ($errors->all() as$err)
                        <p>• {{ $err }}</p>
                    @endforeach
                </div>
            @endif

            <!-- Tarjetas de Balance Financiero -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Fondo Inicial</p>
                    <p class="text-xl font-bold font-mono text-white mt-1">
                        ${{ number_format((float)$turno->monto_inicial, 2) }}
                    </p>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Ventas en Efectivo</p>
                    <p class="text-xl font-bold font-mono text-emerald-400 mt-1">
                        +${{ number_format((float)$turno->total_ventas_efectivo, 2) }}
                    </p>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Retiros de Caja</p>
                    <p class="text-xl font-bold font-mono text-rose-400 mt-1">
                        -${{ number_format((float)$turno->total_retiros, 2) }}
                    </p>
                </div>

                <div class="bg-slate-900 border border-indigo-500/30 rounded-2xl p-4 bg-indigo-950/20">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-300">Efectivo Esperado</p>
                    <p class="text-xl font-bold font-mono text-indigo-200 mt-1">
                        ${{ number_format((float)$turno->monto_esperado_efectivo, 2) }}
                    </p>
                </div>
            </div>

            <!-- Otros Métodos de Pago y Arqueo Final -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-3">
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider border-b border-slate-800 pb-2">
                        Ventas por Otros Medios
                    </h3>
                    <div class="flex justify-between items-center text-xs py-1">
                        <span class="text-slate-400">Tarjeta (Débito/Crédito):</span>
                        <span class="font-mono font-bold text-white">${{ number_format((float)$turno->total_ventas_tarjeta, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs py-1">
                        <span class="text-slate-400">Transferencia Electrónica:</span>
                        <span class="font-mono font-bold text-white">${{ number_format((float)$turno->total_ventas_transferencia, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs py-1">
                        <span class="text-slate-400">Otros Ingresos Registrados:</span>
                        <span class="font-mono font-bold text-emerald-400">+${{ number_format((float)$turno->total_otros_ingresos, 2) }}</span>
                    </div>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-3 lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider">
                            Resultado del Cierre de Caja
                        </h3>
                        @if ($turno->estado === 'abierto')
                            <button @click="modalMovimiento = true"
                                    class="px-3 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-bold shadow transition">
                                - Registrar Retiro / Ingreso Manual
                            </button>
                        @endif
                    </div>

                    @if ($turno->estado === 'cerrado')
                        <div class="grid gap-4 sm:grid-cols-3 pt-2">
                            <div>
                                <p class="text-[11px] text-slate-400">Efectivo Contado:</p>
                                <p class="text-base font-bold font-mono text-white mt-0.5">
                                    ${{ number_format((float)$turno->monto_real_efectivo, 2) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] text-slate-400">Diferencia Final:</p>
                                @php $dif = (float)$turno->diferencia_efectivo; @endphp
                                <p class="text-base font-bold font-mono mt-0.5 {{ $dif == 0 ? 'text-emerald-400' : ($dif < 0 ? 'text-rose-400' : 'text-amber-400') }}">
                                    {{ $dif >= 0 ? '+' : '' }}${{ number_format($dif, 2) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] text-slate-400">Observaciones:</p>
                                <p class="text-xs text-slate-300 mt-0.5 italic">
                                    {{ $turno->observaciones_cierre ?: 'Sin notas registradas.' }}
                                </p>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-slate-400 pt-2">
                            Este turno de caja sigue en operación. El arqueo formal y el cálculo de sobrantes/faltantes se generará cuando el cajero efectúe el cierre desde el POS.
                        </p>
                    @endif
                </div>
            </div>

            <!-- Movimientos de Caja Registrados (Entradas/Salidas Manuales) -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-4">
                <h3 class="text-xs font-bold text-white uppercase tracking-wider pb-2 border-b border-slate-800">
                    Historial de Retiros y Entradas Extraordinarias
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 uppercase text-[10px] border-b border-slate-800">
                                <th class="py-2.5 px-3">Fecha</th>
                                <th class="py-2.5 px-3">Tipo</th>
                                <th class="py-2.5 px-3">Concepto</th>
                                <th class="py-2.5 px-3">Motivo / Justificación</th>
                                <th class="py-2.5 px-3">Comprobante</th>
                                <th class="py-2.5 px-3 text-right">Monto</th>
                                <th class="py-2.5 px-3">Registrado Por</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-300">
                            @forelse ($turno->movimientos as$mov)
                                <tr>
                                    <td class="py-2.5 px-3 text-slate-400 whitespace-nowrap">
                                        {{ $mov->realizado_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-2.5 px-3 font-semibold uppercase text-[10px]">
                                        @if ($mov->tipo === 'retiro')
                                            <span class="text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20">Retiro</span>
                                        @else
                                            <span class="text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">Ingreso</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 font-medium text-white">{{ $mov->concepto }}</td>
                                    <td class="py-2.5 px-3 text-slate-300">{{ $mov->motivo }}</td>
                                    <td class="py-2.5 px-3 font-mono text-slate-400">{{ $mov->comprobante_numero ?: '---' }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold {{ $mov->tipo === 'retiro' ? 'text-rose-400' : 'text-emerald-400' }}">
                                        {{ $mov->tipo === 'retiro' ? '-' : '+' }}${{ number_format((float)$mov->monto, 2) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-400">{{ $mov->usuario?->name }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-center text-slate-500">
                                        No se han registrado retiros ni entradas extraordinarias en este turno.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal para Registrar Retiro o Entrada Manual -->
            <div x-show="modalMovimiento" style="display: none;"
                 class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <h3 class="text-sm font-bold text-white">Registrar Movimiento de Efectivo</h3>
                        <button @click="modalMovimiento = false" class="text-slate-400 hover:text-white">&times;</button>
                    </div>

                    <form action="{{ route('pos.cajas.movimiento', $turno) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Tipo de Operación *</label>
                            <select name="tipo" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white">
                                <option value="retiro">Retiro de Efectivo (Salida de Caja)</option>
                                <option value="ingreso">Ingreso Extraordinario (Entrada a Caja)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Concepto *</label>
                            <input type="text" name="concepto" required placeholder="ej. Recolección de valores / Pago de papelería"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Monto ($ MXN) *</label>
                                <input type="number" step="0.01" min="0.01" name="monto" required placeholder="0.00"
                                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white font-mono font-bold">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1"># Comprobante / Recibo</label>
                                <input type="text" name="comprobante_numero" placeholder="ej. REC-094"
                                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Motivo / Justificación *</label>
                            <textarea name="motivo" rows="2" required placeholder="Explica detalladamente la razón de este movimiento..."
                                      class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modalMovimiento = false"
                                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/20">
                                Guardar Movimiento
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>