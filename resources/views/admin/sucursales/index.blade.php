<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                    Organización
                </p>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Sucursales de {{ $empresa->nombre }}
                </h2>
            </div>

            <a href="{{ route('admin.empresas.sucursales.create', [
                    'empresa' => $empresa->id,]) }}"
                    class="mt-5 inline-flex rounded-lg bg-green-600 px-5 py-3 font-semibold text-white transition hover:bg-green-700">
                Abrir nueva sucursal
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto w-full max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div
                    class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200"
                    role="status"
                >
                    {{ session('success') }}
                </div>
            @endif

            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">
                        Sucursales registradas
                    </p>

                    <p class="mt-2 text-3xl font-bold text-purple-400">
                        {{ $empresa->sucursales->count() }}
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">
                        Sucursales activas
                    </p>

                    <p class="mt-2 text-3xl font-bold text-green-400">
                        {{ $empresa->sucursales->where('activo', true)->count() }}
                    </p>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">
                        Almacenes virtuales
                    </p>

                    <p class="mt-2 text-3xl font-bold text-blue-400">
                        {{ $empresa->almacenes->count() }}
                    </p>
                </div>
            </section>

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-zinc-400">
                        Red operativa
                    </p>

                    <h3 class="mt-1 text-xl font-bold text-white">
                        Sucursales y almacenes físicos
                    </h3>

                    <p class="mt-2 text-sm text-zinc-400">
                        Cada sucursal mantiene existencias independientes en su almacén principal.
                    </p>
                </div>

                @if ($empresa->sucursales->isEmpty())
                    <div class="mt-6 rounded-xl border border-dashed border-zinc-700 bg-zinc-950 p-8 text-center">
                        <p class="font-semibold text-white">
                            No existen sucursales registradas
                        </p>

                        <p class="mt-2 text-sm text-zinc-400">
                            Abre la primera sucursal para crear automáticamente
                            su almacén principal y el almacén virtual de tránsito.
                        </p>

                         <a href=" {{ route('admin.empresas.sucursales.create', [ 'empresa' => $empresa->id,])  }}"
                            class="mt-5 inline-flex rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                        >
                            Abrir primera sucursal
                        </a>
                    </div>
                @else
                    <div class="mt-6 grid gap-5 xl:grid-cols-2">
                        @foreach ($empresa->sucursales as $sucursal)
                            <article class="rounded-xl border border-zinc-700 bg-zinc-950 p-5">
                                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-lg font-bold text-white">
                                                {{ $sucursal->nombre }}
                                            </h4>

                                            @if ($sucursal->es_principal)
                                                <span class="rounded-full bg-purple-950 px-3 py-1 text-xs font-semibold text-purple-200">
                                                    Principal
                                                </span>
                                            @endif

                                            @if ($sucursal->activo)
                                                <span class="rounded-full bg-green-950 px-3 py-1 text-xs font-semibold text-green-200">
                                                    Activa
                                                </span>
                                            @else
                                                <span class="rounded-full bg-red-950 px-3 py-1 text-xs font-semibold text-red-200">
                                                    Inactiva
                                                </span>
                                            @endif
                                        </div>

                                        <p class="mt-2 text-sm text-zinc-400">
                                            Código: {{ $sucursal->codigo }}
                                        </p>
                                    </div>
                                </div>

                                <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-lg bg-zinc-900 p-3">
                                        <dt class="text-xs font-semibold uppercase text-zinc-500">
                                            Gerente
                                        </dt>

                                        <dd class="mt-1 text-sm font-medium text-white">
                                            {{
                                                $sucursal->usuarios
                                                    ->first(
                                                        fn ($usuario) =>
                                                            (bool) $usuario->pivot->es_gerente
                                                            && (bool) $usuario->pivot->activo
                                                    )
                                                    ?->name
                                                ?? 'Sin gerente'
                                            }}
                                        </dd>
                                    </div>

                                    <div class="rounded-lg bg-zinc-900 p-3">
                                        <dt class="text-xs font-semibold uppercase text-zinc-500">
                                            Ubicación
                                        </dt>

                                        <dd class="mt-1 text-sm font-medium text-white">
                                            {{ collect([
                                                $sucursal->ciudad,
                                                $sucursal->estado,
                                            ])->filter()->implode(', ') ?: 'No registrada' }}
                                        </dd>
                                    </div>
                                </dl>

                                <div class="mt-5">
                                    <p class="text-xs font-semibold uppercase text-zinc-500">
                                        Almacenes de la sucursal
                                    </p>

                                    <div class="mt-3 space-y-2">
                                        @forelse ($sucursal->almacenes as $almacen)
                                            <div class="flex flex-col justify-between gap-2 rounded-lg border border-zinc-800 bg-zinc-900 p-3 sm:flex-row sm:items-center">
                                                <div>
                                                    <p class="font-semibold text-white">
                                                        {{ $almacen->nombre }}
                                                    </p>

                                                    <p class="mt-1 text-xs text-zinc-400">
                                                        {{ $almacen->codigo }}
                                                        · {{ ucfirst($almacen->tipo) }}
                                                    </p>
                                                </div>

                                                <span
                                                    @class([
                                                        'w-fit rounded-full px-3 py-1 text-xs font-semibold',
                                                        'bg-green-950 text-green-200' => $almacen->activo,
                                                        'bg-red-950 text-red-200' => ! $almacen->activo,
                                                    ])
                                                >
                                                    {{ $almacen->activo ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </div>
                                        @empty
                                            <p class="text-sm text-amber-400">
                                                La sucursal no tiene almacenes registrados.
                                            </p>
                                        @endforelse
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-xl border border-blue-900 bg-blue-950/40 p-6">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-300">
                        Almacenes virtuales
                    </p>

                    <h3 class="mt-1 text-xl font-bold text-white">
                        Tránsito entre sucursales
                    </h3>
                </div>

                @if ($empresa->almacenes->isEmpty())
                    <p class="mt-4 text-sm text-blue-100">
                        El almacén virtual de tránsito se creará automáticamente
                        al abrir la primera sucursal.
                    </p>
                @else
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        @foreach ($empresa->almacenes as $almacen)
                            <article class="rounded-lg border border-blue-900 bg-zinc-950 p-4">
                                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                    <div>
                                        <p class="font-semibold text-white">
                                            {{ $almacen->nombre }}
                                        </p>

                                        <p class="mt-1 text-sm text-zinc-400">
                                            {{ $almacen->codigo }}
                                            · {{ ucfirst($almacen->tipo) }}
                                        </p>
                                    </div>

                                    <span class="w-fit rounded-full bg-blue-950 px-3 py-1 text-xs font-semibold text-blue-200">
                                        Virtual
                                    </span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
