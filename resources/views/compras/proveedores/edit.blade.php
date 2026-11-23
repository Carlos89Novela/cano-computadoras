{{-- ===================================================================== --}}
{{-- VISTA: EDITAR PROVEEDOR EXISTENTE DE LA EMPRESA ACTIVA               --}}
{{-- Permite actualizar datos fiscales, contacto, estado y observaciones.  --}}
{{-- ===================================================================== --}}
<x-app-layout :title="'Editar Proveedor | ' . ($empresa->nombre ?? config('app.name'))">
    <div class="min-h-screen bg-slate-950 text-slate-100 p-4 lg:p-6 space-y-6">

        {{-- Encabezado --}}
        <header class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-800">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold tracking-tight text-white">
                        Editar Proveedor: {{ $proveedor->nombre }}
                    </h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                        {{ $empresa->nombre }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    Modifica los datos del proveedor seleccionado.
                </p>
            </div>

            <a href="{{ route('operacion.proveedores.index', $empresa->id) }}"
               class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold border border-slate-700 transition">
                ← Volver a Proveedores
            </a>
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
              action="{{ route('operacion.proveedores.update', ['empresa' => $empresa->id, 'proveedor' => $proveedor->id]) }}"
              class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5 text-xs">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Código</label>
                    <input type="text"
                           name="codigo"
                           value="{{ old('codigo', $proveedor->codigo) }}"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white font-mono focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Nombre Comercial / Proveedor *</label>
                    <input type="text"
                           name="nombre"
                           value="{{ old('nombre', $proveedor->nombre) }}"
                           required
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Razón Social</label>
                    <input type="text"
                           name="razon_social"
                           value="{{ old('razon_social', $proveedor->razon_social) }}"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-slate-300 block mb-1 font-medium">RFC</label>
                    <input type="text"
                           name="rfc"
                           value="{{ old('rfc', $proveedor->rfc) }}"
                           maxlength="20"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white font-mono uppercase focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Contacto</label>
                    <input type="text"
                           name="contacto"
                           value="{{ old('contacto', $proveedor->contacto) }}"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Teléfono</label>
                    <input type="text"
                           name="telefono"
                           value="{{ old('telefono', $proveedor->telefono) }}"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-slate-300 block mb-1 font-medium">Correo Electrónico</label>
                    <input type="email"
                           name="correo"
                           value="{{ old('correo', $proveedor->correo) }}"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <label class="text-slate-300 block mb-1 font-medium">Dirección</label>
                    <input type="text"
                           name="direccion"
                           value="{{ old('direccion', $proveedor->direccion) }}"
                           class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="md:col-span-3 flex items-center gap-2 pt-1">
                    <input type="hidden" name="activo" value="0">
                    <input id="activo"
                           type="checkbox"
                           name="activo"
                           value="1"
                           @checked(old('activo', $proveedor->activo))
                           class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500">
                    <label for="activo" class="text-slate-200 font-medium">
                        Proveedor activo (disponible para nuevas órdenes de compra)
                    </label>
                </div>

                <div class="md:col-span-3">
                    <label class="text-slate-300 block mb-1 font-medium">Notas / Condiciones de Entrega</label>
                    <textarea name="notas"
                              rows="2"
                              class="w-full !bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 !text-white focus:outline-none focus:border-indigo-500">{{ old('notas', $proveedor->notas) }}</textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                <a href="{{ route('operacion.proveedores.index', $empresa->id) }}"
                   class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/20 transition">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
