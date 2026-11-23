{{-- ===================================================================== --}}
{{-- VISTA: LISTADO DE ÓRDENES DE COMPRA A PROVEEDORES (MULTI-TENANT)     --}}
{{-- Muestra únicamente las órdenes pertenecientes a la empresa activa,   --}}
{{-- con acceso a creación, detalle y generación de PDF.                  --}}
{{-- ===================================================================== --}}
<x-app-layout :title="'Órdenes de Compra | ' . ($empresa->nombre ?? config('app.name'))">
    {{-- ----------------------------------------------------------------- --}}
    {{-- ENCABEZADO SUPERIOR: TÍTULO, EMPRESA ACTIVA Y ACCIÓN DE CREACIÓN  --}}
    {{-- ----------------------------------------------------------------- --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold tracking-tight text-white">
                        Órdenes de Compra a Proveedores
                    </h2>

                    {{-- Etiqueta visible de la empresa activa en contexto --}}
                    @if (isset($empresa))
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                            {{ $empresa->nombre }}
                        </span>
                    @endif
                </div>

                <p class="text-xs text-slate-400 mt-1">
                    Control de pedidos, autorizaciones y seguimiento de entregas a almacén.
                </p>
            </div>

            {{-- Botón para crear una nueva orden de compra si el usuario tiene permiso --}}
            @php
                $usuarioActual = auth()->user();
                $puedeCrearCompra = $usuarioActual && (
                    $usuarioActual->can('compras.crear')
                    || $usuarioActual->hasRole('administrador')
                    || $usuarioActual->hasRole('supervisor')
                );
            @endphp

            @if ($puedeCrearCompra)
                <a href="{{ route('compras.ordenes.create') }}"
                   class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-indigo-600/20 transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nueva Orden de Compra
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            {{-- ------------------------------------------------------------- --}}
            {{-- ALERTA DE ESTADO / CONFIRMACIÓN                               --}}
            {{-- ------------------------------------------------------------- --}}
            @if (session('status'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl text-xs font-medium">
                    {{ session('status') }}
                </div>
            @endif

            {{-- ------------------------------------------------------------- --}}
            {{-- TABLA PRINCIPAL DE ÓRDENES DE COMPRA                          --}}
            {{-- ------------------------------------------------------------- --}}
            <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 uppercase font-semibold text-[10px] bg-slate-950/60">
                                <th class="py-3 px-4">Folio</th>
                                <th class="py-3 px-4">Fecha Emisión</th>
                                <th class="py-3 px-4">Proveedor</th>
                                <th class="py-3 px-4">Sucursal / Almacén</th>
                                <th class="py-3 px-4 text-center">Estado</th>
                                <th class="py-3 px-4 text-right">Total</th>
                                <th class="py-3 px-4 text-center">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-800/60">
                            @forelse ($ordenes as $orden)
                                <tr class="hover:bg-slate-800/30 transition">
                                    {{-- Folio de la orden --}}
                                    <td class="py-3 px-4 font-mono font-bold text-indigo-400">
                                        {{ $orden->folio }}
                                    </td>

                                    {{-- Fecha de emisión formateada --}}
                                    <td class="py-3 px-4 text-slate-300">
                                        {{ $orden->fecha_emision?->format('d/m/Y') }}
                                    </td>

                                    {{-- Razón social / nombre y RFC del proveedor --}}
                                    <td class="py-3 px-4 text-slate-200">
                                        <div class="font-semibold">
                                            {{ $orden->proveedor?->razon_social ?? $orden->proveedor?->nombre_comercial ?? $orden->proveedor?->nombre ?? 'Sin proveedor' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 font-mono">
                                            {{ $orden->proveedor?->rfc }}
                                        </div>
                                    </td>

                                    {{-- Sucursal y almacén destino --}}
                                    <td class="py-3 px-4 text-slate-300">
                                        <div>{{ $orden->sucursal?->nombre }}</div>
                                        <div class="text-[10px] text-slate-500">{{ $orden->almacen?->nombre }}</div>
                                    </td>

                                    {{-- Badge de estado actual --}}
                                    <td class="py-3 px-4 text-center">
                                        @php
                                            $badgeClases = match ($orden->estado) {
                                                'borrador' => 'bg-slate-800 text-slate-300 border-slate-700',
                                                'solicitada' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                                'autorizada' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                                'recibida_parcial' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
                                                'recibida_total' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                                'cancelada' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                                default => 'bg-slate-800 text-slate-400 border-slate-700',
                                            };
                                        @endphp
                                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase {{ $badgeClases }}">
                                            {{ str_replace('_', ' ', $orden->estado) }}
                                        </span>
                                    </td>

                                    {{-- Importe total --}}
                                    <td class="py-3 px-4 text-right font-mono font-bold text-white text-sm">
                                        ${{ number_format((float) $orden->total, 2) }}
                                    </td>

                                    {{-- Acciones: ver detalle y descargar/abrir PDF --}}
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="{{ route('compras.ordenes.show', $orden->id) }}"
                                               class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-xs font-medium transition">
                                                Detalle
                                            </a>
                                            <a href="{{ route('compras.ordenes.pdf', $orden->id) }}"
                                               target="_blank"
                                               class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-rose-400 border border-slate-700 rounded-lg text-xs transition"
                                               title="Descargar PDF">
                                                PDF
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-500 text-xs">
                                        No hay órdenes de compra registradas actualmente para {{ $empresa->nombre ?? 'esta empresa' }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Paginación si existen más de 15 registros --}}
                @if ($ordenes->hasPages())
                    <div class="p-4 border-t border-slate-800 bg-slate-950/40">
                        {{ $ordenes->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
