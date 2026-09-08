<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Notificaciones
            </h2>
            @if ($notificacionesNoLeidas > 0)
                <form
                    method="POST"
                    action="{{ route('notificaciones.leer-todas') }}"
                >
                    @csrf
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-700 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-600"
                    >
                        Marcar todas como leídas
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl space-y-4 px-6">

            @if (session('success'))
                <div class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @forelse ($notificaciones as $notificacion)
                <div
                    class="rounded-xl border p-6 shadow
                    {{ $notificacion->read_at
                        ? 'border-zinc-800 bg-zinc-900'
                        : 'border-purple-600 bg-purple-950' }}"
                >
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <p class="font-bold text-white">
                                {{ $notificacion->data['mensaje']?? 'Tienes una actualización de reparación.' }}
                            </p>

                            @if (filled($notificacion->data['mensaje_cliente'] ?? null))
                                <p class="mt-2 text-gray-300">
                                    {{ $notificacion->data['mensaje_cliente'] }}
                                </p>
                            @endif

                            <p class="mt-2 text-sm text-gray-400">
                                Folio:
                                {{ $notificacion->data['folio'] ?? 'No disponible' }}
                            </p>

                            <p class="mt-1 text-xs text-gray-500">
                                {{ $notificacion->created_at?->format('d/m/Y H:i') ?? 'Fecha no disponible' }}
                            </p>
                        </div>

                         <form
                            action="{{ route('notificaciones.leer', ['notificacion' => $notificacion->id]) }}"
                            method="POST"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="rounded-lg bg-purple-600 px-4 py-2 font-semibold text-white hover:bg-purple-700"
                            >
                                Ver reparación
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-zinc-900 p-10 text-center text-gray-300">
                    No tienes notificaciones.
                </div>
            @endforelse

            <div class="pt-4">

                {{ $notificaciones->links() }}

            </div>

        </div>
    </div>
</x-app-layout>