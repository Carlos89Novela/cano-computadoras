{{-- ===================================================================== --}}
{{-- VISTA: PANEL DEL ADMINISTRADOR / GERENTE (MULTI-TENANT Y MULTI-GIRO) --}}
{{-- Soporta gerentes/administradores por empresa (con o sin estatus de   --}}
{{-- propietario), mostrando métricas, módulos por giro y acceso directo a  --}}
{{-- la gestión de usuarios y suplencias temporales de supervisor.         --}}
{{-- ===================================================================== --}}
@php
    // =====================================================================
    // 1. RESOLUCIÓN DEL USUARIO, ESTATUS PROPIETARIO Y NIVEL DE ROL
    // =====================================================================
    $usuario = auth()->user();

    // Extraer el rol desde el atributo directo del usuario o desde Spatie Roles
    $rolBruto = strtolower((string) (
        $usuario?->rol
        ?? $usuario?->role
        ?? $usuario?->nivel_rol
        ?? (method_exists($usuario, 'getRoleNames') ? ($usuario->getRoleNames()->first() ?? '') : '')
    ));

    $esPropietarioCuenta = (bool) (
        $usuario
        && method_exists($usuario, 'esPropietario')
        && $usuario->esPropietario()
    );

    $tieneRolAdminGerente = (bool) (
        $usuario
        && (
            (method_exists($usuario, 'hasRole') && $usuario->hasRole(['administrador', 'gerente']))
            || str_contains($rolBruto, 'admin')
            || str_contains($rolBruto, 'gerente')
        )
    );

    // Identificar si el usuario pertenece a Subgerencia o Gerencia/Administración
    $esSubgerente = str_contains($rolBruto, 'subgerente') || str_contains($rolBruto, 'sub_gerente');
    $esGerenteActual = $esGerente ?? (
        $esSubgerente
        || $tieneRolAdminGerente
        || $esPropietarioCuenta
        || true
    );

    // Determinar si se muestra el acceso a Usuarios y Suplencias en el panel
    $puedeVerAccesoUsuarios = (bool) ($usuario && ($esPropietarioCuenta || $tieneRolAdminGerente));

    // Conteo de suplencias temporales de supervisor activas (si existe la tabla y modelo)
    $suplenciasActivasCount = 0;
    if (
        $puedeVerAccesoUsuarios
        && class_exists(\App\Models\SuplenciaRol::class)
        && \Illuminate\Support\Facades\Schema::hasTable('suplencias_rol')
    ) {
        $suplenciasActivasCount = \App\Models\SuplenciaRol::query()->activas()->count();
    }

    // =====================================================================
    // 2. TÍTULO DINÁMICO DE LA PESTAÑA Y ENCABEZADOS SEGÚN ROL
    // =====================================================================
    $tituloDashboard = match (true) {
        $esSubgerente => 'Dashboard Subgerente',
        $esGerenteActual => 'Dashboard Gerencia',
        default => 'Dashboard',
    };

    $encabezadoRol = match (true) {
        $esSubgerente => 'Panel del Subgerente',
        $esPropietarioCuenta => 'Panel del Administrador (Propietario)',
        default => 'Panel del Gerente / Administrador',
    };

    $subtituloArea = $esSubgerente ? 'Subgerencia' : 'Gerencia y Administración';

    // =====================================================================
    // 3. IDENTIFICACIÓN DEL GIRO COMERCIAL DE LA EMPRESA ACTIVA
    //    (Restaurante, Tecnología, Abarrotes y Ferretería)
    // =====================================================================
    $giroActual = strtolower((string) ($giro ?? $empresa?->giro_comercial ?? ''));

    $etiquetaGiro = match ($giroActual) {
        'restaurantes_alimentos', 'restaurante' => 'Giro Restaurante',
        'abarrotes', 'abarrotes_minisuper' => 'Giro Abarrotes',
        'ferreteria', 'ferreteria_tlapaleria' => 'Giro Ferretería',
        default => 'Giro Tecnología',
    };
@endphp

<x-app-layout :title="$tituloDashboard">
    {{-- ===================================================================== --}}
    {{-- ENCABEZADO SUPERIOR: TÍTULO DE ROL Y BADGE DE EMPRESA / GIRO          --}}
    {{-- ===================================================================== --}}
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                    {{ $subtituloArea }}
                </p>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $encabezadoRol }}
                </h2>
            </div>

            {{-- Indicador de empresa activa y giro comercial --}}
            @if (isset($empresa))
                <div class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-800/80 px-3.5 py-1 text-xs text-slate-300 backdrop-blur">
                    <span class="inline-block h-2 w-2 rounded-full {{ $giroActual === 'restaurantes_alimentos' ? 'bg-amber-400' : 'bg-emerald-400' }}"></span>
                    <span class="font-semibold text-white">{{ $empresa->nombre }}</span>
                    <span class="text-slate-500">|</span>
                    <span class="capitalize text-slate-400">
                        {{ $etiquetaGiro }}
                    </span>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto w-full max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">

            {{-- ============================================================= --}}
            {{-- 1. TARJETAS DE ESTADÍSTICAS DEL PANEL DE ADMINISTRACIÓN       --}}
            {{-- ============================================================= --}}
            <section class="grid w-full gap-6 sm:grid-cols-2 xl:grid-cols-4">
                {{-- Tarjeta 1: Órdenes / Comandas --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $giroActual === 'restaurantes_alimentos' ? 'Comandas / Pedidos' : 'Total de reparaciones' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-purple-500">
                        {{ $totalOrdenes }}
                    </p>
                </div>

                {{-- Tarjeta 2: Pendientes / Sin asignar --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $giroActual === 'restaurantes_alimentos' ? 'Comandas por atender' : 'Sin asignar' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-amber-500">
                        {{ $ordenesSinAsignar }}
                    </p>
                </div>

                {{-- Tarjeta 3: Servicios / Platillos del Menú --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $giroActual === 'restaurantes_alimentos' ? 'Platillos en menú' : 'Servicios registrados' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-blue-500">
                        {{ $totalServicios }}
                    </p>
                </div>

                {{-- Tarjeta 4: Personal / Empleados registrados --}}
                <div class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $giroActual === 'restaurantes_alimentos' ? 'Personal de sucursal' : 'Usuarios registrados' }}
                    </p>

                    <p class="mt-3 text-4xl font-bold text-green-500">
                        {{ $totalUsuarios }}
                    </p>
                </div>
            </section>

            {{-- ============================================================= --}}
            {{-- 2. ACCESO DIRECTO A USUARIOS Y SUPLENCIAS DE SUPERVISOR       --}}
            {{-- ============================================================= --}}
            @if ($puedeVerAccesoUsuarios && \Illuminate\Support\Facades\Route::has('admin.usuarios.index'))
                <section class="w-full rounded-2xl border border-slate-800 bg-zinc-900 p-6 shadow space-y-4">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-purple-400">
                                Control de Acceso y Continuidad Operativa
                            </p>
                            <h3 class="text-lg font-bold text-white">
                                Usuarios, Permisos y Suplencias Temporales de Supervisor
                            </h3>
                        </div>

                        @if ($suplenciasActivasCount > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-300">
                                {{ $suplenciasActivasCount }} {{ $suplenciasActivasCount === 1 ? 'suplencia activa' : 'suplencias activas' }}
                            </span>
                        @endif
                    </div>

                    <div class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <a href="{{ route('admin.usuarios.index') }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-purple-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-purple-300">
                                    👥 Usuarios y Suplencias de Supervisor
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Consulta el equipo, administra permisos y gestiona suplencias temporales de supervisor por vacaciones o ausencia.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-purple-400">
                                Gestionar usuarios y suplencias →
                            </span>
                        </a>
                    </div>
                </section>
            @endif

            {{-- ============================================================= --}}
            {{-- 3. MÓDULOS ADMINISTRATIVOS DEL GERENTE / SUBGERENTE (REST.)   --}}
            {{-- ============================================================= --}}
            @if ($giroActual === 'restaurantes_alimentos' && $esGerenteActual && isset($empresa))
                <section class="w-full rounded-2xl border border-slate-800 bg-zinc-900 p-6 shadow space-y-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-amber-400">
                                Gestión Gerencial de Restaurante
                            </p>
                            <h3 class="text-lg font-bold text-white">
                                Recetas, Inventarios, Compras, Proveedores, Caja y KDS
                            </h3>
                        </div>
                        <span class="text-xs text-slate-400">
                            Mesas ocupadas actuales: <strong class="text-emerald-400">{{ $mesasOcupadas ?? 0 }}</strong>
                        </span>
                    </div>

                    {{-- Accesos rápidos a los módulos operativos de restaurante --}}
                    <div class="grid w-full gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {{-- 1. Recetas (Composición y Edición) --}}
                        <a href="{{ route('restaurante.recetas.index') }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-amber-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-amber-300">
                                    📖 Recetas y Composición
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Consulta cómo están compuestas las recetas e ingresa a editar ingredientes y cantidades por platillo.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-amber-400">Administrar recetas →</span>
                        </a>

                        {{-- 2. Inventarios y Entrada de Insumos --}}
                        <a href="{{ route('inventario.existencias.index', ['empresa' => $empresa->id]) }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-emerald-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-emerald-300">
                                    📦 Inventarios de Insumos
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Revisa existencias actuales, almacenes y movimientos de insumos del restaurante.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-emerald-400">Ver existencias →</span>
                        </a>

                        {{-- 3. Registrar Entrada de Insumos --}}
                        <a href="{{ route('inventario.entradas.create', ['empresa' => $empresa->id]) }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-cyan-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-cyan-300">
                                    📥 Entrada a Inventario
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Da entrada directa a insumos o mercancía al inventario de la sucursal.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-cyan-400">Registrar entrada →</span>
                        </a>

                        {{-- 4. Órdenes de Compra --}}
                        <a href="{{ route('compras.ordenes.index') }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-purple-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-purple-300">
                                    🧾 Órdenes de Compra
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Revisa, crea y autoriza órdenes de compra a proveedores y recepciones.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-purple-400">Ver órdenes de compra →</span>
                        </a>

                        {{-- 5. Catálogo de Proveedores de la Empresa Activa --}}
                        @if (\Illuminate\Support\Facades\Route::has('operacion.proveedores.index'))
                            <div class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-teal-500/50">
                                <div>
                                    <div class="text-sm font-bold text-white group-hover:text-teal-300">
                                        🏢 Proveedores
                                    </div>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Consulta, registra y edita proveedores para órdenes de compra de {{ $empresa->nombre }}.
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 text-xs font-semibold">
                                    <a href="{{ route('operacion.proveedores.index', ['empresa' => $empresa->id]) }}"
                                       class="text-teal-400 hover:text-teal-300 transition">
                                        Ver proveedores →
                                    </a>
                                    <a href="{{ route('operacion.proveedores.create', ['empresa' => $empresa->id]) }}"
                                       class="text-emerald-400 hover:text-emerald-300 transition">
                                        + Nuevo
                                    </a>
                                </div>
                            </div>
                        @endif

                        {{-- 6. Cortes y Auditoría de Caja --}}
                        <a href="{{ route('pos.cajas.index') }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-indigo-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-indigo-300">
                                    💰 Cortes y Auditoría de Caja
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Consulta turnos, retiros, ingresos y cortes de caja de cajeros y meseros.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-indigo-400">Auditar cajas →</span>
                        </a>

                        {{-- 7. Monitor KDS --}}
                        <a href="{{ route('restaurante.kds.index') }}"
                           class="group flex flex-col justify-between gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-rose-500/50">
                            <div>
                                <div class="text-sm font-bold text-white group-hover:text-rose-300">
                                    👨‍🍳 Monitor KDS de Cocina
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Supervisa comandas en preparación y despachos por estación en tiempo real.
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-rose-400">Abrir KDS →</span>
                        </a>
                    </div>
                </section>
            @endif

        </div>
    </div>
</x-app-layout>