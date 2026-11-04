<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white leading-tight">
                    Registrar Transferencia entre Almacenes
                </h2>
                <p class="text-xs text-zinc-400 mt-1">
                    Empresa: <span class="font-medium text-purple-300">{{ $empresa->nombre }}</span>
                </p>
            </div>
            <a href="{{ route('inventario.transferencias.index', $empresa) }}"
               class="rounded-lg bg-zinc-800 px-3.5 py-2 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 transition">
                ← Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-8" x-data="formularioTransferencia()">
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            <form action="{{ route('inventario.transferencias.store', $empresa) }}" method="POST" class="space-y-6">
                @csrf

                <!-- Encabezado -->
                <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-purple-300 mb-4">1. Datos de Reubicación</p>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-zinc-300 mb-1">Almacén Origen (Salida) *</label>
                            <select name="almacen_origen_id" required class="admin-form-control text-sm">
                                <option value="">-- Seleccionar origen --</option>
                                @foreach($almacenes as $alm)
                                    <option value="{{ $alm->id }}" @selected(old('almacen_origen_id') == $alm->id)>
                                        {{ $alm->nombre }} ({{ $alm->codigo }})
                                    </option>
                                @endforeach
                            </select>
                            @error('almacen_origen_id') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-zinc-300 mb-1">Almacén Destino (Entrada) *</label>
                            <select name="almacen_destino_id" required class="admin-form-control text-sm">
                                <option value="">-- Seleccionar destino --</option>
                                @foreach($almacenes as $alm)
                                    <option value="{{ $alm->id }}" @selected(old('almacen_destino_id') == $alm->id)>
                                        {{ $alm->nombre }} ({{ $alm->codigo }})
                                    </option>
                                @endforeach
                            </select>
                            @error('almacen_destino_id') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-xs font-medium text-zinc-300 mb-1">Motivo / Justificación *</label>
                        <textarea name="motivo" rows="2" required placeholder="Ej. Reabastecimiento de mostrador sucursal norte"
                            class="admin-form-control text-sm">{{ old('motivo') }}</textarea>
                        @error('motivo') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Líneas -->
                <div class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-purple-300">2. Artículos a Transferir</p>
                        <button type="button" @click="agregarLinea()"
                            class="rounded-lg bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-purple-300 hover:bg-zinc-700 transition">
                            + Agregar Producto
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(linea, index) in lineas" :key="index">
                            <div class="flex flex-col sm:flex-row items-center gap-3 rounded-xl border border-zinc-800 bg-zinc-900/60 p-3">
                                <div class="flex-1 w-full">
                                    <select :name="`lineas[${index}][producto_id]`" required class="admin-form-control text-xs w-full">
                                        <option value="">-- Seleccionar producto --</option>
                                        @foreach($productos as $prod)
                                            <option value="{{ $prod->id }}">{{ $prod->sku }} - {{ $prod->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-full sm:w-36">
                                    <input type="number" step="0.0001" min="0.0001" required placeholder="Cantidad"
                                        :name="`lineas[${index}][cantidad]`" class="admin-form-control text-xs w-full">
                                </div>
                                <button type="button" @click="eliminarLinea(index)" :disabled="lineas.length === 1"
                                    class="rounded-lg p-2 text-zinc-400 hover:text-rose-400 disabled:opacity-30">
                                    ✕
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <a href="{{ route('inventario.transferencias.index', $empresa) }}"
                        class="rounded-lg px-4 py-2 text-xs font-semibold text-zinc-400 hover:text-white transition">
                        Cancelar
                    </a>
                    <button type="submit" class="ion-btn ion-btn--primary px-6 py-2.5 text-xs font-semibold">
                        Confirmar y Ejecutar Transferencia
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function formularioTransferencia() {
            return {
                lineas: [{ producto_id: '', cantidad: '' }],
                agregarLinea() {
                    this.lineas.push({ producto_id: '', cantidad: '' });
                },
                eliminarLinea(index) {
                    if (this.lineas.length > 1) {
                        this.lineas.splice(index, 1);
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
