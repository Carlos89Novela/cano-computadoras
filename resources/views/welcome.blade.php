<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Cano Computadoras</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-[#020b1a] text-white">

    <header class="max-w-7xl mx-auto px-6 py-5 flex justify-end items-center">
        <div class="flex gap-3">
            <a href="/login" class="inline-flex items-center justify-center rounded-full border border-purple-400/60 bg-transparent px-5 py-2 text-sm font-semibold text-purple-200 transition hover:bg-purple-500/10">
                Iniciar Sesión
            </a>

            <a href="/register" class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-violet-600 to-fuchsia-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-violet-500/25 transition hover:brightness-110">
                Registrarse
            </a>
        </div>
    </header>

    <div class="home-bg">
        <section class="max-w-6xl mx-auto text-center py-10 px-6 home-content">
            <div class="hero">
                <div class="hero-content max-w-4xl text-center">
                    <div class="card-hero landing-panel">
                        <span class="eyebrow">Soporte técnico profesional</span>

                        <h2 class="text-5xl font-bold mb-6 text-white">
                            Servicio Profesional de Reparación de Computadoras
                        </h2>

                        <p class="text-xl text-gray-300 mb-6">
                            Reparación, mantenimiento y soporte técnico para laptops,
                            computadoras de escritorio y equipos empresariales.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                            <a href="/register" class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-violet-600 to-fuchsia-500 px-8 py-3 text-base font-bold uppercase tracking-wide text-white shadow-lg shadow-violet-500/25 transition duration-200 hover:brightness-110 hover:-translate-y-0.5">
                                Solicitar Servicio
                            </a>

                            <a href="#precios" class="inline-flex items-center justify-center rounded-full border border-violet-300/80 bg-transparent px-8 py-3 text-base font-bold uppercase tracking-wide text-violet-100 transition duration-200 hover:bg-violet-500/10 hover:-translate-y-0.5">
                                Ver Precios
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- SERVICIOS -->
    <section class="max-w-6xl mx-auto px-6">

        <h2 class="text-4xl font-bold text-center mb-10">
            Nuestros Servicios
        </h2>

        <div class="grid md:grid-cols-3 gap-6">

            <div class="service-card bg-zinc-900/70 p-6 rounded-2xl flex items-start gap-4">
                <div class="service-icon flex-shrink-0 mt-1">
                    <!-- drive + wrench icon -->
                    <svg width="36" height="36" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="7" width="20" height="10" rx="2" fill="#1f0b2e" />
                        <circle cx="7" cy="12" r="2" fill="#7c3aed" />
                        <path d="M16 8l3-3 1 1-3 3" fill="#c084fc" />
                        <path d="M13 11l5 5" stroke="#9f7aea" stroke-width="1.6" stroke-linecap="round" />
                        <path d="M15 9l-2 2" stroke="#c084fc" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-purple-500 mb-3">
                        Formateo
                    </h3>

                    <p>
                        Instalación y configuración de Windows.
                    </p>
                </div>
            </div>

            <div class="service-card bg-zinc-900/70 p-6 rounded-2xl flex items-start gap-4">
                <div class="service-icon flex-shrink-0 mt-1">
                    <!-- shield + bug (removal) icon -->
                    <svg width="36" height="36" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2l7 3v4c0 5-3.6 9.7-7 11-3.4-1.3-7-6-7-11V5l7-3z" fill="#6b21a8" />
                        <path d="M9.5 11.5a2.5 2.5 0 0 0 5 0" stroke="#f3e8ff" stroke-width="1.2" fill="none" stroke-linecap="round" />
                        <rect x="11" y="8.5" width="2" height="3" rx="0.6" fill="#c084fc" />
                        <path d="M8 16l8-8" stroke="#fca5a5" stroke-width="1.6" stroke-linecap="round" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-purple-500 mb-3">
                        Eliminación de Virus
                    </h3>

                    <p>
                        Limpieza de malware y optimización.
                    </p>
                </div>
            </div>

            <div class="service-card bg-zinc-900/70 p-6 rounded-2xl flex items-start gap-4">
                <div class="service-icon flex-shrink-0 mt-1">
                    <!-- ssd + bolt icon -->
                    <svg width="36" height="36" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="6" width="18" height="12" rx="2" fill="#6b21a8" />
                        <rect x="6" y="9" width="12" height="6" fill="#1f0b2e" />
                        <path d="M10 9l2-3v3l2-1-3 5v-3l-1 1z" fill="#facc15" />
                        <circle cx="18" cy="8" r="1" fill="#c084fc" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-purple-500 mb-3">
                        Actualización SSD
                    </h3>

                    <p>
                        Incrementa la velocidad de tu equipo.
                    </p>
                </div>
            </div>

        </div>

    </section>

    <!-- PRECIOS -->
    <section id="precios" class="max-w-4xl mx-auto px-6 py-24">

        <h2 class="text-4xl font-bold text-center mb-10">
            Lista de Precios
        </h2>

        <div class="max-w-4xl mx-auto bg-zinc-900/70 rounded-2xl overflow-hidden border border-violet-500/20 shadow-[0_20px_45px_rgba(124,58,237,0.18)]">

            @forelse ($servicios as $servicio)

                <div class="price-row flex flex-col gap-2 border-b border-zinc-700/80 p-5 last:border-b-0 md:flex-row md:items-center md:justify-between">

                    <div>
                        <h3 class="font-semibold text-white">
                            {{ $servicio->nombre }}
                        </h3>

                        @if ($servicio->descripcion)
                            <p class="mt-1 text-sm text-gray-400">
                                {{ $servicio->descripcion }}
                            </p>
                        @endif
                    </div>

                    <span class="text-xl font-bold text-purple-400">
                        ${{ number_format((float) $servicio->precio, 2) }}
                    </span>

                </div>

            @empty

                <div class="p-8 text-center text-gray-400">
                    Próximamente publicaremos nuestros servicios.
                </div>

            @endforelse

        </div>

    </section>

    <!-- FOOTER -->
    <footer class="text-center py-8 border-t border-zinc-800">

        <h3 class="text-purple-500 text-xl font-bold">
            Cano Computadoras
        </h3>

        <p class="text-gray-400 mt-2">
            © {{ date('Y') }} Todos los derechos reservados.
        </p>

    </footer>

</body>

</html>