<div class="flex flex-wrap items-center gap-2">

    <a
        href="{{ route('admin.servicios.edit', [
            'servicio' => $servicio->id,
        ]) }}"
        class="rounded-lg bg-zinc-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-zinc-600"
    >
        Editar
    </a>

    <form
        action="{{ route('admin.servicios.destroy', ['servicio' => $servicio->id]) }}"
        method="POST"
        onsubmit="return confirm('¿Deseas eliminar este servicio?');"
    >
        @csrf
        @method('DELETE')

        <button
            type="submit"
            class="rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-800"
        >
            Eliminar
        </button>
    </form>

</div>