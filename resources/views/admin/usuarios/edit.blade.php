<x-app-layout>
    <x-slot name="header">
        <div class="grid gap-2 border-t border-zinc-700 p-4 sm:grid-cols-2 xl:grid-cols-3">
            <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                Control de acceso
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Responsabilidades de {{ $usuario->name }}
            </h2>
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

            @if ($errors->any())
                <div
                    class="rounded-lg border border-red-700 bg-red-950 p-4 text-red-200"
                    role="alert"
                >
                    <p class="font-semibold">
                        No fue posible actualizar el rol.
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <dl class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg bg-zinc-800 p-4">
                        <dt class="text-xs font-semibold uppercase text-zinc-400">
                            Usuario
                        </dt>

                        <dd class="mt-1 font-semibold text-white">
                            {{ $usuario->name }}
                        </dd>
                    </div>

                    <div class="rounded-lg bg-zinc-800 p-4">
                        <dt class="text-xs font-semibold uppercase text-zinc-400">
                            Correo
                        </dt>

                        <dd class="mt-1 break-all font-semibold text-white">
                            {{ $usuario->email }}
                        </dd>
                    </div>

                    <div class="rounded-lg bg-zinc-800 p-4">
                        <dt class="text-xs font-semibold uppercase text-zinc-400">
                            Rol actual
                        </dt>

                        <dd class="mt-1 font-semibold text-white">
                            {{
                                ucfirst(
                                    $usuario->roles->first()?->name
                                    ?? 'Sin rol'
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Cambiar rol base
                    </h3>

                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Selecciona el puesto principal del usuario. Los permisos
                        individuales se administrarán por separado.
                    </p>
                </div>

                <form action="{{ route('admin.usuarios.rol.update', [
                    'usuario' => $usuario->id,
                ]) }}" method="POST" class="mt-6 space-y-6">
                @csrf
                @method('PATCH')

                    <fieldset>
                        <legend class="font-semibold text-gray-900 dark:text-white">
                            Rol asignable
                        </legend>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($rolesAsignables as $rolDisponible)
                                <label
                                    @class([
                                        'cursor-pointer rounded-lg border p-4 transition',
                                        'border-purple-500 bg-purple-950' =>
                                            old(
                                                'rol',
                                                $usuario->roles->first()?->name
                                            ) === $rolDisponible->name,
                                        'border-zinc-700 bg-zinc-800 hover:border-purple-500' =>
                                            old(
                                                'rol',
                                                $usuario->roles->first()?->name
                                            ) !== $rolDisponible->name,
                                    ])
                                >
                                    <input
                                        type="radio"
                                        name="rol"
                                        value="{{ $rolDisponible->name }}"
                                        class="text-purple-600 focus:ring-purple-500"
                                        @checked(
                                            old(
                                                'rol',
                                                $usuario->roles->first()?->name
                                            ) === $rolDisponible->name
                                        )
                                    >

                                    <span class="ml-2 font-semibold text-white">
                                        {{ ucfirst($rolDisponible->name) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('rol')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </fieldset>

                    <div>
                        <label
                            for="motivo"
                            class="mb-2 block font-semibold text-gray-900 dark:text-white"
                        >
                            Motivo del cambio
                        </label>

                        <textarea
                            id="motivo"
                            name="motivo"
                            rows="4"
                            maxlength="1000"
                            required
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 p-3 text-white placeholder:text-zinc-400 focus:border-purple-500 focus:ring-purple-500"
                            placeholder="Explica por qué se cambia el rol de este usuario."
                        >{{ old('motivo') }}</textarea>

                        <p class="mt-2 text-xs text-gray-500 dark:text-zinc-400">
                            Obligatorio. Máximo 1000 caracteres. El motivo quedará
                            registrado permanentemente en auditoría.
                        </p>

                        @error('motivo')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="rounded-lg border border-amber-700 bg-amber-950 p-4">
                        <p class="font-semibold text-amber-200">
                            Acción auditada
                        </p>

                        <p class="mt-2 text-sm text-amber-100">
                            El sistema registrará el propietario que realizó el cambio,
                            el rol anterior, el rol nuevo, el motivo, la fecha, la sesión
                            y la dirección IP.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            class="rounded-lg bg-purple-600 px-6 py-3 font-semibold text-white transition hover:bg-purple-700"
                            onclick="return confirm('¿Confirmas el cambio de rol de este usuario?');"
                        >
                            Guardar cambio de rol
                        </button>

                        <a href="{{ route('admin.usuarios.index') }}"
                            class="rounded-lg border border-zinc-600 bg-zinc-800 px-6 py-3 font-semibold text-gray-200 transition hover:bg-zinc-700"
                        >
                            Volver a usuarios
                        </a>
                    </div>
                </form>
            </section>
            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Responsabilidades adicionales
                    </h3>

                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Los permisos heredados por el rol aparecen bloqueados.
                        Los permisos adicionales pueden concederse o retirarse.
                    </p>
                </div>

                <form action="{{ route('admin.usuarios.permisos.update', [
                        'usuario' => $usuario->id,
                    ]) }}" method="POST" class="mt-6 space-y-6">
                    @csrf
                    @method('PATCH')

                    @php
                        $seleccionados = old(
                            'permisos',
                            $permisosDirectos
                        );

                        if (! is_array($seleccionados)) {
                            $seleccionados = [];
                        }
                    @endphp

                    <div class="space-y-3 overflow-y-auto pr-2"
                        style="max-height: 620px;"
                        tabindex="0"
                        aria-label="Responsabilidades delegables"
                    >
                    <div class="space-y-3">
                        @foreach ($gruposPermisos as $grupo)
                            @php
                                $tituloGrupo = is_array($grupo)
                                    ? ($grupo['titulo'] ?? 'Responsabilidades')
                                    : 'Responsabilidades';

                                $permisosGrupo = is_array($grupo)
                                    && isset($grupo['permisos'])
                                    && is_array($grupo['permisos'])
                                        ? $grupo['permisos']
                                        : [];

                                $cantidadDirectosGrupo = collect(
                                    array_keys($permisosGrupo)
                                )->filter(
                                    fn (string $permiso): bool =>
                                        in_array(
                                            $permiso,
                                            $seleccionados,
                                            true
                                        )
                                        && ! in_array(
                                            $permiso,
                                            $permisosHeredados,
                                            true
                                        )
                                )->count();

                                $cantidadHeredadosGrupo = collect(
                                    array_keys($permisosGrupo)
                                )->filter(
                                    fn (string $permiso): bool =>
                                        in_array(
                                            $permiso,
                                            $permisosHeredados,
                                            true
                                        )
                                )->count();
                            @endphp

                            <details
                                class="group rounded-xl border border-zinc-700 bg-zinc-800"
                                @if ($loop->first) open @endif
                            >
                                <summary
                                    class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4"
                                >
                                    <div>
                                        <h4 class="font-bold text-white">
                                            {{ $tituloGrupo }}
                                        </h4>

                                        <p class="mt-1 text-xs text-zinc-400">
                                            {{ count($permisosGrupo) }} responsabilidades
                                            · {{ $cantidadHeredadosGrupo }} heredadas
                                            · {{ $cantidadDirectosGrupo }} adicionales
                                        </p>
                                    </div>

                                    <span
                                        class="text-xl font-bold text-zinc-400 transition-transform group-open:rotate-180"
                                        aria-hidden="true"
                                    >
                                        ⌄
                                    </span>
                                </summary>

                                <div
                                     class="grid gap-2 border-t border-zinc-700 p-4 sm:grid-cols-2 xl:grid-cols-3">

                                    @foreach ($permisosGrupo as $permiso => $etiqueta)
                                        @php
                                            $heredado = in_array(
                                                $permiso,
                                                $permisosHeredados,
                                                true
                                            );

                                            $directo = in_array(
                                                $permiso,
                                                $seleccionados,
                                                true
                                            );
                                        @endphp

                                        <label
                                            @class([
                                                'flex min-h-20 items-start gap-3 rounded-lg border p-3',
                                                'cursor-not-allowed border-blue-900 bg-blue-950/40 opacity-75' =>
                                                    $heredado,
                                                'cursor-pointer border-zinc-600 bg-zinc-900 transition hover:border-purple-500' =>
                                                    ! $heredado,
                                            ])
                                        >
                                            <input
                                                type="checkbox"
                                                name="permisos[]"
                                                value="{{ $permiso }}"
                                                class="mt-1 shrink-0 rounded border-zinc-600 bg-zinc-800 text-purple-600 focus:ring-purple-500"
                                                @checked($heredado || $directo)
                                                @disabled($heredado)
                                            >

                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-semibold text-white">
                                                    {{ $etiqueta }}
                                                </span>

                                                @if ($heredado)
                                                    <span
                                                        class="mt-1 inline-flex rounded-full bg-blue-900 px-2 py-0.5 text-xs font-semibold text-blue-100"
                                                    >
                                                        Heredado
                                                    </span>
                                                @elseif ($directo)
                                                    <span
                                                        class="mt-1 inline-flex rounded-full bg-purple-900 px-2 py-0.5 text-xs font-semibold text-purple-100"
                                                    >
                                                        Adicional
                                                    </span>
                                                @else
                                                    <span class="mt-1 block truncate text-xs text-zinc-500">
                                                        {{ $permiso }}
                                                    </span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>

                    @error('permisos')
                        <p class="text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('permisos.*')
                        <p class="text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror

                    <div>
                        <label
                            for="motivo_permisos"
                            class="mb-2 block font-semibold text-gray-900 dark:text-white"
                        >
                            Motivo del cambio de responsabilidades
                        </label>

                        <textarea
                            id="motivo_permisos"
                            name="motivo_permisos"
                            rows="4"
                            maxlength="1000"
                            required
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 p-3 text-white placeholder:text-zinc-400 focus:border-purple-500 focus:ring-purple-500"
                            placeholder="Explica por qué se conceden o retiran estas responsabilidades."
                        >{{ old('motivo_permisos') }}</textarea>

                        <p class="mt-2 text-xs text-gray-500 dark:text-zinc-400">
                            Obligatorio. El motivo, los permisos anteriores y los
                            permisos nuevos quedarán registrados en auditoría.
                        </p>

                        @error('motivo_permisos')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div
                        class="rounded-lg border border-amber-700 bg-amber-950 p-4"
                    >
                        <p class="font-semibold text-amber-200">
                            Consideraciones
                        </p>

                        <ul
                            class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-100"
                        >
                            <li>
                                Los permisos heredados no pueden retirarse desde
                                esta sección.
                            </li>

                            <li>
                                Para retirar un permiso heredado es necesario cambiar
                                el rol base.
                            </li>

                            <li>
                                Los permisos reservados al propietario nunca aparecen
                                en esta lista.
                            </li>
                        </ul>
                    </div>

                    <button
                        type="submit"
                        class="rounded-lg bg-purple-600 px-6 py-3 font-semibold text-white transition hover:bg-purple-700"
                        onclick="return confirm('¿Confirmas el cambio de responsabilidades de este usuario?');"
                    >
                        Guardar responsabilidades adicionales
                    </button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
