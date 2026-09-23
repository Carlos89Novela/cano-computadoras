<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                Organización
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Abrir nueva sucursal
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto w-full max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div
                    class="rounded-lg border border-red-700 bg-red-950 p-4 text-red-200"
                    role="alert"
                >
                    <p class="font-semibold">
                        No fue posible abrir la sucursal.
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                <dl class="grid gap-4 sm:grid-cols-2">
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
                            Tipo de apertura
                        </dt>

                        <dd class="mt-1 font-semibold text-white">
                            {{
                                $tieneSucursales
                                    ? 'Sucursal adicional'
                                    : 'Primera sucursal'
                            }}
                        </dd>
                    </div>
                </dl>
            </section>

             $empresa->id,
            <form action="{{ route('admin.empresas.sucursales.store',[ 'empresa' => $empresa->id, ]) }}"
                method="POST"
                class="space-y-6 rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow"
            >
                @csrf

                <section>
                    <h3 class="text-lg font-bold text-white">
                        Identificación
                    </h3>

                    <div class="mt-4 grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                for="codigo"
                                class="mb-2 block font-semibold text-zinc-200"
                            >
                                Código
                            </label>

                            <input
                                id="codigo"
                                name="codigo"
                                type="text"
                                required
                                maxlength="30"
                                value="{{ old('codigo') }}"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 uppercase text-white placeholder:text-zinc-500 focus:border-purple-500 focus:ring-purple-500"
                                placeholder="Ejemplo: CENTRO"
                            >

                            <p class="mt-2 text-xs text-zinc-400">
                                Solo letras, números y guiones.
                            </p>

                            @error('codigo')
                                <p class="mt-2 text-sm text-red-500">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="nombre"
                                class="mb-2 block font-semibold text-zinc-200"
                            >
                                Nombre de la sucursal
                            </label>

                            <input
                                id="nombre"
                                name="nombre"
                                type="text"
                                required
                                maxlength="150"
                                value="{{ old('nombre') }}"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:border-purple-500 focus:ring-purple-500"
                                placeholder="Ejemplo: Sucursal Centro"
                            >

                            @error('nombre')
                                <p class="mt-2 text-sm text-red-500">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="border-t border-zinc-800 pt-6">
                    <h3 class="text-lg font-bold text-white">
                        Gerencia
                    </h3>

                    <div class="mt-4">
                        <label
                            for="gerente_id"
                            class="mb-2 block font-semibold text-zinc-200"
                        >
                            Gerente responsable
                        </label>

                        <select
                            id="gerente_id"
                            name="gerente_id"
                            required
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white focus:border-purple-500 focus:ring-purple-500"
                        >
                            <option value="">
                                Selecciona un supervisor o administrador
                            </option>

                            @foreach ($gerentes as $gerente)
                                <option
                                    value="{{ $gerente->id }}"
                                    @selected(
                                        (string) old('gerente_id')
                                        === (string) $gerente->id
                                    )
                                >
                                    {{ $gerente->name }}
                                    · {{ $gerente->email }}
                                </option>
                            @endforeach
                        </select>

                        @if ($gerentes->isEmpty())
                            <p class="mt-2 text-sm text-amber-400">
                                No existen supervisores o administradores delegados disponibles.
                            </p>
                        @endif

                        @error('gerente_id')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </section>

                <section class="border-t border-zinc-800 pt-6">
                    <h3 class="text-lg font-bold text-white">
                        Contacto y ubicación
                    </h3>

                    <div class="mt-4 grid gap-5 md:grid-cols-2">
                        <div>
                            <label
                                for="telefono"
                                class="mb-2 block font-semibold text-zinc-200"
                            >
                                Teléfono
                            </label>

                            <input
                                id="telefono"
                                name="telefono"
                                type="text"
                                maxlength="30"
                                value="{{ old('telefono') }}"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white"
                            >
                        </div>

                        <div>
                            <label
                                for="correo"
                                class="mb-2 block font-semibold text-zinc-200"
                            >
                                Correo
                            </label>

                            <input
                                id="correo"
                                name="correo"
                                type="email"
                                maxlength="255"
                                value="{{ old('correo') }}"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white"
                            >
                        </div>

                        <div>
                            <label
                                for="ciudad"
                                class="mb-2 block font-semibold text-zinc-200"
                            >
                                Ciudad
                            </label>

                            <input
                                id="ciudad"
                                name="ciudad"
                                type="text"
                                maxlength="100"
                                value="{{ old('ciudad') }}"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white"
                            >
                        </div>

                        <div>
                            <label
                                for="estado"
                                class="mb-2 block font-semibold text-zinc-200"
                            >
                                Estado
                            </label>

                            <input
                                id="estado"
                                name="estado"
                                type="text"
                                maxlength="100"
                                value="{{ old('estado') }}"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white"
                            >
                        </div>

                        <div>
                            <label
                                for="codigo_postal"
                                class="mb-2 block font-semibold text-zinc-200"
                            >
                                Código postal
                            </label>

                            <input
                                id="codigo_postal"
                                name="codigo_postal"
                                type="text"
                                maxlength="10"
                                value="{{ old('codigo_postal') }}"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white"
                            >
                        </div>

                        <div class="md:col-span-2">
                            <label
                                for="direccion"
                                class="mb-2 block font-semibold text-zinc-200"
                            >
                                Dirección
                            </label>

                            <textarea
                                id="direccion"
                                name="direccion"
                                rows="3"
                                maxlength="1000"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white"
                            >{{ old('direccion') }}</textarea>
                        </div>
                    </div>
                </section>

                @if (! $tieneSucursales)
                    <input
                        type="hidden"
                        name="es_principal"
                        value="1"
                    >

                    <div class="rounded-lg border border-purple-800 bg-purple-950/50 p-4">
                        <p class="font-semibold text-purple-200">
                            Primera sucursal
                        </p>

                        <p class="mt-2 text-sm text-purple-100">
                            Esta sucursal será marcada automáticamente como principal.
                        </p>
                    </div>
                @else
                    <input
                        type="hidden"
                        name="es_principal"
                        value="0"
                    >
                @endif

                <section class="border-t border-zinc-800 pt-6">
                    <label
                        for="motivo"
                        class="mb-2 block font-semibold text-zinc-200"
                    >
                        Motivo de apertura
                    </label>

                    <textarea
                        id="motivo"
                        name="motivo"
                        rows="4"
                        maxlength="1000"
                        required
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:border-purple-500 focus:ring-purple-500"
                        placeholder="Explica por qué se abre esta sucursal."
                    >{{ old('motivo') }}</textarea>

                    <p class="mt-2 text-xs text-zinc-400">
                        El motivo quedará registrado permanentemente en auditoría.
                    </p>

                    @error('motivo')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </section>

                <div class="rounded-lg border border-blue-900 bg-blue-950/40 p-4">
                    <p class="font-semibold text-blue-200">
                        Creación automática
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-blue-100">
                        <li>Se creará el almacén físico principal de la sucursal.</li>
                        <li>Se asignará al gerente seleccionado.</li>
                        <li>Se creará el almacén virtual de tránsito si todavía no existe.</li>
                        <li>La operación completa quedará auditada.</li>
                    </ul>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        class="rounded-lg bg-purple-600 px-6 py-3 font-semibold text-white transition hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @disabled($gerentes->isEmpty())
                        onclick="return confirm('¿Confirmas la apertura de esta sucursal?');"
                    >
                        Abrir sucursal
                    </button>

                     $empresa->id,
                    <a href="{{ route('admin.empresas.sucursales.create', [ 'empresa' => $empresa->id, ]) }}"
                        class="rounded-lg border border-zinc-600 bg-zinc-800 px-6 py-3 font-semibold text-zinc-200 transition hover:bg-zinc-700"
                    >
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
