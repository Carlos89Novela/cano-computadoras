<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-white">
                    Auditoría de Cajas y Cortes de Turno
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    Supervisión financiera, arqueos de efectivo y balance por sucursal.
                </p>
            </div>
            <a href="{{ route('pos.index') }}"
               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-indigo-600/20 transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Ir a Terminal POS
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl text-xs font-semibold">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Filtros de Consulta -->
            <form method="GET" action="{{ route('pos.cajas.index') }}" class="bg-slate-900 border border-slate-800 rounded-2xl p-4 grid gap-4 md:grid-cols-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Sucursal</label>
                    <select name="sucursal_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Todas las sucursales</option>
                        @foreach ($sucursales as $suc)
                            <option value="{{ $suc->id }}" {{ (string) request('sucursal_id') === (string) $suc->id ? 'selected' : '' }}>
                                {{ $suc->nombre }} ({{ $suc->codigo }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Estado de Caja</label>
                    <select name="estado" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Todos los estados</option>
                        <option value="abierto" @selected(request('estado') === 'abierto')>Cajas Abiertas (En Operación)</option>
                        <option value="cerrado" @selected(request('estado') === 'cerrado')>Cajas Cerradas (Arqueadas)</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold transition border border-slate-700">
                        Filtrar Registros
                    </button>
                    <a href="{{ route('pos.cajas.index') }}" class="px-4 py-2 bg-slate-950 hover:bg-slate-900 text-slate-400 rounded-xl text-xs font-semibold transition border border-slate-800">
                        Limpiar
                    </a>
                </div>
            </form>

            <!-- Tabla de Turnos -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 uppercase font-semibold text-[10px] bg-slate-950/60">
                                <th class="py-3 px-3">Turno #</th>
                                <th class="py-3 px-3">Sucursal</th>
                                <th class="py-3 px-3">Cajero</th>
                                <th class="py-3 px-3">Apertura</th>
                                <th class="py-3 px-3 text-right">Fondo Inicial</th>
                                <th class="py-3 px-3 text-right">Efectivo Esperado</th>
                                <th class="py-3 px-3 text-right">Diferencia</th>
                                <th class="py-3 px-3 text-center">Estado</th>
                                <th class="py-3 px-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-slate-200">
                            @forelse ($turnos as $t)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="py-3 px-3 font-mono font-bold text-indigo-400">
                                        #{{ $t->id }}
                                    </td>
                                    <td class="py-3 px-3 font-medium text-slate-300">
                                        {{ $t->sucursal?->nombre }}
                                    </td>
                                    <td class="py-3 px-3 text-slate-300">
                                        {{ $t->user?->name }}
                                    </td>
                                    <td class="py-3 px-3 text-slate-400 whitespace-nowrap">
                                        {{ $t->apertura_at?->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono">
                                        ${{ number_format((float)$t->monto_inicial, 2) }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono font-bold text-slate-100">
                                        ${{ number_format((float)$t->monto_esperado_efectivo, 2) }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono">
                                        @if ($t->estado === 'cerrado')
                                            @php $dif = (float)$t->diferencia_efectivo; @endphp
                                            @if ($dif == 0)
                                                <span class="text-emerald-400 font-bold">$0.00 (Exacto)</span>
                                            @elseif ($dif < 0)
                                                <span class="text-rose-400 font-bold">-${{ number_format(abs($dif), 2) }} (Faltante)</span>
                                            @else
                                                <span class="text-amber-400 font-bold">+${{ number_format($dif, 2) }} (Sobrante)</span>
                                            @endif
                                        @else
                                            <span class="text-slate-500 italic">En turno</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        @if ($t->estado === 'abierto')
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase bg-cyan-500/10 text-cyan-400 border-cyan-500/20 animate-pulse">
                                                Abierta
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase bg-slate-800 text-slate-400 border-slate-700">
                                                Cerrada
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-center space-x-1 whitespace-nowrap">
                                        <a href="{{ route('pos.cajas.show', $t) }}"
                                           class="inline-block px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold transition border border-slate-700">
                                            Auditar
                                        </a>
                                        @if ($t->estado === 'cerrado')
                                            <a href="{{ route('pos.cajas.corte-pdf', $t) }}" target="_blank"
                                               class="inline-block px-2.5 py-1 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 rounded-lg text-xs font-semibold transition border border-indigo-500/30">
                                                Ticket PDF
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-8 text-center text-slate-500">
                                        No se encontraron turnos de caja registrados con los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="pt-4">
                    {{ $turnos->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>