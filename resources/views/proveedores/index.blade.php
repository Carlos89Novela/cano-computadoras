<x-app-layout>
    {{-- =====================================================
         ENCABEZADO
         Muestra el nombre de la empresa y el acceso para
         registrar un proveedor nuevo.
    ====================================================== --}}

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                    Compras e inventario
                </p>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Proveedores de {{ $empresa->nombre }}
                </h2>
            </div>

            @can('productos.crear')
                <a
                    href="{{ route('proveedores.create', [
                        'empresa' => $empresa->id,
                    ]) }}"
                    class="inline-flex items-center rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-purple-700"
                >
                    Nuevo proveedor
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto w-full max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            {{-- =================================================
                 MENSAJES
                 Muestra confirmaciones y errores de validación.
            ================================================== --}}

            @if (session('success'))
                <div
                    class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200"
                    role="status"
                >
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="rounded-lg border border-red-700 bg-red-950 p-4 text-red-200"
                    role="alert"
                >
                    <p class="font-semibold">
                        No fue posible completar la operación.
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- =================================================
                 RESUMEN
                 Presenta el total y los estados de la página
                 actual del listado paginado.
            ================================================== --}}

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">
                        Total de proveedores
                    </p>

                    <p class="mt-2 text-3xl font-bold text-purple-400">
                        {{ $proveedores->total() }}
                    </p>
                </div>

                <div class="rounded-xl border border-green-900 bg-green-950/50 p-5">
                    <p class="text-sm text-green-300">
                        Activos en esta página
                    </p>

                    <p class="mt-2 text-3xl font-bold text-green-200">
                        {{ $proveedores->where('activo', true)->count() }}
                    </p>
                </div>

                <div class="rounded-xl border border-red-900 bg-red-950/50 p-5">
                    <p class="text-sm text-red-300">
                        Inactivos en esta página
                    </p>

                    <p class="mt-2 text-3xl font-bold text-red-200">
                        {{ $proveedores->where('activo', false)->count() }}
                    </p>
                </div>
            </section>

            {{-- =================================================
                 LISTADO
                 Muestra los datos comerciales y de contacto.
            ================================================== --}}

            <section class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow">
                @if ($proveedores->isEmpty())
                    <div class="p-10 text-center">
                        <h3 class="text-xl font-bold text-white">
                            No existen proveedores registrados
                        </h3>

                        <p class="mt-2 text-sm text-zinc-400">
                            Registra proveedores para utilizarlos posteriormente
                            en las entradas y recepciones de mercancía.
                        </p>

                        @can('productos.crear')
                            <a
                                href="{{ route('proveedores.create', [
                                    'empresa' => $empresa->id,
                                ]) }}"
                                class="mt-5 inline-flex rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                            >
                                Crear primer proveedor
                            </a>
                        @endcan
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-800">
                            <thead class="bg-zinc-800">
                                <tr>
                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300">
                                        Proveedor
                                    </th>

                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300">
                                        Datos fiscales
                                    </th>

                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300">
                                        Contacto
                                    </th>

                                    <th class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300">
                                        Estado
                                    </th>

                                    <th class="px-5 py-4 text-right text-xs font-semibold uppercase text-zinc-300">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-zinc-800">
                                @foreach ($proveedores as $proveedor)
                                    <tr class="bg-zinc-900 transition-colors hover:bg-zinc-800">
                                        {{-- =============================
                                             IDENTIFICACIÓN DEL PROVEEDOR
                                        ============================== --}}

                                        <td class="px-5 py-4 align-top">
                                            <p class="font-semibold text-white">
                                                {{ $proveedor->nombre }}
                                            </p>

                                            <p class="mt-1 text-sm text-purple-300">
                                                {{ $proveedor->codigo }}
                                            </p>

                                            @if ($proveedor->notas)
                                                <p class="mt-2 max-w-sm text-xs text-zinc-400">
                                                    {{ $proveedor->notas }}
                                                </p>
                                            @endif
                                        </td>

                                        {{-- =============================
                                             INFORMACIÓN FISCAL
                                        ============================== --}}

                                        <td class="px-5 py-4 align-top text-sm text-zinc-300">
                                            <p>
                                                {{
                                                    $proveedor->razon_social
                                                    ?: 'Sin razón social'
                                                }}
                                            </p>

                                            <p class="mt-1 text-zinc-400">
                                                RFC:
                                                {{ $proveedor->rfc ?: 'No registrado' }}
                                            </p>
                                        </td>

                                        {{-- =============================
                                             INFORMACIÓN DE CONTACTO
                                        ============================== --}}

                                        <td class="px-5 py-4 align-top text-sm text-zinc-300">
                                            <p>
                                                {{
                                                    $proveedor->contacto
                                                    ?: 'Sin contacto'
                                                }}
                                            </p>

                                            <p class="mt-1 text-zinc-400">
                                                {{ $proveedor->telefono ?: 'Sin teléfono' }}
                                            </p>

                                            <p class="mt-1 break-all text-zinc-400">
                                                {{ $proveedor->correo ?: 'Sin correo' }}
                                            </p>
                                        </td>

                                        {{-- =============================
                                             ESTADO DEL PROVEEDOR
                                        ============================== --}}

                                        <td class="px-5 py-4 align-top">
                                            @if ($proveedor->activo)
                                                <span class="inline-flex rounded-full bg-green-950 px-3 py-1 text-xs font-semibold text-green-200">
                                                    Activo
                                                </span>
                                            @else
                                                <span class="inline-flex rounded-full bg-red-950 px-3 py-1 text-xs font-semibold text-red-200">
                                                    Inactivo
                                                </span>

                                                @if ($proveedor->motivo_desactivacion)
                                                    <p class="mt-2 max-w-xs text-xs text-zinc-400">
                                                        {{ $proveedor->motivo_desactivacion }}
                                                    </p>
                                                @endif
                                            @endif
                                        </td>

                                        {{-- =============================
                                             ACCIONES
                                             Por ahora agregamos edición.
                                             El modal de estado se incorpora
                                             en el siguiente bloque.
                                        ============================== --}}

                                        <td class="px-5 py-4 text-right align-top">
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                @can('productos.actualizar')
                                                    <a
                                                        href="{{ route('proveedores.edit', [
                                                            'empresa' => $empresa->id,
                                                            'proveedor' => $proveedor->id,
                                                        ]) }}"
                                                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                                    >
                                                        Editar
                                                    </a>
                                                @endcan

                                                @can('productos.cambiar_estado')
                                                    <div
                                                        x-data="{ abierto: false }"
                                                        class="inline-block"
                                                    >
                                                        {{-- Botón que abre el modal. --}}
                                                        <button
                                                            type="button"
                                                            class="rounded-lg border border-zinc-600 bg-zinc-800 px-4 py-2 text-sm font-semibold text-zinc-200 transition hover:bg-zinc-700"
                                                            @click="abierto = true"
                                                        >
                                                            {{ $proveedor->activo ? 'Desactivar' : 'Reactivar' }}
                                                        </button>

                                                        {{-- Fondo y contenedor principal del modal. --}}
                                                        <div
                                                            x-cloak
                                                            x-show="abierto"
                                                            x-transition.opacity
                                                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                                                            role="dialog"
                                                            aria-modal="true"
                                                            aria-labelledby="titulo-estado-proveedor-{{ $proveedor->id }}"
                                                            @keydown.escape.window="abierto = false"
                                                        >
                                                            <div
                                                                class="w-full max-w-lg rounded-2xl border border-zinc-700 bg-zinc-950 p-6 text-left shadow-2xl"
                                                                @click.outside="abierto = false"
                                                            >
                                                                {{-- Encabezado del modal. --}}
                                                                <div class="flex items-start justify-between gap-4">
                                                                    <div>
                                                                        <p class="text-sm font-semibold uppercase tracking-wide text-purple-400">
                                                                            Cambio de estado
                                                                        </p>

                                                                        <h3
                                                                            id="titulo-estado-proveedor-{{ $proveedor->id }}"
                                                                            class="mt-1 text-xl font-bold text-white"
                                                                        >
                                                                            {{
                                                                                $proveedor->activo
                                                                                    ? 'Desactivar proveedor'
                                                                                    : 'Reactivar proveedor'
                                                                            }}
                                                                        </h3>

                                                                        <p class="mt-2 text-sm text-zinc-400">
                                                                            {{ $proveedor->nombre }}
                                                                            · {{ $proveedor->codigo }}
                                                                        </p>
                                                                    </div>

                                                                    <button
                                                                        type="button"
                                                                        class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-800 hover:text-white"
                                                                        aria-label="Cerrar"
                                                                        @click="abierto = false"
                                                                    >
                                                                        ✕
                                                                    </button>
                                                                </div>

                                                                {{-- Formulario auditado de cambio de estado. --}}
                                                                <form
                                                                    action="{{ route('proveedores.estado.update', [
                                                                        'empresa' => $empresa->id,
                                                                        'proveedor' => $proveedor->id,
                                                                    ]) }}"
                                                                    method="POST"
                                                                    class="mt-6 space-y-5"
                                                                >
                                                                    @csrf
                                                                    @method('PATCH')

                                                                    <input
                                                                        type="hidden"
                                                                        name="activar"
                                                                        value="{{ $proveedor->activo ? '0' : '1' }}"
                                                                    >

                                                                    {{-- Motivo obligatorio. --}}
                                                                    <div>
                                                                        <label
                                                                            for="motivo-proveedor-{{ $proveedor->id }}"
                                                                            class="mb-2 block font-semibold text-white"
                                                                        >
                                                                            Motivo del cambio
                                                                        </label>

                                                                        <textarea
                                                                            id="motivo-proveedor-{{ $proveedor->id }}"
                                                                            name="motivo"
                                                                            rows="5"
                                                                            maxlength="1000"
                                                                            required
                                                                            class="min-h-32 w-full resize-y rounded-lg border border-zinc-700 bg-zinc-900 p-3 text-white placeholder:text-zinc-500 focus:border-purple-500 focus:ring-purple-500"
                                                                            placeholder="Explica el motivo del cambio de estado."
                                                                        ></textarea>

                                                                        <p class="mt-2 text-xs text-zinc-400">
                                                                            El motivo quedará registrado permanentemente en auditoría.
                                                                        </p>
                                                                    </div>

                                                                    {{-- Consecuencia de la operación. --}}
                                                                    <div class="rounded-lg border border-amber-900 bg-amber-950/40 p-4">
                                                                        <p class="text-sm text-amber-100">
                                                                            @if ($proveedor->activo)
                                                                                El proveedor dejará de estar disponible para
                                                                                nuevas compras, pero conservará su historial.
                                                                            @else
                                                                                El proveedor volverá a estar disponible para
                                                                                compras y recepciones de mercancía.
                                                                            @endif
                                                                        </p>
                                                                    </div>

                                                                    {{-- Botones del modal. --}}
                                                                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                                                        <button
                                                                            type="button"
                                                                            class="rounded-lg border border-zinc-600 bg-zinc-800 px-5 py-2.5 font-semibold text-zinc-200 transition hover:bg-zinc-700"
                                                                            @click="abierto = false"
                                                                        >
                                                                            Cancelar
                                                                        </button>

                                                                        <button
                                                                            type="submit"
                                                                            @class([
                                                                                'rounded-lg px-5 py-2.5 font-semibold text-white transition',
                                                                                'bg-red-600 hover:bg-red-700' => $proveedor->activo,
                                                                                'bg-green-600 hover:bg-green-700' => ! $proveedor->activo,
                                                                            ])
                                                                        >
                                                                            {{
                                                                                $proveedor->activo
                                                                                    ? 'Confirmar desactivación'
                                                                                    : 'Confirmar reactivación'
                                                                            }}
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- =============================================
                         PAGINACIÓN
                    ============================================== --}}

                    <div class="border-t border-zinc-800 p-5">
                        {{ $proveedores->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
