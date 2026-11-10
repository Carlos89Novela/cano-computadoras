<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-400 font-mono">
                    Finanzas & Flujo de Caja • {{ $empresa->nombre }}
                </p>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    Cuentas Bancarias y Tesorería
                </h2>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs text-zinc-400 font-mono">Saldo Consolidado:</span>
                <span class="text-xl font-black font-mono text-emerald-400">
                    ${{ number_format($saldoTotal, 2) }} <span class="text-xs text-zinc-400 font-normal">MXN</span>
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6"
         x-data="{
             modalMovimiento: false,
             tipoMovimiento: 'ingreso',
             cuentaOrigenId: '{{ $cuentaSeleccionadaId ?? ($cuentas->first()?->id ?? '') }}',
             csrfToken: '{{ csrf_token() }}',
             conciliarMovimiento(url, el) {
                 fetch(url, {
                     method: 'POST',
                     headers: {
                         'X-CSRF-TOKEN': this.csrfToken,
                         'Accept': 'application/json'
                     }
                 }).then(res => res.json()).then(data => {
                     if (data.conciliado) {
                         el.outerHTML = '<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-950/80 text-emerald-300 border border-emerald-800\">✓ Conciliado</span>';
                     }
                 });
             }
         }">

        @if (session('success'))
            <div class="p-4 rounded-xl bg-emerald-950/50 border border-emerald-800 text-emerald-200 text-xs">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-rose-950/50 border border-rose-800 text-rose-200 text-xs">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Tarjetas de Cuentas Bancarias --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @forelse ($cuentas as $cuenta)
                <div class="rounded-2xl border p-5 transition shadow-lg relative
                    {{ $cuentaSeleccionadaId === $cuenta->id ? 'border-indigo-500 bg-indigo-950/20' : 'border-zinc-800 bg-zinc-950/80 hover:border-zinc-700' }}">
                    
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-400 font-mono">{{ $cuenta->banco }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $cuenta->activa ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-zinc-800 text-zinc-400' }}">
                            {{ $cuenta->activa ? 'Activa' : 'Inactiva' }}
                        </span>
                    </div>

                    <h3 class="text-base font-bold text-white mt-1">{{ $cuenta->nombre_cuenta }}</h3>
                    <p class="text-xs text-zinc-400 font-mono mt-0.5">
                        {{ $cuenta->clabe ? 'CLABE: ' . $cuenta->clabe : ($cuenta->numero_cuenta ? 'Cta: ' . $cuenta->numero_cuenta : 'Cuenta Operativa') }}
                    </p>

                    <div class="mt-4 pt-3 border-t border-zinc-800/80 flex items-baseline justify-between">
                        <span class="text-[11px] uppercase text-zinc-400 font-sans">Saldo Actual</span>
                        <span class="text-lg font-black font-mono {{ $cuenta->saldo_actual >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            ${{ number_format((float) $cuenta->saldo_actual, 2) }}
                        </span>
                    </div>

                    <div class="mt-3 flex items-center justify-between pt-2 border-t border-zinc-900 text-xs">
                        <a href="{{ route('operacion.bancos.index', ['empresa' => $empresa->id, 'cuenta_id' => $cuenta->id]) }}" 
                           class="text-indigo-400 hover:text-indigo-300 font-semibold">
                            Ver movimientos ({{ $cuenta->movimientos_count }}) &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-3 rounded-2xl border border-dashed border-zinc-800 p-8 text-center text-sm text-zinc-400">
                    No se han configurado cuentas bancarias para esta empresa.
                </div>
            @endforelse
        </div>

        {{-- Barra de Acciones del Libro de Movimientos --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
            <div class="flex items-center gap-2">
                <h3 class="text-base font-bold text-white">Libro de Movimientos</h3>
                @if ($cuentaSeleccionadaId)
                    <a href="{{ route('operacion.bancos.index', $empresa->id) }}" class="text-xs text-zinc-400 hover:text-white underline">
                        (Quitar filtro de cuenta)
                    </a>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="tipoMovimiento = 'ingreso'; modalMovimiento = true"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow transition">
                    + Registrar Ingreso
                </button>
                <button type="button" @click="tipoMovimiento = 'egreso'; modalMovimiento = true"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow transition">
                    - Registrar Egreso
                </button>
                <button type="button" @click="tipoMovimiento = 'transferencia'; modalMovimiento = true"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow transition">
                    ⇄ Transferencia Interna
                </button>
            </div>
        </div>

        {{-- Tabla de Movimientos Bancarios --}}
        <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-zinc-300">
                    <thead class="bg-zinc-900/60 uppercase tracking-wider text-[10px] text-zinc-400 font-mono border-b border-zinc-800">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Cuenta</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Concepto & Ref</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3 text-right">Saldo Posterior</th>
                            <th class="px-4 py-3 text-center">Conciliado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-900 font-sans">
                        @forelse ($movimientos as $mov)
                            <tr class="hover:bg-zinc-900/30 transition">
                                <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $mov->fecha_operacion->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-bold text-white">{{ $mov->cuentaBancaria->nombre_cuenta }}</span>
                                    <span class="text-[10px] text-zinc-400 block font-mono">{{ $mov->cuentaBancaria->banco }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase
                                        @if($mov->tipo === 'ingreso') bg-emerald-950 text-emerald-300 border border-emerald-800
                                        @elseif($mov->tipo === 'egreso') bg-rose-950 text-rose-300 border border-rose-800
                                        @else bg-indigo-950 text-indigo-300 border border-indigo-800 @endif">
                                        {{ $mov->tipo }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-white block font-medium">{{ $mov->concepto }}</span>
                                    @if ($mov->referencia)
                                        <span class="text-[10px] text-zinc-400 font-mono">Ref: {{ $mov->referencia }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold whitespace-nowrap
                                    {{ $mov->tipo === 'ingreso' ? 'text-emerald-400' : ($mov->tipo === 'egreso' ? 'text-rose-400' : 'text-indigo-400') }}">
                                    {{ $mov->tipo === 'ingreso' ? '+' : ($mov->tipo === 'egreso' ? '-' : '⇄') }}
                                    ${{ number_format((float) $mov->monto, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-300 whitespace-nowrap">
                                    ${{ number_format((float) $mov->saldo_posterior, 2) }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if ($mov->conciliado)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-950/80 text-emerald-300 border border-emerald-800">
                                            ✓ Conciliado
                                        </span>
                                    @else
                                        <button type="button" 
                                                @click="conciliarMovimiento('{{ route('operacion.bancos.movimientos.conciliar', [$empresa->id, $mov->id]) }}', $el)"
                                                class="px-2 py-0.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-[10px] border border-zinc-700 transition">
                                            Conciliar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-zinc-500">
                                    No se registran movimientos en el período seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($movimientos->hasPages())
                <div class="p-4 border-t border-zinc-800">
                    {{ $movimientos->links() }}
                </div>
            @endif
        </div>

        {{-- Modal de Registro de Movimiento --}}
        <div x-show="modalMovimiento" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalMovimiento = false" class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <h3 class="text-sm font-bold text-white capitalize" x-text="`Registrar ${tipoMovimiento}`"></h3>
                    <button type="button" @click="modalMovimiento = false" class="text-zinc-400 hover:text-white">&times;</button>
                </div>

                <form action="{{ route('operacion.bancos.movimientos.store', $empresa->id) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <input type="hidden" name="tipo" :value="tipoMovimiento">

                    <div>
                        <label class="block text-zinc-300 mb-1 font-medium" x-text="tipoMovimiento === 'transferencia' ? 'Cuenta Origen *' : 'Cuenta Bancaria *'"></label>
                        <select name="cuenta_bancaria_id" x-model="cuentaOrigenId" required class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-indigo-500">
                            @foreach ($cuentas as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre_cuenta }} ({{ $c->banco }}) - Saldo: ${{ number_format((float) $c->saldo_actual, 2) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="tipoMovimiento === 'transferencia'">
                        <label class="block text-zinc-300 mb-1 font-medium">Cuenta Destino *</label>
                        <select name="cuenta_destino_id" class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-indigo-500">
                            <option value="">Seleccione cuenta receptora</option>
                            @foreach ($cuentas as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre_cuenta }} ({{ $c->banco }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-zinc-300 mb-1 font-medium">Monto ($) *</label>
                            <input type="number" step="0.01" min="0.01" name="monto" required placeholder="0.00"
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-2.5 text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-zinc-300 mb-1 font-medium">Fecha Operación *</label>
                            <input type="date" name="fecha_operacion" value="{{ date('Y-m-d') }}" required
                                   class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-zinc-300 mb-1 font-medium">Concepto *</label>
                        <input type="text" name="concepto" required placeholder="Ej. Depósito en efectivo, Pago servicio"
                               class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-zinc-300 mb-1 font-medium">Referencia / Folio</label>
                        <input type="text" name="referencia" placeholder="Ej. Folio SPEI, No. Cheque, Ticket"
                               class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-zinc-800">
                        <button type="button" @click="modalMovimiento = false" class="px-3 py-2 bg-zinc-800 text-zinc-300 rounded-xl">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl shadow-lg">Asentar Movimiento</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>