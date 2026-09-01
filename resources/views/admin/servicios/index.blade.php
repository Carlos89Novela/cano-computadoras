<x-app-layout>

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Servicios y precios
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Administra el catálogo público de servicios.
                </p>
            </div>

             <a
                href="{{ route('admin.servicios.create') }}"
                class="inline-block rounded-lg bg-purple-600 px-5 py-3 font-semibold text-white transition hover:bg-purple-700"
            >
                Nuevo servicio
            </a>

        </div>
    </x-slot>

    <div class="bg-gray-950 py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-6">

            @if (session('success'))
                <div class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            <section class="overflow-visible rounded-xl border border-zinc-700 bg-zinc-900 p-6 shadow-xl">

                <livewire:servicios-table />

            </section>

        </div>
    </div>

</x-app-layout>