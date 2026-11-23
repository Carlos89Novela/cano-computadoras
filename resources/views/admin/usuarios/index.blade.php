{{-- ===================================================================== --}}
{{-- VISTA: ADMINISTRACIÓN DE USUARIOS, RESPONSABILIDADES Y SUPLENCIAS     --}}
{{-- Permite gestionar roles, permisos directos y activar/restaurar        --}}
{{-- suplencias temporales de supervisor por vacaciones o emergencias.     --}}
{{-- ===================================================================== --}}
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
            {{-- ============================================================= --}}
            {{-- ALERTAS DE ÉXITO O ERRORES DE VALIDACIÓN                      --}}
            {{-- ============================================================= --}}
            @if (session('success'))
                <div
                    class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200"
                    role="status"
                >
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-700 bg-red-950 p-4 text-red-200">
                    <p class="font-semibold">No fue posible completar la acción:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ============================================================= --}}
            {{-- SECCIÓN: SUPLENCIA TEMPORAL DE SUPERVISOR (VACACIONES/AUSENCIA)--}}
            {{-- ============================================================= --}}
            @if (auth()->user()?->esPropietario() && auth()->user()?->hasRole('administrador'))
                <section class="rounded-xl border border-amber-700/60 bg-white p-6 shadow dark:bg-zinc-900 space-y-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-amber-400">
                            Continuidad Operativa y Guardias
                        </p>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            Suplencia Temporal de Supervisor
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-zinc-400">
                            Promueve temporalmente a un colaborador al rol <strong>Supervisor</strong> cuando el titular falte por vacaciones, incapacidad o emergencia. Su rol original queda respaldado para restaurarlo al regreso.
                        </p>
                    </div>

                    {{-- Formulario para otorgar rol temporal de supervisor --}}
                    <form
                        method="POST"
                        action="{{ route('admin.usuarios.suplencias.store') }}"
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5 items-end"
                    >
                        @csrf

                        <div>
                            <label for="supervisor_ausente_id" class="mb-1.5 block text-xs font-semibold uppercase text-gray-700 dark:text-zinc-300">
                                Supervisor ausente (opcional)
                            </label>
                            <select
                                id="supervisor_ausente_id"
                                name="supervisor_ausente_id"
                                class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            >
                                <option value="">Seleccionar titular...</option>
                                @foreach ($supervisores ?? [] as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="suplente_id" class="mb-1.5 block text-xs font-semibold uppercase text-gray-700 dark:text-zinc-300">
                                Colaborador suplente *
                            </label>
                            <select
                                id="suplente_id"
                                name="suplente_id"
                                required
                                class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            >
                                <option value="">Seleccionar colaborador...</option>
                                @foreach ($candidatosSuplencia ?? [] as $cand)
                                    <option value="{{ $cand->id }}">
                                        {{ $cand->name }} ({{ ucfirst($cand->roles->first()?->name ?? 'sin rol') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="motivo_suplencia" class="mb-1.5 block text-xs font-semibold uppercase text-gray-700 dark:text-zinc-300">
                                Motivo de ausencia *
                            </label>
                            <input
                                id="motivo_suplencia"
                                name="motivo"
                                type="text"
                                required
                                maxlength="500"
                                placeholder="Ej. Vacaciones / Incapacidad / Emergencia"
                                class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            >
                        </div>

                        <div>
                            <label for="fecha_fin_estimada" class="mb-1.5 block text-xs font-semibold uppercase text-gray-700 dark:text-zinc-300">
                                Regreso estimado
                            </label>
                            <input
                                id="fecha_fin_estimada"
                                name="fecha_fin_estimada"
                                type="date"
                                class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            >
                        </div>

                        <div>
                            <button
                                type="submit"
                                class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-700"
                            >
                                Asignar Supervisor Temporal
                            </button>
                        </div>
                    </form>

                    {{-- Tabla de suplencias activas con botón de restauración inmediata --}}
                    @if (($suplenciasActivas ?? collect())->isNotEmpty())
                        <div class="overflow-x-auto rounded-lg border border-zinc-800">
                            <table class="min-w-full divide-y divide-zinc-800 text-sm">
                                <thead class="bg-zinc-800/80 text-xs uppercase text-zinc-300">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Suplente activo</th>
                                        <th class="px-4 py-3 text-left">Rol respaldado</th>
                                        <th class="px-4 py-3 text-left">Cubre a</th>
                                        <th class="px-4 py-3 text-left">Motivo / Vigencia</th>
                                        <th class="px-4 py-3 text-right">Restaurar al regresar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800 bg-zinc-950/60 text-zinc-200">
                                    @foreach ($suplenciasActivas as $suplencia)
                                        <tr>
                                            <td class="px-4 py-3 font-semibold text-white">
                                                {{ $suplencia->suplente?->name ?? 'N/D' }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="rounded-full bg-zinc-800 px-2.5 py-0.5 text-xs font-semibold text-amber-300">
                                                    {{ ucfirst($suplencia->rol_anterior) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-zinc-300">
                                                {{ $suplencia->supervisorAusente?->name ?? 'No especificado' }}
                                            </td>
                                            <td class="px-4 py-3 text-xs text-zinc-400">
                                                <span class="text-zinc-200">{{ $suplencia->motivo }}</span>
                                                <br>
                                                Inicio: {{ $suplencia->fecha_inicio?->format('d/m/Y') }}
                                                @if ($suplencia->fecha_fin_estimada)
                                                    · Regreso est.: {{ $suplencia->fecha_fin_estimada->format('d/m/Y') }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.usuarios.suplencias.restaurar', $suplencia) }}"
                                                    onsubmit="return confirm('¿Confirmas que regresó el supervisor y deseas devolver a {{ $suplencia->suplente?->name }} a su rol anterior ({{ $suplencia->rol_anterior }})?');"
                                                >
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                                    >
                                                        Devolver a {{ ucfirst($suplencia->rol_anterior) }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            {{-- ============================================================= --}}
            {{-- FILTRO DE BÚSQUEDA Y ROL BASE                                 --}}
            {{-- ============================================================= --}}
            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <form
                    method="GET"
                    action="{{ route('admin.usuarios.index') }}"
                    class="grid grid-cols-1 gap-4 md:grid-cols-3"
                >
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
                                    @selected($rol === $rolDisponible->name)
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

            {{-- ============================================================= --}}
            {{-- TABLA GENERAL DE USUARIOS Y RESPONSABILIDADES                 --}}
            {{-- ============================================================= --}}
            <section class="overflow-hidden rounded-xl bg-white shadow dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-700">
                        <thead class="bg-zinc-800">
                            <tr>
                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300">
                                    Usuario
                                </th>

                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300">
                                    Rol base
                                </th>

                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300">
                                    Permisos directos
                                </th>

                                <th class="px-5 py-4 text-left text-xs font-semibold uppercase text-zinc-300">
                                    Verificación
                                </th>

                                <th class="px-5 py-4 text-right text-xs font-semibold uppercase text-zinc-300">
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
                                                href="{{ route('admin.usuarios.edit', ['usuario' => $usuario->id]) }}"
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