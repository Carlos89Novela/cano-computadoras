<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Reparación {{ $orden->folio }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('admin.ordenes.pdf', ['orden' => $orden->id]) }}"
                    target="_blank" rel="noopener noreferrer"
                    class="rounded-lg bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 transition">
                    📄 Descargar PDF
                </a>
                <a href="{{ route('admin.ordenes.index') }}"
                    class="rounded-lg bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 transition">
                    ← Volver a órdenes
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ tab: 'general' }">
        @if (session('imprimir_comprobante_url'))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    window.open('{{ session('imprimir_comprobante_url') }}', '_blank');
                });
            </script>
        @endif
        <div class="mx-auto max-w-6xl px-4 sm:px-6">

            <!-- 1. Encabezado Resumen Fijo -->
            <div class="mb-6 rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-purple-300">
                            Resumen de la reparación
                        </p>
                        <h3 class="mt-1 text-2xl font-bold text-white tracking-tight">
                            {{ $orden->folio }}
                        </h3>
                    </div>

                    @include('admin.ordenes.partials.estado-badge', [
                        'estado' => $orden->estado,
                    ])
                </div>

                <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Cliente</p>
                        <p class="mt-1 text-sm font-semibold text-white truncate">
                            {{ $orden->user?->name ?? 'Cliente no disponible' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Equipo</p>
                        <p class="mt-1 text-sm font-semibold text-white truncate">
                            {{ trim(($orden->equipo?->marca ?? '').' '.($orden->equipo?->modelo ?? '')) ?: 'Equipo no disponible' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Costo estimado</p>
                        <p class="mt-1 text-sm font-semibold text-white">
                            {{ $orden->costo_estimado !== null ? '$'.number_format((float) $orden->costo_estimado, 2) : 'Pendiente' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-400">Costo final</p>
                        <p class="mt-1 text-sm font-semibold text-emerald-400">
                            {{ $orden->costo_final !== null ? '$'.number_format((float) $orden->costo_final, 2) : 'Pendiente' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. Barra de Navegación por Pestañas (Tabs) -->
            <div class="mb-6 flex space-x-2 border-b border-zinc-800 pb-2">
                <button type="button"
                    @click="tab = 'general'"
                    :class="tab === 'general' ? 'bg-purple-600 text-white font-semibold shadow-md' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60'"
                    class="rounded-lg px-4 py-2 text-sm transition">
                    🛠️ Diagnóstico y Estado
                </button>

                @can('inventario.ver')
                    <button type="button"
                        @click="tab = 'inventario'"
                        :class="tab === 'inventario' ? 'bg-purple-600 text-white font-semibold shadow-md' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60'"
                        class="rounded-lg px-4 py-2 text-sm transition flex items-center gap-2">
                        <span>📦 Refacciones e Inventario</span>
                        @if($orden->reservasInventario->count() > 0)
                            <span class="rounded-full bg-purple-950 px-2 py-0.5 text-xs text-purple-200 border border-purple-700/50">
                                {{ $orden->reservasInventario->count() }}
                            </span>
                        @endif
                    </button>
                @endcan

                <button type="button"
                    @click="tab = 'historial'"
                    :class="tab === 'historial' ? 'bg-purple-600 text-white font-semibold shadow-md' : 'text-zinc-400 hover:text-white hover:bg-zinc-800/60'"
                    class="rounded-lg px-4 py-2 text-sm transition flex items-center gap-2">
                    <span>🕒 Historial de Trabajo</span>
                    @if($orden->historial->count() > 0)
                        <span class="rounded-full bg-zinc-800 px-2 py-0.5 text-xs text-zinc-300">
                            {{ $orden->historial->count() }}
                        </span>
                    @endif
                </button>

                @can('deliver', $orden)
                    <button type="button"
                        @click="tab = 'entrega'"
                        :class="tab === 'entrega' ? 'bg-emerald-600 text-white font-semibold shadow-md' : 'text-emerald-400 hover:bg-emerald-950/40'"
                        class="rounded-lg px-4 py-2 text-sm transition">
                        ✓ Entrega al Cliente
                    </button>
                @endcan
            </div>

            <!-- 3. Contenido de las Pestañas -->

            <!-- Pestaña A: Diagnóstico y Estado General -->
            <div x-show="tab === 'general'" x-cloak>
                <form action="{{ route('admin.ordenes.update', ['orden' => $orden->id]) }}"
                    method="POST"
                    class="space-y-6 rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="rounded-xl border border-zinc-800/60 bg-zinc-900/40 p-4">
                            <p class="text-xs uppercase tracking-wide text-zinc-400">Cliente titular</p>
                            <p class="mt-1 font-semibold text-white">{{ $orden->user?->name ?? 'No disponible' }}</p>
                            <p class="text-xs text-zinc-400">{{ $orden->user?->email ?? 'Sin correo' }}</p>
                        </div>

                        <div class="rounded-xl border border-zinc-800/60 bg-zinc-900/40 p-4">
                            <p class="text-xs uppercase tracking-wide text-zinc-400">Equipo en taller</p>
                            <p class="mt-1 font-semibold text-white">
                                {{ trim(($orden->equipo?->marca ?? '').' '.($orden->equipo?->modelo ?? '')) ?: 'No disponible' }}
                            </p>
                            <p class="text-xs text-zinc-400">Tipo: {{ $orden->equipo?->tipo ?? 'No especificado' }}</p>
                        </div>
                    </div>

                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-zinc-400">Falla reportada por el cliente</p>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900/80 p-4 text-sm text-zinc-200">
                            {{ $orden->problema_reportado }}
                        </div>
                    </div>

                    <div>
                        <label for="estado" class="mb-2 block text-sm font-medium text-zinc-200">
                            Estado operativo de la reparación
                        </label>
                        <select id="estado" name="estado" required class="admin-form-control text-sm">
                            @foreach ($estados as $estado)
                                <option value="{{ $estado }}" @selected(old('estado', $orden->estado) === $estado)>
                                    {{ $estado }}
                                </option>
                            @endforeach
                        </select>
                        @error('estado')
                            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="diagnostico" class="mb-2 block text-sm font-medium text-zinc-200">
                            Diagnóstico técnico y resolución
                        </label>
                        <textarea id="diagnostico" name="diagnostico" rows="4" maxlength="3000"
                            class="admin-form-control text-sm"
                            placeholder="Describe el diagnóstico técnico detallado.">{{ old('diagnostico', $orden->diagnostico) }}</textarea>
                        @error('diagnostico')
                            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="costo_estimado" class="mb-2 block text-sm font-medium text-zinc-200">
                                Costo estimado ($)
                            </label>
                            <input id="costo_estimado" name="costo_estimado" type="number" step="0.01" min="0" max="999999.99"
                                value="{{ old('costo_estimado', $orden->costo_estimado) }}"
                                class="admin-form-control text-sm" placeholder="0.00">
                            @error('costo_estimado')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="costo_final" class="mb-2 block text-sm font-medium text-zinc-200">
                                Costo final ($)
                            </label>
                            <input id="costo_final" name="costo_final" type="number" step="0.01" min="0" max="999999.99"
                                value="{{ old('costo_final', $orden->costo_final) }}"
                                class="admin-form-control text-sm" placeholder="0.00">
                            @error('costo_final')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="comentario" class="mb-2 block text-sm font-medium text-zinc-200">
                            Comentario interno (historial de taller)
                        </label>
                        <textarea id="comentario" name="comentario" rows="2" maxlength="2000"
                            class="admin-form-control text-sm"
                            placeholder="Opcional. Visible solo para el personal administrativo y técnico.">{{ old('comentario') }}</textarea>
                    </div>

                    <div>
                        <label for="mensaje_cliente" class="mb-2 block text-sm font-medium text-zinc-200">
                            Mensaje para el cliente (notificación por correo)
                        </label>
                        <textarea id="mensaje_cliente" name="mensaje_cliente" rows="2" maxlength="2000"
                            class="admin-form-control text-sm"
                            placeholder="Opcional. Se enviará automáticamente por correo al cliente.">{{ old('mensaje_cliente') }}</textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-800">
                        <button type="submit" class="ion-btn ion-btn--primary">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>

            <!-- Pestaña B: Refacciones e Inventario -->
            @can('inventario.ver')
                <div x-show="tab === 'inventario'" x-cloak class="space-y-6">
                    @can('inventario.consumir')
                        <section class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                            <div class="mb-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-purple-300">
                                    Comprometer Material
                                </p>
                                <h3 class="mt-1 text-lg font-bold text-white">
                                    Reservar nueva refacción
                                </h3>
                            </div>

                            @forelse ($empresasInventario as $empresaInventario)
                                <form action="{{ route('admin.ordenes.inventario.reservar', ['empresa' => $empresaInventario->id, 'orden' => $orden->id]) }}"
                                    method="POST" class="mt-4 space-y-4">
                                    @csrf

                                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-zinc-300">Almacén</label>
                                            <select name="almacen_id" required class="admin-form-control text-sm">
                                                <option value="">Selecciona almacén</option>
                                                @foreach ($empresaInventario->existenciasAlmacen->pluck('almacen')->filter()->unique('id') as $alm)
                                                    <option value="{{ $alm->id }}">{{ $alm->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-zinc-300">Producto / Refacción</label>
                                            <select name="producto_id" required class="admin-form-control text-sm">
                                                <option value="">Selecciona producto</option>
                                                @foreach ($empresaInventario->existenciasAlmacen->pluck('producto')->filter()->unique('id') as $prod)
                                                    <option value="{{ $prod->id }}">{{ $prod->nombre }} ({{ $prod->sku }})</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-zinc-300">Cantidad</label>
                                            <input type="number" name="cantidad" step="0.0001" min="0.0001" placeholder="1.0000" required
                                                class="admin-form-control text-sm">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-zinc-300">Motivo</label>
                                            <input type="text" name="motivo" placeholder="Ej. Cambio de módulo" required
                                                class="admin-form-control text-sm">
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" class="ion-btn ion-btn--primary text-xs py-2">
                                            + Reservar refacción
                                        </button>
                                    </div>
                                </form>
                            @empty
                                <div class="rounded-xl border border-dashed border-zinc-800 p-4 text-sm text-zinc-400">
                                    No hay existencias disponibles para reservar en los almacenes registrados.
                                </div>
                            @endforelse
                        </section>
                    @endcan

                    <!-- Listado de Refacciones Comprometidas -->
                    <section class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                        <h3 class="mb-4 text-lg font-bold text-white">
                            Refacciones comprometidas en esta orden
                        </h3>

                        @if ($orden->reservasInventario->isEmpty())
                            <div class="rounded-xl border border-dashed border-zinc-800 bg-zinc-900/40 p-6 text-center text-sm text-zinc-400">
                                Esta orden aún no tiene refacciones o materiales reservados.
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach ($orden->reservasInventario as $reserva)
                                    <article class="rounded-xl border border-zinc-800 bg-zinc-900/90 p-5">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="font-semibold text-white">
                                                    {{ $reserva->producto?->nombre ?? 'Producto no disponible' }}
                                                </p>
                                                <p class="text-xs text-zinc-400 mt-0.5">
                                                    SKU: {{ $reserva->producto?->sku ?? 'Sin SKU' }} · Almacén: {{ $reserva->almacen?->nombre ?? 'N/D' }}
                                                </p>
                                            </div>

                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                                @if($reserva->estado === 'consumida') bg-emerald-600/20 text-emerald-300
                                                @elseif($reserva->estado === 'liberada') bg-zinc-700/40 text-zinc-300
                                                @elseif($reserva->estado === 'parcial') bg-amber-600/20 text-amber-300
                                                @else bg-purple-600/20 text-purple-200 @endif">
                                                {{ ucfirst($reserva->estado) }}
                                            </span>
                                        </div>

                                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                            <div class="rounded-lg bg-zinc-950 p-2.5 text-center">
                                                <p class="text-[10px] uppercase tracking-wide text-zinc-500">Reservado</p>
                                                <p class="mt-0.5 font-semibold text-white text-sm">
                                                    {{ number_format((float) $reserva->cantidad_reservada, 4) }}
                                                </p>
                                            </div>
                                            <div class="rounded-lg bg-zinc-950 p-2.5 text-center">
                                                <p class="text-[10px] uppercase tracking-wide text-zinc-500">Consumido</p>
                                                <p class="mt-0.5 font-semibold text-emerald-400 text-sm">
                                                    {{ number_format((float) $reserva->cantidad_consumida, 4) }}
                                                </p>
                                            </div>
                                            <div class="rounded-lg bg-zinc-950 p-2.5 text-center">
                                                <p class="text-[10px] uppercase tracking-wide text-zinc-500">Liberado</p>
                                                <p class="mt-0.5 font-semibold text-zinc-300 text-sm">
                                                    {{ number_format((float) $reserva->cantidad_liberada, 4) }}
                                                </p>
                                            </div>
                                            <div class="rounded-lg bg-zinc-950 p-2.5 text-center">
                                                <p class="text-[10px] uppercase tracking-wide text-zinc-500">Pendiente</p>
                                                <p class="mt-0.5 font-semibold text-purple-300 text-sm">
                                                    {{ number_format((float) $reserva->cantidadPendiente(), 4) }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-3 text-xs text-zinc-400">
                                            <span>Motivo: <strong class="text-zinc-200">{{ $reserva->motivo }}</strong></span>
                                            <span class="mx-2">·</span>
                                            <span>Por: <strong class="text-zinc-200">{{ $reserva->reservadoPor?->name ?? 'N/D' }}</strong></span>
                                        </div>

                                        @if ($reserva->estaActiva() && (float) $reserva->cantidadPendiente() > 0)
                                            <div class="mt-4 border-t border-zinc-800/80 pt-3" x-data="{ accion: null }">
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button"
                                                        @click="accion = (accion === 'consumir' ? null : 'consumir')"
                                                        class="rounded-lg bg-emerald-600/20 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-600/30 transition">
                                                        ✓ Consumir pieza en reparación
                                                    </button>
                                                    <button type="button"
                                                        @click="accion = (accion === 'liberar' ? null : 'liberar')"
                                                        class="rounded-lg bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 transition">
                                                        ↩ Liberar al stock
                                                    </button>
                                                </div>

                                                <!-- Formulario para Consumir -->
                                                <div x-show="accion === 'consumir'" x-cloak class="mt-3 rounded-xl border border-emerald-900/50 bg-emerald-950/20 p-4">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">Confirmar consumo físico</p>
                                                    <form action="{{ route('admin.ordenes.inventario.consumir', ['empresa' => $reserva->empresa_id, 'orden' => $orden->id]) }}" method="POST" class="mt-3 space-y-3">
                                                        @csrf
                                                        <input type="hidden" name="almacen_id" value="{{ $reserva->almacen_id }}">
                                                        <input type="hidden" name="producto_id" value="{{ $reserva->producto_id }}">

                                                        <div class="grid gap-3 sm:grid-cols-2">
                                                            <div>
                                                                <label class="block text-xs text-zinc-400">Cantidad (Máx: {{ $reserva->cantidadPendiente() }})</label>
                                                                <input type="number" step="0.0001" min="0.0001" max="{{ $reserva->cantidadPendiente() }}"
                                                                    name="cantidad" value="{{ $reserva->cantidadPendiente() }}" required
                                                                    class="admin-form-control mt-1 text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs text-zinc-400">Motivo de consumo</label>
                                                                <input type="text" name="motivo" placeholder="Ej. Instalado en el equipo" required
                                                                    class="admin-form-control mt-1 text-sm">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-end gap-2 pt-2">
                                                            <button type="button" @click="accion = null" class="px-3 py-1 text-xs text-zinc-400 hover:text-white">Cancelar</button>
                                                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Confirmar consumo</button>
                                                        </div>
                                                    </form>
                                                </div>

                                                <!-- Formulario para Liberar -->
                                                <div x-show="accion === 'liberar'" x-cloak class="mt-3 rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-300">Devolver refacción al stock</p>
                                                    <form action="{{ route('admin.ordenes.inventario.liberar', ['empresa' => $reserva->empresa_id, 'orden' => $orden->id]) }}" method="POST" class="mt-3 space-y-3">
                                                        @csrf
                                                        <input type="hidden" name="almacen_id" value="{{ $reserva->almacen_id }}">
                                                        <input type="hidden" name="producto_id" value="{{ $reserva->producto_id }}">

                                                        <div class="grid gap-3 sm:grid-cols-2">
                                                            <div>
                                                                <label class="block text-xs text-zinc-400">Cantidad (Máx: {{ $reserva->cantidadPendiente() }})</label>
                                                                <input type="number" step="0.0001" min="0.0001" max="{{ $reserva->cantidadPendiente() }}"
                                                                    name="cantidad" value="{{ $reserva->cantidadPendiente() }}" required
                                                                    class="admin-form-control mt-1 text-sm">
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs text-zinc-400">Motivo de liberación</label>
                                                                <input type="text" name="motivo" placeholder="Ej. No se requirió el componente" required
                                                                    class="admin-form-control mt-1 text-sm">
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-end gap-2 pt-2">
                                                            <button type="button" @click="accion = null" class="px-3 py-1 text-xs text-zinc-400 hover:text-white">Cancelar</button>
                                                            <button type="submit" class="rounded-lg bg-zinc-700 px-4 py-1.5 text-xs font-semibold text-white hover:bg-zinc-600">Confirmar liberación</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>
            @endcan

            <!-- Pestaña C: Historial de Trabajo -->
            <div x-show="tab === 'historial'" x-cloak class="rounded-2xl border border-zinc-800 bg-zinc-950/80 p-6 shadow-sm">
                <h3 class="mb-5 text-lg font-bold text-white">
                    Historial de cambios y seguimiento técnico
                </h3>

                @if ($orden->historial->isEmpty())
                    <div class="rounded-xl border border-dashed border-zinc-800 bg-zinc-900/40 p-6 text-center text-sm text-zinc-400">
                        Aún no hay cambios registrados en esta reparación.
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach ($orden->historial as $registro)
                            <div class="flex gap-4 rounded-xl border border-zinc-800/80 bg-zinc-900/60 p-4">
                                <div class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-purple-600/20 text-purple-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                                    </svg>
                                </div>

                                <div class="flex-1">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        @if ($registro->estado)
                                            @include('admin.ordenes.partials.estado-badge', ['estado' => $registro->estado])
                                        @else
                                            <span class="text-sm font-semibold text-zinc-200">Actualización técnica</span>
                                        @endif
                                        <span class="text-xs text-zinc-400">
                                            {{ $registro->created_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                        </span>
                                    </div>

                                    @if (filled($registro->comentarios))
                                        <div class="mt-2.5 rounded-lg bg-zinc-950/70 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">Comentario interno</p>
                                            <p class="mt-1 text-sm text-zinc-300">{{ $registro->comentarios }}</p>
                                        </div>
                                    @endif

                                    @if (filled($registro->mensaje_cliente))
                                        <div class="mt-2.5 rounded-lg border border-blue-900/60 bg-blue-950/30 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-blue-300">Mensaje enviado al cliente</p>
                                            <p class="mt-1 text-sm text-blue-100">{{ $registro->mensaje_cliente }}</p>
                                        </div>
                                    @endif

                                    <p class="mt-2 text-xs text-zinc-400">
                                        Por <strong class="text-zinc-300">{{ $registro->usuario?->name ?? 'Usuario del sistema' }}</strong>
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Pestaña D: Entrega del Equipo -->
            @can('deliver', $orden)
                <div x-show="tab === 'entrega'" x-cloak class="rounded-2xl border border-emerald-800 bg-emerald-950/40 p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">
                        Equipo listo para entrega
                    </p>
                    <h3 class="mt-1 text-xl font-bold text-white">
                        Confirmar entrega física al cliente
                    </h3>
                    <p class="mt-1 text-sm text-emerald-200/80">
                        Esta acción registrará la fecha de entrega y marcará la orden como finalizada.
                    </p>

                    <form action="{{ route('operacion.ordenes.entregar', ['orden' => $orden->id]) }}" method="POST" class="mt-6 space-y-5">
                        @csrf
                        <div>
                            <label for="comentario_entrega_admin" class="block text-sm font-medium text-emerald-100">
                                Comentario de entrega opcional
                            </label>
                            <textarea id="comentario_entrega_admin" name="comentario" rows="3" maxlength="2000"
                                class="mt-2 w-full rounded-xl border border-emerald-700/60 bg-zinc-900 p-3 text-white placeholder:text-zinc-500 focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="Por ejemplo: Equipo entregado probado con el cliente, cargador original y conformidad de servicio.">{{ old('comentario') }}</textarea>
                            @error('comentario')
                                <p class="mt-2 text-xs text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Selector de Comprobante -->
                        <div>
                            <label class="block text-sm font-medium text-emerald-100 mb-2">
                                ¿Cómo deseas expedir el comprobante de entrega?
                            </label>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-emerald-800/80 bg-zinc-900/80 p-3.5 transition hover:border-emerald-500">
                                    <input type="radio" name="accion_comprobante" value="imprimir" checked
                                        class="text-emerald-500 focus:ring-emerald-500">
                                    <div>
                                        <p class="text-sm font-semibold text-white">📄 Imprimir PDF</p>
                                        <p class="text-xs text-zinc-400">Abre el comprobante en una nueva pestaña listo para imprimir.</p>
                                    </div>
                                </label>

                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-emerald-800/80 bg-zinc-900/80 p-3.5 transition hover:border-emerald-500">
                                    <input type="radio" name="accion_comprobante" value="correo"
                                        class="text-emerald-500 focus:ring-emerald-500">
                                    <div>
                                        <p class="text-sm font-semibold text-white">✉️ Enviar por correo</p>
                                        <p class="text-xs text-zinc-400">Notifica al cliente con el enlace de entrega y resumen digital.</p>
                                    </div>
                                </label>

                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-emerald-800/80 bg-zinc-900/80 p-3.5 transition hover:border-emerald-500">
                                    <input type="radio" name="accion_comprobante" value="ambas"
                                        class="text-emerald-500 focus:ring-emerald-500">
                                    <div>
                                        <p class="text-sm font-semibold text-white">⚡ Ambas opciones</p>
                                        <p class="text-xs text-zinc-400">Envía el correo al cliente y abre el documento para impresión física.</p>
                                    </div>
                                </label>
                            </div>
                            @error('accion_comprobante')
                                <p class="mt-2 text-xs text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pt-2">
                            <button type="submit"
                                onclick="return confirm('¿Confirmas que el equipo fue entregado físicamente al cliente?');"
                                class="rounded-lg bg-emerald-600 px-6 py-2.5 font-semibold text-white transition hover:bg-emerald-500 shadow-lg">
                                Confirmar entrega
                            </button>
                        </div>
                    </form>
                </div>
            @endcan

        </div>
    </div>
</x-app-layout>
