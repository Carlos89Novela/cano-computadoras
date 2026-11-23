<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @props(['title' => null])

        @php
            $empresaActual = $empresa
                ?? auth()->user()?->empresa
                ?? auth()->user()?->sucursales()->with('empresa')->first()?->empresa
                ?? \App\Models\Empresa::query()->where('giro_comercial', 'restaurantes_alimentos')->first()
                ?? \App\Models\Empresa::query()->first();

            $nombreEmpresa = $empresaActual?->nombre ?? config('app.name', 'Sistema');
            $tituloSeccion = trim((string) ($title ?? $__env->yieldContent('title')));
        @endphp
        <title>{{ $tituloSeccion !== '' ? $tituloSeccion . ' | ' . $nombreEmpresa : $nombreEmpresa }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css'])
        @livewireStyles
    </head>
    <body class="bg-gray-950 font-sans text-white antialiased">
        <div class="min-h-screen bg-gray-950">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                @include('components.flash-messages')
                {{ $slot }}
            </main>
        </div>
        @livewireScripts

        @vite(['resources/js/app.js'])
        <!-- DataTables helpers -->
        @include('components.datatables-styles')

        <!-- jQuery + DataTables JS (loaded once globally) -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

        {{-- Directiva obligatoria para scripts de vistas hijas --}}
        @stack('scripts')
    </body>
</html>
