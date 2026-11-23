{{-- ===================================================================== --}}
{{-- VISTA: REGISTRAR NUEVO PROVEEDOR POR EMPRESA                        --}}
{{-- Vincula el proveedor a la empresa activa y permite regresar directo   --}}
{{-- a Crear Orden de Compra si se abrió con ?retorno=orden_compra.      --}}
{{-- ===================================================================== --}}
<x-app-layout :title="'Nuevo Proveedor | ' . ($empresa->nombre ?? config('app.name'))">
    <div class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6 space-y-6">

        {{-- Encabezado --}}
        <header class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold tracking-tight text-white">
                        Registrar Proveedor
                    </h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                        {{ $empresa->nombre }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    Captura los datos fiscales y de contacto del proveedor para {{ $empresa->nombre }}.
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if (($retorno ?? '') === 'orden_compra')
                    <a href="{{ route('compras.ordenes.create') }}"
                       class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold border border-slate-700 transition">
                        ← Volver a Orden de Compra
                    </a>
                @else
                    <a href="{{ route('operacion.proveedores.index', $empresa->id) }}"
                       class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold border border-slate-700 transition">
                        ← Volver a Proveedores
                    </a>
                @endif
            </div>
        </header>

        {{-- Errores de validación --}}
        @if ($errors->any())
            <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-2xl text-xs space-y-1">
                <p class="font-bold">Revisa los datos ingresados:</p>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ route('operacion.proveedores.store', $empresa->id) }}"
              class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5 text-xs">
            @csrf

            @if (! empty($retorno))
                <input type="hidden" name="retorno" value="{{ $retorno }}">
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Código interno (opcional; se genera automático si se deja vacío) --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Código (opcional)</label>
                    <input type="text"
                           name="codigo"
                           value="{{ old('codigo') }}"
                           placeholder="Ej. PROV-001 (auto si vacío)"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white font-mono focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Nombre comercial o del proveedor --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Nombre Comercial / Proveedor *</label>
                    <input type="text"
                           name="nombre"
                           value="{{ old('nombre') }}"
                           required
                           placeholder="Ej. Distribuidora de Carnes y Abarrotes"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Razón social --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Razón Social</label>
                    <input type="text"
                           name="razon_social"
                           value="{{ old('razon_social') }}"
                           placeholder="Si se omite, se usa el nombre"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                {{-- RFC --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">RFC</label>
                    <input type="text"
                           name="rfc"
                           value="{{ old('rfc') }}"
                           maxlength="20"
                           placeholder="Ej. XAXX010101000"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white font-mono uppercase focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Persona de contacto --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Contacto</label>
                    <input type="text"
                           name="contacto"
                           value="{{ old('contacto') }}"
                           placeholder="Nombre del ejecutivo o vendedor"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Teléfono --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Teléfono</label>
                    <input type="text"
                           name="telefono"
                           value="{{ old('telefono') }}"
                           placeholder="Ej. 662 123 4567"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Correo electrónico --}}
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Correo Electrónico</label>
                    <input type="email"
                           name="correo"
                           value="{{ old('correo') }}"
                           placeholder="ventas@proveedor.com"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Dirección --}}
                <div class="md:col-span-2">
                    <label class="text-slate-300 block mb-1 font-medium">Dirección</label>
                    <input type="text"
                           name="direccion"
                           value="{{ old('direccion') }}"
                           placeholder="Calle, número, colonia, ciudad..."
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                {{-- Notas --}}
                <div class="md:col-span-3">
                    <label class="text-slate-300 block mb-1 font-medium">Notas / Condiciones de Entrega</label>
                    <textarea name="notas"
                              rows="2"
                              placeholder="Días de reparto, crédito, condiciones especiales..."
                              class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">{{ old('notas') }}</textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                <a href="{{ ($retorno ?? '') === 'orden_compra' ? route('compras.ordenes.create') : route('operacion.proveedores.index', $empresa->id) }}"
                   class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/20 transition">
                    Guardar Proveedor
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
