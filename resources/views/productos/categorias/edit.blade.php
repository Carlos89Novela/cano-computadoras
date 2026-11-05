<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                Catálogo de productos
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Editar categoría
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto w-full max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
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
                        No fue posible actualizar la categoría.
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-zinc-950 p-4">
                        <dt class="text-xs font-semibold uppercase text-zinc-500">
                            Empresa
                        </dt>

                        <dd class="mt-1 font-semibold text-white">
                            {{ $empresa->nombre }}
                        </dd>
                    </div>

                    <div class="rounded-lg bg-zinc-950 p-4">
                        <dt class="text-xs font-semibold uppercase text-zinc-500">
                            Estado
                        </dt>

                        <dd class="mt-1">
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
                            @endif
                        </dd>
                    </div>

                    <div class="rounded-lg bg-zinc-950 p-4">
                        <dt class="text-xs font-semibold uppercase text-zinc-500">
                            Creada por
                        </dt>

                        <dd class="mt-1 font-semibold text-white">
                            {{
                                $categoria->creadoPor?->name
                                ?? 'Usuario no disponible'
                            }}
                        </dd>
                    </div>
                </dl>
            </section>

            <form action="{{ route('productos.categorias.update', [
                    'empresa' => $empresa->id,
                    'categoria' => $categoria->id,
                ]) }}"
                method="POST"
                class="space-y-6 rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow">
                @csrf
                @method('PUT')

                <div>
                    <label
                        for="nombre"
                        class="mb-2 block font-semibold text-zinc-200"
                    >
                        Nombre de la categoría
                    </label>

                    <input
                        id="nombre"
                        name="nombre"
                        type="text"
                        required
                        maxlength="150"
                        value="{{ old('nombre', $categoria->nombre) }}"
                        @class([
                            'w-full rounded-lg border bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:ring-purple-500',
                            'border-zinc-700 focus:border-purple-500' => ! $errors->has('nombre'),
                            'border-red-600 focus:border-red-500' => $errors->has('nombre'),
                        ])
                    >

                    @error('nombre')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="descripcion"
                        class="mb-2 block font-semibold text-zinc-200"
                    >
                        Descripción
                    </label>

                    <textarea
                        id="descripcion"
                        name="descripcion"
                        rows="5"
                        maxlength="2000"
                        @class([
                            'w-full rounded-lg border bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:ring-purple-500',
                            'border-zinc-700 focus:border-purple-500' => ! $errors->has('descripcion'),
                            'border-red-600 focus:border-red-500' => $errors->has('descripcion'),
                        ])
                        placeholder="Describe los productos que pertenecen a esta categoría."
                    >{{ old('descripcion', $categoria->descripcion) }}</textarea>

                    <p class="mt-2 text-xs text-zinc-400">
                        Opcional. Máximo 2000 caracteres.
                    </p>

                    @error('descripcion')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="rounded-lg border border-blue-900 bg-blue-950/40 p-4">
                    <p class="font-semibold text-blue-200">
                        Modificación auditada
                    </p>

                    <p class="mt-2 text-sm text-blue-100">
                        Se registrarán el nombre y la descripción anteriores,
                        los valores nuevos, el usuario, la fecha, la sesión
                        y la dirección IP.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        class="rounded-lg bg-purple-600 px-6 py-3 font-semibold text-white transition hover:bg-purple-700"
                    >
                        Guardar cambios
                    </button>
                    <a href="{{ route('productos.categorias.index', [
                     $empresa->id,
                        ]) }}"
                        class="rounded-lg border border-zinc-600 bg-zinc-800 px-6 py-3 font-semibold text-zinc-200 transition hover:bg-zinc-700"
                    >
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
