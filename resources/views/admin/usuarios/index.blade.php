<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                Control de acceso
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Usuarios y responsabilidades
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-6">
            @if (session('success'))
                <div
                    class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200"
                    role="status"
                >
                    {{ session('success') }}
                </div>
            @endif

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <form
                    action="{{ route('admin.usuarios.index') }}       >
                    <div>
                        <label
                            for="buscar"
                            class="mb-2 block font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Buscar usuario
                        </label>

                        <input
                            id="buscar"
                            name="buscar"
                            type="search"
                            value="{{ $busqueda }}"
                            class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            placeholder="Nombre o correo"
                        >
                    </div>

                    <div>
                        <label
                            for="rol"
                            class="mb-2 block font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Rol base
                        </label>

                        <select
                            id="rol"
                            name="rol"
                            class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        >
                            <option value="">
                                Todos los roles
                            </option>

                            @foreach ($roles as $rolDisponible)
                                <option
                                    value="{{ $rolDisponible->name }}"
                                    @selected(
                                        $rol === $rolDisponible->name
                                    )
                                >
                                    {{ ucfirst($rolDisponible->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end gap-3">
                        <button
                            type="submit"
                            class="rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
                        >
                            Filtrar
                        </button>

                        <a
                            href="{{ route('admin.usuarios.index') }}"
                            class="rounded-lg border border-zinc-600 px-5 py-3 font-semibold text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-zinc-800"
                        >
                            Limpiar
                        </a>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl bg-white shadow dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-700">
                        <thead class="bg-zinc-800">
                            <tr>
                                <th
                                    class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300"
                                >
                                    Usuario
                                </th>

                                <th
                                    class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300"
                                >
                                    Rol base
                                </th>

                                <th
                                    class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300"
                                >
                                    Permisos directos
                                </th>

                                <th
                                    class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300"
                                >
                                    Verificación
                                </th>

                                <th
                                    class="px-5 py-4 text-right text-xs font-semibold uppercase text-zinc-300"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-800">
                            @forelse ($usuarios as $usuario)
                                <tr class="bg-zinc-900 text-zinc-100 transition-colors hover:bg-zinc-800">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div>
                                                <p class="font-semibold text-white">
                                                    {{ $usuario->name }}
                                                </p>

                                                <p class="text-sm text-zinc-400">
                                                    {{ $usuario->email }}
                                                </p>
                                            </div>

                                            @if ($usuario->esPropietario())
                                                <span
                                                    class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-200"
                                                >
                                                    Propietario
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-5 py-4">
                                        <span
                                            class="rounded-full bg-purple-100 px-3 py-1 text-sm font-semibold text-purple-700 dark:bg-purple-950 dark:text-purple-200"
                                        >
                                            {{ ucfirst($usuario->roles->first()?->name ?? 'Sin rol') }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 text-sm text-zinc-200">
                                        {{ $usuario->permissions->count() }}
                                    </td>

                                    <td class="px-5 py-4">
                                        @if ($usuario->email_verified_at !== null)
                                            <span class="font-medium text-green-500">
                                                Verificado
                                            </span>
                                        @else
                                            <span class="font-medium text-amber-500">
                                                Pendiente
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-5 py-4 text-right">
                                        @if ($usuario->esPropietario())
                                            <span class="text-sm text-zinc-300">
                                                Cuenta protegida
                                            </span>
                                        @else
                                            <a
                                                href="{{ route('admin.usuarios.edit', [
                                                    'usuario' => $usuario->id,
                                                ]) }}"
                                                class="inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                                            >
                                                Administrar responsabilidades
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="5"
                                        class="px-5 py-10 text-center text-zinc-400"
                                    >
                                        No se encontraron usuarios.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-zinc-800 p-5">
                    {{ $usuarios->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
