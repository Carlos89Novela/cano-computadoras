{{-- ===================================================================== --}}
{{-- VISTA: LISTADO DE PROVEEDORES POR EMPRESA (GERENTE / SUBGERENTE)     --}}
{{-- Permite buscar, crear, editar y cambiar el estado de los proveedores  --}}
{{-- de la empresa activa (compatible con todos los giros del ERP).        --}}
{{-- ===================================================================== --}}
<x-app-layout :title="'Proveedores | ' . ($empresa->nombre ?? config('app.name'))">
    {{-- ----------------------------------------------------------------- --}}
    {{-- ENCABEZADO SUPERIOR: EMPRESA ACTIVA Y ACCIÓN DE CREACIÓN          --}}
    {{-- ----------------------------------------------------------------- --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold tracking-tight text-white">
                        Catálogo de Proveedores
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                        {{ $empresa->nombre }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    Administra proveedores para órdenes de compra y abastecimiento de inventario.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('compras.ordenes.index') }}"
                   class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold border border-slate-700 transition">
                    Órdenes de Compra
                </a>

                <a href="{{ route('operacion.proveedores.create', $empresa->id) }}"
                   class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-indigo-600/20 transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nuevo Proveedor
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            {{-- Mensaje de confirmación --}}
            @if (session('status'))
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl text-xs font-medium">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Barra de búsqueda por código, nombre, razón social o RFC --}}
            <form method="GET" action="{{ route('operacion.proveedores.index', $empresa->id) }}"
                  class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-wrap items-center gap-3">
                <input type="text"
                       name="q"
                       value="{{ $buscar ?? '' }}"
                       placeholder="Buscar por código, nombre comercial, razón social o RFC..."
                       class="flex-1 min-w-[240px] !bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs !text-white focus:outline-none focus:border-indigo-500">

                <button type="submit"
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold border border-slate-700 transition">
                    Buscar
                </button>

                @if (! empty($buscar))
                    <a href="{{ route('operacion.proveedores.index', $empresa->id) }}"
                       class="px-3 py-2 text-xs text-slate-400 hover:text-white transition">
                        Limpiar
                    </a>
                @endif
            </form>

            {{-- Tabla de proveedores de la empresa activa --}}
            <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 uppercase font-semibold text-[10px] bg-slate-950/60">
                                <th class="py-3 px-4">Código</th>
                                <th class="py-3 px-4">Proveedor / Razón Social</th>
                                <th class="py-3 px-4">RFC</th>
                                <th class="py-3 px-4">Contacto</th>
                                <th class="py-3 px-4 text-center">Estado</th>
                                <th class="py-3 px-4 text-center">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-800/60">
                            @forelse ($proveedores as $proveedor)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="py-3 px-4 font-mono font-bold text-indigo-400">
                                        {{ $proveedor->codigo ?? '—' }}
                                    </td>

                                    <td class="py-3 px-4 text-slate-200">
                                        <div class="font-semibold">{{ $proveedor->nombre }}</div>
                                        @if ($proveedor->razon_social && $proveedor->razon_social !== $proveedor->nombre)
                                            <div class="text-[11px] text-slate-400">{{ $proveedor->razon_social }}</div>
                                        @endif
                                    </td>

                                    <td class="py-3 px-4 font-mono text-slate-300">
                                        {{ $proveedor->rfc ?? 'S/RFC' }}
                                    </td>

                                    <td class="py-3 px-4 text-slate-300">
                                        <div>{{ $proveedor->contacto ?? '—' }}</div>
                                        <div class="text-[11px] text-slate-400">
                                            {{ $proveedor->telefono ?? '' }}
                                            @if ($proveedor->telefono && $proveedor->correo) · @endif
                                            {{ $proveedor->correo ?? '' }}
                                        </div>
                                    </td>

                                    <td class="py-3 px-4 text-center">
                                        @if ($proveedor->activo)
                                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase bg-emerald-500/10 text-emerald-400 border-emerald-500/20">
                                                Activo
                                            </span>
                                        @else
                                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase bg-rose-500/10 text-rose-400 border-rose-500/20">
                                                Inactivo
                                            </span>
                                        @endif
                                    </td>

                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="{{ route('operacion.proveedores.edit', ['empresa' => $empresa->id, 'proveedor' => $proveedor->id]) }}"
                                               class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-xs font-medium transition">
                                                Editar
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('operacion.proveedores.estado', ['empresa' => $empresa->id, 'proveedor' => $proveedor->id]) }}"
                                                  class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-xs font-medium transition {{ $proveedor->activo ? 'text-amber-400' : 'text-emerald-400' }}">
                                                    {{ $proveedor->activo ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-slate-500 text-xs">
                                        No hay proveedores registrados para {{ $empresa->nombre }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($proveedores->hasPages())
                    <div class="p-4 border-t border-slate-800 bg-slate-950/40">
                        {{ $proveedores->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
