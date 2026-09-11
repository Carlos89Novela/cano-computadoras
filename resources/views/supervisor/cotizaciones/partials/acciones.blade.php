<form
    action="{{ route('supervisor.cotizaciones.rechazar', ['orden' => $orden->id]) }}"
    method="POST"
    class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
>
    @csrf
    Revisar cotización
</form>