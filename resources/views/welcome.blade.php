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

    <section class="brand-shell home-bg px-4 pt-4 pb-20">
        <div class="brand-orbit"></div>

        <div class="relative z-10 flex flex-col items-center justify-center text-center">
            <div class="brand-laptop" aria-label="Logo de Cano Computadoras">
                <svg viewBox="0 0 1000 600" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="title desc">
                    <title id="title">Cano Computadoras</title>
                    <desc id="desc">Laptop con herramientas tecnológicas y logotipo de Cano Computadoras.</desc>
                    <defs>
                        <linearGradient id="screenGlow" x1="0" x2="1">
                            <stop offset="0%" stop-color="#a855f7" stop-opacity="0.5"/>
                            <stop offset="50%" stop-color="#d946ef" stop-opacity="0.35"/>
                            <stop offset="100%" stop-color="#7c3aed" stop-opacity="0.5"/>
                        </linearGradient>
                        <linearGradient id="screenDark" x1="0" x2="1">
                            <stop offset="0%" stop-color="#0b1123"/>
                            <stop offset="100%" stop-color="#111827"/>
                        </linearGradient>
                        <linearGradient id="metal" x1="0" x2="1">
                            <stop offset="0%" stop-color="#f8fafc"/>
                            <stop offset="45%" stop-color="#a1a1aa"/>
                            <stop offset="100%" stop-color="#e5e7eb"/>
                        </linearGradient>
                    </defs>

                    <g>
                        <path d="M250 175h500c34 0 60 26 60 60v220H190V235c0-34 26-60 60-60z" fill="url(#screenDark)" stroke="#9d4edd" stroke-width="8"/>
                        <path d="M210 190h580v220c0 32-26 58-58 58H268c-32 0-58-26-58-58V190z" fill="url(#screenGlow)" opacity="0.12"/>

                        <g transform="translate(0 0)">
                            <path d="M410 315l88-74c8-7 20-7 28 0l31 27c6 6 6 17 0 23l-91 84a19 19 0 0 1-28 0l-28-27c-7-6-7-17 0-23z" fill="#9d4edd" opacity="0.92"/>
                            <path d="M500 210h24c20 0 36 16 36 35v26c0 19-16 35-36 35h-24c-19 0-35-16-35-35v-26c0-19 16-35 35-35z" fill="#d8b4fe" opacity="0.8"/>
                            <path d="M315 345l118-109c26-24 64-25 92-3l79 74c11 10 10 28-2 38l-118 110c-26 24-64 25-92 3l-79-74a27 27 0 0 1 2-39z" fill="none" stroke="#d8b4fe" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" opacity="0.7"/>
                            <circle cx="505" cy="308" r="40" fill="#7c3aed" opacity="0.9"/>
                        </g>

                        <g transform="translate(0 16)">
                            <g transform="translate(350 286)">
                                <circle cx="0" cy="0" r="55" fill="none" stroke="#d8b4fe" stroke-width="10" stroke-dasharray="8 18"/>
                                <circle cx="0" cy="0" r="28" fill="#d8b4fe" opacity="0.18"/>
                                <path d="M-54 0h24M54 0h-24M0 -54v24M0 54v-24" stroke="#d8b4fe" stroke-width="10" stroke-linecap="round"/>
                            </g>
                            <g transform="translate(590 270)">
                                <path d="M-35 10L35 10L35 50L-35 50Z" fill="#d8b4fe" opacity="0.7"/>
                                <path d="M-35 10L-55 -25L-20 -25L0 10Z" fill="#9d4edd"/>
                                <path d="M35 10L55 -25L20 -25L0 10Z" fill="#c084fc"/>
                                <path d="M-20 50L-45 86L0 86L20 50Z" fill="#7c3aed"/>
                                <path d="M-34 -25C-20 -43 20 -43 34 -25" stroke="#f3e8ff" stroke-width="8" fill="none" stroke-linecap="round"/>
                            </g>
                        </g>
                    </g>

                    <g>
                        <path d="M160 435h680a28 28 0 0 1 28 28v16H132v-16a28 28 0 0 1 28-28z" fill="url(#metal)" stroke="#d4d4d8" stroke-width="4"/>
                        <path d="M210 430h580c16 0 28 12 28 28v18H182v-18c0-16 12-28 28-28z" fill="#111827" stroke="#6b7280" stroke-width="4"/>
                        <path d="M228 443h544" stroke="#4b5563" stroke-width="8" stroke-linecap="round"/>
                        <path d="M132 470h736" stroke="#d4d4d8" stroke-width="8" opacity="0.8"/>
                    </g>
                </svg>
            </div>

            <div class="brand-logo">
                <div class="brand-logo-word main">CANO</div>
                <div class="brand-logo-word secondary">COMPUTADORAS</div>
            </div>

            <div class="brand-divider"></div>

            <div class="brand-tagline">
                <span>Reparación</span>
                <span class="dot">•</span>
                <span>Mantenimiento</span>
                <span class="dot">•</span>
                <span>Soporte</span>
            </div>

            <div class="brand-badge" aria-hidden="true">
                <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                    <path d="M32 8l18 9v13c0 12.5-8.7 23.8-18 28-9.3-4.2-18-15.5-18-28V17l18-9z" fill="none" stroke="#d8b4fe" stroke-width="2.5"/>
                    <path d="M23 29h18v7H23zm-4 10h26v4H19zm11-12h4v22h-4zm-6 8h16" stroke="#d8b4fe" stroke-width="2.5" stroke-linecap="round"/>
                    <circle cx="32" cy="36" r="7" fill="none" stroke="#d8b4fe" stroke-width="2.5"/>
                </svg>
            </div>
        </div>
    </section>

    <div class="home-bg">
        <section class="max-w-6xl mx-auto text-center py-12 px-6 home-content">
            <div class="hero">
                <div class="hero-content max-w-3xl text-center">
                    <div class="card-hero">
                        <h2 class="text-5xl font-bold mb-6 text-white">
                            Servicio Profesional de Reparación de Computadoras
                        </h2>

                        <p class="text-xl text-gray-300 mb-6">
                            Reparación, mantenimiento y soporte técnico para laptops,
                            computadoras de escritorio y equipos empresariales.
                        </p>

                        <div class="flex justify-center gap-4">
                            <a href="/register" class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-violet-600 to-fuchsia-500 px-7 py-3 text-lg font-semibold text-white shadow-lg shadow-violet-500/25 transition hover:brightness-110">
                                Solicitar Servicio
                            </a>

                            <a href="#precios" class="inline-flex items-center justify-center rounded-full border border-violet-400/70 bg-transparent px-7 py-3 text-lg font-semibold text-violet-200 transition hover:bg-violet-500/10">
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

            <div class="bg-zinc-900 p-6 rounded-xl flex items-start gap-4">
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

            <div class="bg-zinc-900 p-6 rounded-xl flex items-start gap-4">
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

            <div class="bg-zinc-900 p-6 rounded-xl flex items-start gap-4">
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

        <div class="max-w-4xl mx-auto bg-zinc-900 rounded-xl overflow-hidden">

            @forelse ($servicios as $servicio)

                <div class="flex flex-col gap-2 border-b border-zinc-700 p-5 last:border-b-0 md:flex-row md:items-center md:justify-between">

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