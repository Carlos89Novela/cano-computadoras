<x-app-layout>
    <x-slot name="header">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    class="text-sm font-semibold uppercase tracking-wide text-purple-500"
                >
                    Catálogo de productos
                </p>

                <h2
                    class="text-2xl font-bold text-gray-900 dark:text-white"
                >
                    Categorías de {{ $empresa->nombre }}
                </h2>
            </div>

            @can('productos.crear')
                <a href="{{ route('productos.categorias.create', [
                'empresa' => $empresa->id,
                ]) }}"
                class="mt-5 inline-flex rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700">
                Nueva categoría
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div
            class="mx-auto w-full max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8"
        >
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

            <section class="grid gap-4 sm:grid-cols-3">
                <div
                    class="rounded-xl border border-zinc-800 bg-zinc-900 p-5"
                >
                    <p class="text-sm text-zinc-400">
                        Total de categorías
                    </p>

                    <p class="mt-2 text-3xl font-bold text-purple-400">
                        {{ $categorias->total() }}
                    </p>
                </div>

                <div
                    class="rounded-xl border border-green-900 bg-green-950/50 p-5"
                >
                    <p class="text-sm text-green-300">
                        Activas en esta página
                    </p>

                    <p class="mt-2 text-3xl font-bold text-green-200">
                        {{ $categorias->where('activo', true)->count() }}
                    </p>
                </div>

                <div
                    class="rounded-xl border border-red-900 bg-red-950/50 p-5"
                >
                    <p class="text-sm text-red-300">
                        Inactivas en esta página
                    </p>

                    <p class="mt-2 text-3xl font-bold text-red-200">
                        {{ $categorias->where('activo', false)->count() }}
                    </p>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow"
            >
                @if ($categorias->isEmpty())
                    <div class="p-10 text-center">
                        <h3 class="text-xl font-bold text-white">
                            No existen categorías registradas
                        </h3>

                        <p class="mt-2 text-sm text-zinc-400">
                            Las categorías permitirán organizar el catálogo
                            de productos e inventario.
                        </p>

                        @can('productos.crear')
                            <a href="{{ route('productos.categorias.create', ['empresa' => $empresa->id,])}}"
                                class="mt-5 inline-flex rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                            >
                                Crear primera categoría
                            </a>
                        @endcan
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-800">
                            <thead class="bg-zinc-800">
                                <tr>
                                    <th
                                        class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300"
                                    >
                                        Categoría
                                    </th>

                                    <th
                                        class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300"
                                    >
                                        Estado
                                    </th>

                                    <th
                                        class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300"
                                    >
                                        Registro
                                    </th>

                                    <th
                                        class="px-5 py-4 text-right text-xs font-semibold uppercase text-zinc-300"
                                    >
                                        Acciones
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-zinc-800">
                                @foreach ($categorias as $categoria)
                                    <tr
                                        class="bg-zinc-900 transition-colors hover:bg-zinc-800"
                                    >
                                        <td class="px-5 py-4">
                                            <p class="font-semibold text-white">
                                                {{ $categoria->nombre }}
                                            </p>

                                            <p
                                                class="mt-1 max-w-xl text-sm text-zinc-400"
                                            >
                                                {{
                                                    $categoria->descripcion
                                                    ?: 'Sin descripción'
                                                }}
                                            </p>
                                        </td>

                                        <td class="px-5 py-4">
                                            @if ($categoria->activo)
                                                <span
                                                    class="inline-flex rounded-full bg-green-950 px-3 py-1 text-xs font-semibold text-green-200"
                                                >
                                                    Activa
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex rounded-full bg-red-950 px-3 py-1 text-xs font-semibold text-red-200"
                                                >
                                                    Inactiva
                                                </span>

                                                @if (
                                                    $categoria
                                                        ->motivo_desactivacion
                                                )
                                                    <p
                                                        class="mt-2 max-w-xs text-xs text-zinc-400"
                                                    >
                                                        {{
                                                            $categoria
                                                                ->motivo_desactivacion
                                                        }}
                                                    </p>
                                                @endif
                                            @endif
                                        </td>

                                        <td
                                            class="px-5 py-4 text-sm text-zinc-300"
                                        >
                                            <p>
                                                Creada por:
                                                {{
                                                    $categoria
                                                        ->creadoPor
                                                        ?->name
                                                    ?? 'Usuario no disponible'
                                                }}
                                            </p>

                                            @if (
                                                $categoria
                                                    ->actualizadoPor
                                                    !== null
                                            )
                                                <p class="mt-1 text-zinc-400">
                                                    Actualizada por:
                                                    {{
                                                        $categoria
                                                            ->actualizadoPor
                                                            ->name
                                                    }}
                                                </p>
                                            @endif
                                        </td>

                                        <td class="min-w[360px] px-5 py-4 align-top">
                                            <div
                                                class="flex flex-wrap items-start justify-end gap-2"
                                            >
                                                @can('productos.actualizar')
                                                    <a href="{{ route('productos.categorias.edit', ['empresa' => $empresa->id, 'categoria' => $categoria->id,]) }}"
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
                                                        <button
                                                            type="button"
                                                            class="rounded-lg border border-zinc-600 bg-zinc-800 px-4 py-2 text-sm font-semibold text-zinc-200 transition hover:bg-zinc-700"
                                                            @click="abierto = true"
                                                        >
                                                            {{
                                                                $categoria->activo
                                                                    ? 'Desactivar'
                                                                    : 'Reactivar'
                                                            }}
                                                        </button>

                                                        <div
                                                            x-cloak
                                                            x-show="abierto"
                                                            x-transition.opacity
                                                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                                                            role="dialog"
                                                            aria-modal="true"
                                                            aria-labelledby="titulo-estado-{{ $categoria->id }}"
                                                            @keydown.escape.window="abierto = false"
                                                        >
                                                            <div
                                                                class="w-full max-w-lg rounded-2xl border border-zinc-700 bg-zinc-950 p-6 shadow-2xl"
                                                                @click.outside="abierto = false"
                                                            >
                                                                <div class="flex items-start justify-between gap-4">
                                                                    <div>
                                                                        <p
                                                                            class="text-sm font-semibold uppercase tracking-wide text-purple-400"
                                                                        >
                                                                            Cambio de estado
                                                                        </p>

                                                                        <h3
                                                                            id="titulo-estado-{{ $categoria->id }}"
                                                                            class="mt-1 text-xl font-bold text-white"
                                                                        >
                                                                            {{
                                                                                $categoria->activo
                                                                                    ? 'Desactivar categoría'
                                                                                    : 'Reactivar categoría'
                                                                            }}
                                                                        </h3>

                                                                        <p class="mt-2 text-sm text-zinc-400">
                                                                            {{ $categoria->nombre }}
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

                                                                <form action="{{ route('productos.categorias.estado.update', [
                                                                        'empresa' => $empresa->id,
                                                                        'categoria' => $categoria->id,
                                                                    ]) }}">
                                                                    @csrf
                                                                    @method('PATCH')

                                                                    <input
                                                                        type="hidden"
                                                                        name="activar"
                                                                        value="{{ $categoria->activo ? '0' : '1' }}"
                                                                    >

                                                                    <div>
                                                                        <label
                                                                            for="motivo-{{ $categoria->id }}"
                                                                            class="mb-2 block font-semibold text-white"
                                                                        >
                                                                            Motivo del cambio
                                                                        </label>

                                                                        <textarea
                                                                            id="motivo-{{ $categoria->id }}"
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

                                                                    <div
                                                                        class="rounded-lg border border-amber-900 bg-amber-950/40 p-4"
                                                                    >
                                                                        <p class="text-sm text-amber-100">
                                                                            {{
                                                                                $categoria->activo
                                                                                    ? 'La categoría dejará de estar disponible para nuevos productos, pero conservará su historial.'
                                                                                    : 'La categoría volverá a estar disponible para organizar productos.'
                                                                            }}
                                                                        </p>
                                                                    </div>

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
                                                                                'bg-red-600 hover:bg-red-700' => $categoria->activo,
                                                                                'bg-green-600 hover:bg-green-700' => ! $categoria->activo,
                                                                            ])
                                                                        >
                                                                            {{
                                                                                $categoria->activo
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

                    <div class="border-t border-zinc-800 p-5">
                        {{ $categorias->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
