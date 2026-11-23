{{-- ===================================================================== --}}
{{-- COMPONENTE: BARRA DE NAVEGACIÓN PRINCIPAL (ERP MULTI-TENANT)        --}}
{{-- Resuelve rutas, permisos, giro comercial y empresa activa de forma   --}}
{{-- dinámica para escritorio y vista móvil.                              --}}
{{-- ===================================================================== --}}
<nav x-data="{ openMobile: false }" class="bg-slate-900 border-b border-slate-800 text-slate-200">
    @php
        /** @var \App\Models\User|null $usuario */
        $usuario = auth()->user();

        // Conteo de notificaciones sin leer del usuario autenticado
        $cantidadNotificacionesNoLeidas = $usuario ? $usuario->unreadNotifications()->count() : 0;

        // Determinar la ruta principal del dashboard según el rol del usuario
        $rutaDashboard = match (true) {
            $usuario?->hasRole('administrador') => route('admin.dashboard'),
            $usuario?->hasRole('supervisor') => route('supervisor.dashboard'),
            $usuario?->hasRole('empleado') => route('empleado.dashboard'),
            default => route('dashboard'),
        };

        // Determina si la empresa activa tiene un giro de servicios / tecnología / taller
        // (ej. "computadoras_tecnologia"), ocultándolo en "restaurantes_alimentos"
        $giroComercialActivo = strtolower((string) ($empresaActual?->giro_comercial ?? ''));
        $esGiroServiciosTecnologia = (bool) preg_match(
            '/computador|tecnolog|taller|reparac|servicio|celular|electron/',
            $giroComercialActivo
        );

        // -----------------------------------------------------------------
        // RESOLUCIÓN MULTI-TENANT DE EMPRESA ACTIVA:
        // 1) Parámetro {empresa} en la ruta actual (y persistencia en sesión)
        // 2) Empresa guardada en sesión activa (empresa_activa_id / empresa_id)
        // 3) Empresa de la sucursal activa asignada en sucursal_usuario
        // 4) Respaldo final de primera empresa activa registrada
        // -----------------------------------------------------------------
        $empresaRuta = request()->route('empresa');
        /** @var \App\Models\Empresa|null $empresaActual */
        $empresaActual = null;

        if ($empresaRuta instanceof \App\Models\Empresa) {
            $empresaActual = $empresaRuta;
            session(['empresa_activa_id' => $empresaActual->id]);
        } elseif (is_numeric($empresaRuta)) {
            $empresaActual = \App\Models\Empresa::query()->find((int) $empresaRuta);
            if ($empresaActual) {
                session(['empresa_activa_id' => $empresaActual->id]);
            }
        }

        if (! $empresaActual) {
            $sessionEmpresaId = session('empresa_activa_id') ?? session('empresa_id');
            if (! empty($sessionEmpresaId)) {
                $empresaActual = \App\Models\Empresa::query()->find((int) $sessionEmpresaId);
            }
        }

        if (! $empresaActual && $usuario && method_exists($usuario, 'sucursales')) {
            $sucursalActiva = $usuario->sucursales()
                ->wherePivot('activo', true)
                ->orderByDesc('sucursal_usuario.es_principal')
                ->with('empresa')
                ->first();

            if ($sucursalActiva?->empresa instanceof \App\Models\Empresa) {
                $empresaActual = $sucursalActiva->empresa;
                session(['empresa_activa_id' => $empresaActual->id]);
            }
        }

        if (! $empresaActual) {
            $empresaActual = \App\Models\Empresa::query()->where('activo', true)->first();
        }

        // Identificación de roles operativos y giro comercial
        // (Compatible con cómputo/celulares, restaurante, abarrotes y ferretería)
        $esAdminOSupervisor = (bool) ($usuario?->hasRole('administrador') || $usuario?->hasRole('supervisor'));
        $esEmpleado = (bool) $usuario?->hasRole('empleado');
        $esCliente = (bool) $usuario?->hasRole('cliente');
        $esPropietario = (bool) ($usuario && method_exists($usuario, 'esPropietario') && $usuario->esPropietario());

        $puedeVerInventario = (bool) (
            $empresaActual
            && $usuario
            && ($usuario->can('inventario.transferir') || $usuario->can('inventario.registrar_entrada'))
        );

        $puedeRegistrarEntrada = (bool) ($usuario && $usuario->can('inventario.registrar_entrada'));
        $puedeTransferirInventario = (bool) ($usuario && $usuario->can('inventario.transferir'));
        $puedeVerProductos = (bool) ($usuario && $usuario->can('productos.ver'));
    @endphp

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            {{-- ============================================================= --}}
            {{-- SECCIÓN IZQUIERDA: LOGO Y MENÚ PRINCIPAL EN ESCRITORIO         --}}
            {{-- ============================================================= --}}
            <div class="flex items-center gap-6">
                <!-- Logo del sistema -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ $rutaDashboard }}" title="{{ $empresaActual?->nombre ?? config('app.name') }}">
                        <x-application-logo class="block h-8 w-auto fill-current text-white" />
                    </a>
                </div>

                <!-- Enlaces principales (visible en md+) -->
                <div class="hidden md:flex items-center space-x-2">
                    <!-- 1. Enlace directo al Dashboard correspondiente al rol -->
                    <x-nav-link :href="$rutaDashboard" :active="request()->routeIs('*dashboard*')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    <!-- 2. Acceso rápido a POS Mostrador -->
                    @if ($esAdminOSupervisor || $esEmpleado)
                        <a href="{{ route('pos.index') }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('pos.index') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'bg-indigo-950/60 text-indigo-300 hover:bg-indigo-900/60 border border-indigo-500/30' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            POS Mostrador
                        </a>
                    @endif

                    <!-- 3. Desplegable: Operación y Ventas -->
                    @if ($esAdminOSupervisor || $esEmpleado)
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                            <button type="button"
                                    @click="open = !open"
                                    class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold rounded-lg transition {{ request()->routeIs('admin.ordenes.*', 'supervisor.*', 'empleado.*', 'compras.ordenes.*', 'pos.cajas.*') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }}">
                                <span>Operación y Ventas</span>
                                <svg class="w-3.5 h-3.5 transition-transform" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div x-show="open"
                                 style="display: none;"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute left-0 mt-2 w-56 rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl py-1.5 z-50">

                                {{-- Opciones de taller (cómputo / equipos electrónicos) según rol --}}
                                @if ($empresaActual?->esTallerComputo())
                                    @if ($usuario?->hasRole('administrador'))
                                        <a href="{{ route('admin.ordenes.index') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                            🛠️ Reparaciones (Taller)
                                        </a>
                                    @elseif ($usuario?->hasRole('supervisor'))
                                        <a href="{{ route('supervisor.dashboard') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                            🛠️ Supervisión de Taller
                                        </a>
                                    @elseif ($esEmpleado)
                                        <a href="{{ route('empleado.dashboard') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                            🛠️ Reparaciones Asignadas
                                        </a>
                                    @endif
                                @endif

                                {{-- Compras y arqueos de caja disponibles para administración y supervisión --}}
                                @if ($esAdminOSupervisor)
                                    <a href="{{ route('compras.ordenes.index') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                        📄 Órdenes de Compra
                                    </a>
                                    <a href="{{ route('pos.cajas.index') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                        💰 Cortes de Caja y Arqueos
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- 4. Desplegable: Inventario y Almacén (vinculado a $empresaActual) -->
                    @if ($puedeVerInventario)
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                            <button type="button"
                                    @click="open = !open"
                                    class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold rounded-lg transition {{ request()->routeIs('inventario.*') ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }}">
                                <span>Inventario</span>
                                <svg class="w-3.5 h-3.5 transition-transform" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div x-show="open"
                                 style="display: none;"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute left-0 mt-2 w-56 rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl py-1.5 z-50">

                                {{-- Usa $empresaActual para mantener el contexto de la empresa activa --}}
                                <a href="{{ route('inventario.existencias.index', $empresaActual) }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                    📊 Existencias y Kardex
                                </a>

                                @if ($puedeRegistrarEntrada)
                                    <a href="{{ route('inventario.recepciones.create', $empresaActual) }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                        📥 Recepción de Mercancía
                                    </a>
                                @endif

                                @if ($puedeTransferirInventario)
                                    <a href="{{ route('inventario.transferencias.index', $empresaActual) }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                        🔄 Transferencias entre Almacenes
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Enlaces exclusivos para portal de Clientes -->
                    @if ($esCliente)
                        <x-nav-link :href="route('equipos.index')" :active="request()->routeIs('equipos.*')">
                            {{ __('Mis equipos') }}
                        </x-nav-link>
                        <x-nav-link :href="route('ordenes.index')" :active="request()->routeIs('ordenes.*')">
                            {{ __('Mis reparaciones') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            {{-- ============================================================= --}}
            {{-- SECCIÓN DERECHA: NOTIFICACIONES Y MENÚ DE USUARIO              --}}
            {{-- ============================================================= --}}
            <div class="hidden md:flex items-center space-x-3">
                <!-- Campana de notificaciones -->
                <a href="{{ route('notificaciones.index') }}"
                   title="Notificaciones"
                   class="relative p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    @if ($cantidadNotificacionesNoLeidas > 0)
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-500 ring-2 ring-slate-900"></span>
                    @endif
                </a>

                <!-- Desplegable de perfil y administración -->
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                    <button type="button"
                            @click="open = !open"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-800 bg-slate-950/60 hover:bg-slate-800 text-xs font-semibold text-slate-300 transition">
                        <span>{{ $usuario?->name ?? 'Usuario' }}</span>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open"
                         style="display: none;"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl py-2 z-50">

                        @if ($usuario?->hasRole('administrador'))
                            <div class="px-4 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-800/80">
                                Configuración Global
                            </div>
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-1.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                Panel Administrativo
                            </a>
                            @if ($esGiroServiciosTecnologia && \Illuminate\Support\Facades\Route::has('admin.servicios.index'))
                                <a href="{{ route('admin.servicios.index') }}" ...>
                                    Catálogo de Servicios
                                </a>
                            @endif

                            @if ($esPropietario)
                                <a href="{{ route('admin.usuarios.index') }}" class="block px-4 py-1.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                    Usuarios y Permisos
                                </a>
                                @if ($empresaActual)
                                    <a href="{{ route('admin.empresas.sucursales.index', $empresaActual) }}" class="block px-4 py-1.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                        Sucursales y Almacenes
                                    </a>
                                    @if ($puedeVerProductos)
                                        <a href="{{ route('productos.categorias.index', $empresaActual) }}" class="block px-4 py-1.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                            Categorías de Productos
                                        </a>
                                        <a href="{{ route('proveedores.index', $empresaActual) }}" class="block px-4 py-1.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                                            Proveedores
                                        </a>
                                    @endif
                                @endif
                            @endif
                            <div class="my-1 border-t border-slate-800/80"></div>
                        @endif

                        <div class="px-4 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Cuenta
                        </div>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-1.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-white">
                            Mi Perfil
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-1.5 text-xs text-rose-400 hover:bg-rose-500/10 transition">
                                Cerrar Sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Botón hamburguesa para dispositivos móviles -->
            <div class="-me-2 flex items-center md:hidden">
                <button type="button"
                        @click="openMobile = !openMobile"
                        class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': openMobile, 'inline-flex': !openMobile }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !openMobile, 'inline-flex': openMobile }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- MENÚ RESPONSIVE MÓVIL                                                --}}
    {{-- ===================================================================== --}}
    <div :class="{'block': openMobile, 'hidden': !openMobile}" class="hidden md:hidden border-t border-slate-800 bg-slate-950 px-4 py-3 space-y-2 text-xs">
        <a href="{{ $rutaDashboard }}" class="block py-2 text-slate-300 font-semibold">Dashboard</a>

        @if ($esAdminOSupervisor || $esEmpleado)
            <a href="{{ route('pos.index') }}" class="block py-2 text-indigo-400 font-bold">POS Mostrador</a>

            <div class="pt-2 border-t border-slate-900 text-[10px] font-bold uppercase text-slate-500">Operación</div>
            @if ($esAdminOSupervisor)
                <a href="{{ route('compras.ordenes.index') }}" class="block py-1.5 text-slate-300">Órdenes de Compra</a>
                <a href="{{ route('pos.cajas.index') }}" class="block py-1.5 text-slate-300">Cortes de Caja</a>
            @endif
        @endif

        @if ($puedeVerInventario)
            <div class="pt-2 border-t border-slate-900 text-[10px] font-bold uppercase text-slate-500">Inventario</div>
            <a href="{{ route('inventario.existencias.index', $empresaActual) }}" class="block py-1.5 text-slate-300">Kardex y Stock</a>
            @if ($puedeRegistrarEntrada)
                <a href="{{ route('inventario.recepciones.create', $empresaActual) }}" class="block py-1.5 text-slate-300">Recepción de Mercancía</a>
            @endif
            @if ($puedeTransferirInventario)
                <a href="{{ route('inventario.transferencias.index', $empresaActual) }}" class="block py-1.5 text-slate-300">Transferencias</a>
            @endif
        @endif

        @if ($esCliente)
            <div class="pt-2 border-t border-slate-900 text-[10px] font-bold uppercase text-slate-500">Cliente</div>
            <a href="{{ route('equipos.index') }}" class="block py-1.5 text-slate-300">Mis equipos</a>
            <a href="{{ route('ordenes.index') }}" class="block py-1.5 text-slate-300">Mis reparaciones</a>
        @endif

        <div class="pt-2 border-t border-slate-900 text-[10px] font-bold uppercase text-slate-500">Usuario</div>
        <a href="{{ route('profile.edit') }}" class="block py-1.5 text-slate-300">Mi Perfil</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="block w-full text-left py-1.5 text-rose-400 font-semibold">Cerrar Sesión</button>
        </form>
    </div>
</nav>
