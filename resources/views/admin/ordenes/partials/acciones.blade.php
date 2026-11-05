<div class="flex flex-wrap items-center gap-2">
     <a href="{{ route('admin.ordenes.edit', ['orden' => $orden->id]) }}"
        class="rounded-md border border-purple-600 bg-purple-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-purple-500"
    >
        Ver detalle
    </a>

     <a href="{{ route('admin.ordenes.edit', ['orden' => $orden->id]) }}"
        class="rounded-md border border-blue-600 bg-blue-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-blue-500"
    >
        Administrar
    </a>
</div>