<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-500">
                Supervisión operativa
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Panel del supervisor
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-6">
            @if (session('success'))
                <div class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-700 bg-red-950 p-4 text-red-200">
                    <p class="font-semibold">
                        No fue posible completar la asignación.
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Total de reparaciones
                    </p>

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $totalOrdenes }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Reparaciones activas
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $ordenesActivas }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Sin asignar
                    </p>

                    <p class="mt-3 text-4xl font-bold text-amber-500">
                        {{ $ordenesSinAsignar }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Cierres pendientes
                    </p>

                    <p class="mt-3 text-4xl font-bold text-red-500">
                        {{ $cierresPendientes }}
                    </p>
                </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            Carga de empleados
                        </h3>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Reparaciones activas asignadas a cada integrante del área técnica.
                        </p>
                    </div>

                    <span class="rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-200">
                        {{ $empleados->count() }} empleados
                    </span>
                </div>

                @if ($empleados->isEmpty())
                    <div class="mt-6 rounded-lg border border-amber-700 bg-amber-950 p-5 text-amber-200">
                        No hay empleados registrados. Asigna el rol empleado a una cuenta antes de distribuir reparaciones.
                    </div>
                @else
                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($empleados as $empleado)
                            <div class="rounded-lg border border-gray-200 p-5 dark:border-zinc-700">
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    {{ $empleado->name }}
                                </p>

                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    Carga activa:
                                    <span class="font-bold text-blue-500">
                                        {{ $empleado->carga_activa }}
                                    </span>
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            Reparaciones sin asignar
                        </h3>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Se muestran primero las reparaciones con mayor tiempo de espera.
                        </p>
                    </div>

                    <span class="rounded-full bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-200">
                        {{ $ordenesPendientes->count() }} mostradas
                    </span>
                </div>

                @if ($ordenesPendientes->isEmpty())
                    <div class="mt-6 rounded-lg bg-gray-100 p-6 text-center text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                        No hay reparaciones pendientes de asignación.
                    </div>
                @else
                    <div class="mt-6 space-y-5">
                        @foreach ($ordenesPendientes as $orden)
                            <article class="rounded-xl border border-gray-200 p-5 dark:border-zinc-700">
                                <div class="grid gap-5 lg:grid-cols-2">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <p class="text-lg font-bold text-purple-500">
                                                {{ $orden->folio }}
                                            </p>

                                            <span class="rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">
                                                {{ $orden->estado }}
                                            </span>
                                        </div>

                                        <dl class="mt-4 space-y-2 text-sm">
                                            <div>
                                                <dt class="inline font-semibold text-gray-700 dark:text-gray-300">
                                                    Cliente:
                                                </dt>

                                                <dd class="inline text-gray-600 dark:text-gray-400">
                                                    {{ $orden->user->name }}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt class="inline font-semibold text-gray-700 dark:text-gray-300">
                                                    Equipo:
                                                </dt>

                                                <dd class="inline text-gray-600 dark:text-gray-400">
                                                    {{ $orden->equipo->marca }}
                                                    {{ $orden->equipo->modelo }}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt class="inline font-semibold text-gray-700 dark:text-gray-300">
                                                    Servicio:
                                                </dt>

                                                <dd class="inline text-gray-600 dark:text-gray-400">
                                                    {{ $orden->servicio?->nombre ?? 'Diagnóstico general' }}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt class="inline font-semibold text-gray-700 dark:text-gray-300">
                                                    Fecha de ingreso:
                                                </dt>

                                                <dd class="inline text-gray-600 dark:text-gray-400">
                                                    {{ $orden->fecha_ingreso->format('d/m/Y') }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <form
                                        action="{{ route('operacion.ordenes.asignar', ['orden' => $orden->id]) }}"
                                        method="POST"
                                    >
                                        @csrf

                                        <div>
                                            <label
                                                for="empleado-{{ $orden->id }}"
                                                class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                                            >
                                                Empleado responsable
                                            </label>

                                            <select
                                                id="empleado-{{ $orden->id }}"
                                                name="empleado_id"
                                                required
                                                class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                                @disabled($empleados->isEmpty())
                                            >
                                                <option value="">
                                                    Selecciona un empleado
                                                </option>

                                                @foreach ($empleados as $empleado)
                                                    <option value="{{ $empleado->id }}">
                                                        {{ $empleado->name }}
                                                        · Carga activa: {{ $empleado->carga_activa }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                for="observaciones-{{ $orden->id }}"
                                                class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                                            >
                                                Observaciones
                                            </label>

                                            <textarea
                                                id="observaciones-{{ $orden->id }}"
                                                name="observaciones"
                                                rows="3"
                                                maxlength="2000"
                                                class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                                placeholder="Indicaciones internas para el empleado."
                                            ></textarea>
                                        </div>

                                        <button
                                            type="submit"
                                            class="rounded-lg bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            @disabled($empleados->isEmpty())
                                        >
                                            Asignar reparación
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            Reparaciones asignadas
                        </h3>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            El supervisor puede cambiar al empleado responsable cuando sea necesario.
                        </p>
                    </div>

                    <span class="rounded-full bg-green-100 px-4 py-2 text-sm font-semibold text-green-700 dark:bg-green-950 dark:text-green-200">
                        {{ $ordenesAsignadas->count() }} mostradas
                    </span>
                </div>

                @if ($ordenesAsignadas->isEmpty())
                    <div class="mt-6 rounded-lg bg-gray-100 p-6 text-center text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                        Todavía no existen reparaciones asignadas.
                    </div>
                @else
                    <div class="mt-6 space-y-5">
                        @foreach ($ordenesAsignadas as $orden)
                            <article class="rounded-xl border border-gray-200 p-5 dark:border-zinc-700">
                                <div class="grid gap-5 lg:grid-cols-2">
                                    <div>
                                        <p class="text-lg font-bold text-purple-500">
                                            {{ $orden->folio }}
                                        </p>

                                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                            Empleado actual:
                                            <span class="font-bold text-green-500">
                                                {{ $orden->asignacionActiva?->empleado?->name ?? 'No disponible' }}
                                            </span>
                                        </p>

                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            Equipo:
                                            {{ $orden->equipo->marca }}
                                            {{ $orden->equipo->modelo }}
                                        </p>

                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            Estado:
                                            {{ $orden->estado }}
                                        </p>
                                    </div>

                                    <form
                                        method="POST"
                                        action="{{ route('operacion.ordenes.asignar', ['orden' => $orden->id]) }}"
                                        class="space-y-4"
                                    >
                                        @csrf

                                        <div>
                                            <label
                                                for="reasignar-empleado-{{ $orden->id }}"
                                                class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                                            >
                                                Reasignar a
                                            </label>

                                            <select
                                                id="reasignar-empleado-{{ $orden->id }}"
                                                name="empleado_id"
                                                required
                                                class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                            >
                                                <option value="">
                                                    Selecciona un empleado
                                                </option>

                                                @foreach ($empleados as $empleado)
                                                    <option
                                                        value="{{ $empleado->id }}"
                                                        @selected(
                                                            $orden->asignacionActiva?->empleado_id
                                                                === $empleado->id
                                                        )
                                                    >
                                                        {{ $empleado->name }}
                                                        · Carga activa: {{ $empleado->carga_activa }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                for="reasignar-observaciones-{{ $orden->id }}"
                                                class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200"
                                            >
                                                Motivo u observaciones
                                            </label>

                                            <textarea
                                                id="reasignar-observaciones-{{ $orden->id }}"
                                                name="observaciones"
                                                rows="3"
                                                maxlength="2000"
                                                class="w-full rounded-lg border-gray-300 bg-white text-gray-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                                placeholder="Motivo de la reasignación."
                                            ></textarea>
                                        </div>

                                        <button
                                            type="submit"
                                            class="rounded-lg bg-amber-600 px-5 py-3 font-semibold text-white hover:bg-amber-700"
                                        >
                                            Guardar reasignación
                                        </button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>