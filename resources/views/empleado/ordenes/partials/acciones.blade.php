<a
    href="{{ route('empleado.ordenes.show', ['orden' => $orden->id]) }}"
    class="inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-zinc-900"
    aria-label="Abrir reparación {{ $orden->folio }}"
>
    Abrir reparación
</a>