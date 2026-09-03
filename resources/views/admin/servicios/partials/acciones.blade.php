<div class="flex flex-wrap items-center gap-2">

    <a
        href="{{ route('admin.servicios.edit', ['servicio' => $servicio->id]) }}"
        class="ion-btn ion-btn-sm ion-btn--primary"
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
            class="ion-btn ion-btn-sm ion-btn--danger"
        >
            Eliminar
        </button>
    </form>

</div>