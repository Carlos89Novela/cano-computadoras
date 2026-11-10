<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-400 font-mono">
                    Restaurante • Business Intelligence
                </p>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    📊 Panel de Analítica y Rendimiento
                </h2>
            </div>

            {{-- Filtro de Fechas --}}
            <form method="GET" action="{{ route('restaurante.reportes.index') }}" class="flex items-center gap-2">
                <input type="date" name="fecha_inicio" value="{{ $fechaInicio->format('Y-m-d') }}"
                       class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                <span class="text-slate-500 text-xs">a</span>
                <input type="date" name="fecha_fin" value="{{ $fechaFin->format('Y-m-d') }}"
                       class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                <button type="submit" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow transition">
                    Filtrar
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- 1. Tarjetas de Tiempos en Pantallas KDS --}}
        <div>
            <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-3">
                ⏱️ Tiempo Promedio de Preparación en Cocina (KDS)
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach (['cocina' => 'Cocina Caliente', 'barra' => 'Barra / Bebidas', 'parrilla' => 'Parrilla', 'postres' => 'Repostería'] as $est => $label)
                    @php $kds = $metricas['metricas_kds'][$est] ?? ['cantidad' => 0, 'promedio_minutos' => 0]; @endphp
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
                        <span class="text-[10px] font-bold uppercase text-slate-400 block">{{ $label }}</span>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-2xl font-black font-mono text-white">{{ $kds['promedio_minutos'] }}</span>
                            <span class="text-xs text-slate-400">min/orden</span>
                        </div>
                        <span class="text-[11px] text-slate-500 font-mono mt-1 block">{{ $kds['cantidad'] }} items despachados</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 2. Filas de Desempeño: Top Ventas y Márgenes Teóricos --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            {{-- Top Platillos Más Vendidos --}}
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-white mb-3 flex items-center justify-between">
                        <span>🏆 Platillos Más Vendidos</span>
                        <span class="text-[11px] text-slate-500 font-normal">Por volumen</span>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 border-b border-slate-800 text-[10px] uppercase font-semibold">
                                    <th class="py-2">Platillo</th>
                                    <th class="py-2 text-center">Unidades</th>
                                    <th class="py-2 text-right">Total Generado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                @forelse ($metricas['top_platillos'] as $platillo)
                                    <tr class="hover:bg-slate-950/40 transition">
                                        <td class="py-2.5 font-medium text-slate-200">{{ $platillo['nombre'] }}</td>
                                        <td class="py-2.5 text-center font-mono font-bold text-white">{{ number_format($platillo['cantidad'], 0) }}</td>
                                        <td class="py-2.5 text-right font-mono text-emerald-400 font-medium">${{ number_format($platillo['total'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-slate-500">Sin ventas registradas en el periodo.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Márgenes y Costeo de Recetas (BOM) --}}
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-white mb-3 flex items-center justify-between">
                        <span>🎯 Margen Teórico por Receta</span>
                        <span class="text-[11px] text-slate-500 font-normal">Costo vs. Precio</span>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 border-b border-slate-800 text-[10px] uppercase font-semibold">
                                    <th class="py-2">Receta</th>
                                    <th class="py-2 text-right">Costo BOM</th>
                                    <th class="py-2 text-right">P. Venta</th>
                                    <th class="py-2 text-right">Margen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                @forelse ($metricas['margenes_recetas'] as $rec)
                                    <tr class="hover:bg-slate-950/40 transition">
                                        <td class="py-2.5 font-medium text-slate-200">{{ $rec['nombre'] }}</td>
                                        <td class="py-2.5 text-right font-mono text-slate-400">${{ number_format($rec['costo_teorico'], 2) }}</td>
                                        <td class="py-2.5 text-right font-mono text-white font-semibold">${{ number_format($rec['precio_venta'], 2) }}</td>
                                        <td class="py-2.5 text-right font-mono font-bold"
                                            class="{{ $rec['margen_porcentaje'] >= 60 ? 'text-emerald-400' : 'text-amber-400' }}">
                                            {{ $rec['margen_porcentaje'] }}%
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-slate-500">No hay recetas configuradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Mermas Acumuladas de Insumos --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <h3 class="text-sm font-bold text-white mb-3 flex items-center justify-between">
                <span>📉 Mermas de Materia Prima Acumuladas</span>
                <span class="text-[11px] text-slate-500 font-normal">Merma teórica según producción</span>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 text-[10px] uppercase font-semibold">
                            <th class="py-2">Materia Prima / Insumo</th>
                            <th class="py-2 text-center">Unidad</th>
                            <th class="py-2 text-right">Volumen Merma</th>
                            <th class="py-2 text-right">Costo Financiero Desperdicio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($metricas['mermas_acumuladas'] as $merma)
                            <tr class="hover:bg-slate-950/40 transition">
                                <td class="py-2.5 font-medium text-slate-200">{{ $merma['insumo'] }}</td>
                                <td class="py-2.5 text-center font-mono text-slate-400">{{ $merma['unidad'] }}</td>
                                <td class="py-2.5 text-right font-mono text-amber-400 font-semibold">{{ number_format($merma['merma_total'], 3) }}</td>
                                <td class="py-2.5 text-right font-mono text-rose-400 font-bold">${{ number_format($merma['costo_merma'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-500">No hay mermas registradas en el periodo seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>