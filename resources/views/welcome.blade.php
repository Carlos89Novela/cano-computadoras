<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Cano Computadoras</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-black text-white">

    <!-- CABECERA -->
    <header class="max-w-7xl mx-auto px-6 py-6 flex justify-between items-center">

        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-xl">
                C
            </div>

            <h1 class="text-3xl font-bold text-purple-500">
                Cano Computadoras
            </h1>
        </div>

        <div class="flex gap-3">

            <a href="/login" class="bg-zinc-800 hover:bg-zinc-700 px-4 py-2 rounded-lg">
                Iniciar Sesión
            </a>

            <a href="/register" class="bg-zinc-800 hover:bg-zinc-700 px-4 py-2 rounded-lg">
                Registrarse
            </a>

        </div>

    </header>

    <!-- HERO -->
    <section class="max-w-6xl mx-auto text-center py-24 px-6">

        <h2 class="text-5xl font-bold mb-6">
            Servicio Profesional de Reparación de Computadoras
        </h2>

        <p class="text-xl text-gray-300 mb-10">
            Reparación, mantenimiento y soporte técnico para laptops,
            computadoras de escritorio y equipos empresariales.
        </p>

        <div class="flex justify-center gap-4">

            <a href="/register" class="ion-btn bg-purple-500 hover:bg-purple-600 px-6 py-3 rounded-lg text-white font-bold">
                Solicitar Servicio
            </a>

            <a href="#precios" class="ion-btn bg-zinc-800 hover:bg-zinc-700 px-6 py-3 rounded-lg text-white font-bold">
                Ver Precios
            </a>

        </div>

    </section>

    <!-- SERVICIOS -->
    <section class="max-w-6xl mx-auto px-6">

        <h2 class="text-4xl font-bold text-center mb-10">
            Nuestros Servicios
        </h2>

        <div class="grid md:grid-cols-3 gap-6">

            <div class="bg-zinc-900 p-6 rounded-xl">
                <h3 class="text-xl font-bold text-purple-500 mb-3">
                    Formateo
                </h3>

                <p>
                    Instalación y configuración de Windows.
                </p>
            </div>

            <div class="bg-zinc-900 p-6 rounded-xl">
                <h3 class="text-xl font-bold text-purple-500 mb-3">
                    Eliminación de Virus
                </h3>

                <p>
                    Limpieza de malware y optimización.
                </p>
            </div>

            <div class="bg-zinc-900 p-6 rounded-xl">
                <h3 class="text-xl font-bold text-purple-500 mb-3">
                    Actualización SSD
                </h3>

                <p>
                    Incrementa la velocidad de tu equipo.
                </p>
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