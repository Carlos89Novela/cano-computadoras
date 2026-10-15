<x-app-layout>
    <x-slot name="header">
        {{-- -------------------------------------------------
            ENCABEZADO
            Identifica la operación y la empresa seleccionada.
        -------------------------------------------------- --}}

        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                Inventario
            </p>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Registrar entrada para {{ $empresa->nombre }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-6">
            {{-- -------------------------------------------------
                MENSAJE DE ÉXITO
                Confirma que la entrada se registró correctamente.
            -------------------------------------------------- --}}

            @if (session('success'))
                <div
                    class="rounded-xl border border-emerald-700 bg-emerald-950/40 p-4 text-sm text-emerald-200"
                    role="status"
                >
                    {{ session('success') }}
                </div>
            @endif

            {{-- -------------------------------------------------
                ENCABEZADO DEL FORMULARIO
                Explica qué efecto tendrá la operación.
            -------------------------------------------------- --}}

            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                    Nueva entrada
                </p>

                <h3 class="mt-2 text-xl font-bold text-gray-900 dark:text-white">
                    Aumentar existencias
                </h3>

                <p class="mt-2 text-sm text-gray-500 dark:text-zinc-400">
                    Selecciona el almacén, el producto y la cantidad recibida.
                    La operación actualizará el saldo y generará un movimiento de inventario.
                </p>
            </section>

            {{-- -------------------------------------------------
                FORMULARIO
                Envía los datos al endpoint de registro de entrada.
            -------------------------------------------------- --}}

            <form
                action="{{ route('inventario.entradas.store', [
                    'empresa' => $empresa->id,
                ]) }}"
                method="POST"
                class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900"
            >
                @csrf

                {{-- -------------------------------------------------
                    ALMACÉN Y PRODUCTO
                    Ambos registros pertenecen a la empresa de la ruta.
                -------------------------------------------------- --}}

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="almacen_id"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-200"
                        >
                            Almacén
                        </label>

                        <select
                            id="almacen_id"
                            name="almacen_id"
                            required
                            aria-describedby="almacen-error"
                            @class([
                                'admin-form-control',
                                'admin-form-control--error' => $errors->has('almacen_id'),
                            ])
                        >
                            <option value="">
                                Selecciona un almacén
                            </option>

                            @foreach ($almacenes as $almacen)
                                <option
                                    value="{{ $almacen->id }}"
                                    @selected(
                                        (string) old('almacen_id')
                                        === (string) $almacen->id
                                    )
                                >
                                    {{ $almacen->nombre }}
                                    ({{ $almacen->codigo }})

                                    @if ($almacen->es_virtual)
                                        - Virtual
                                    @endif
                                </option>
                            @endforeach
                        </select>

                        @error('almacen_id')
                            <p
                                id="almacen-error"
                                class="mt-2 text-sm text-red-500"
                                role="alert"
                            >
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="producto_id"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-200"
                        >
                            Producto
                        </label>

                        <select
                            id="producto_id"
                            name="producto_id"
                            required
                            aria-describedby="producto-error"
                            @class([
                                'admin-form-control',
                                'admin-form-control--error' => $errors->has('producto_id'),
                            ])
                        >
                            <option value="">
                                Selecciona un producto
                            </option>

                            @foreach ($productos as $producto)
                                <option
                                    value="{{ $producto->id }}"
                                    data-costo="{{ $producto->costo_referencia }}"
                                    @selected(
                                        (string) old('producto_id')
                                        === (string) $producto->id
                                    )
                                >
                                    {{ $producto->nombre }}
                                    ({{ $producto->sku }})
                                </option>
                            @endforeach
                        </select>

                        @error('producto_id')
                            <p
                                id="producto-error"
                                class="mt-2 text-sm text-red-500"
                                role="alert"
                            >
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                {{-- -------------------------------------------------
                    CANTIDAD Y COSTO
                    Admiten hasta cuatro posiciones decimales.
                -------------------------------------------------- --}}

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="cantidad"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-200"
                        >
                            Cantidad recibida
                        </label>

                        <input
                            id="cantidad"
                            type="number"
                            name="cantidad"
                            value="{{ old('cantidad') }}"
                            min="0.0001"
                            step="0.0001"
                            required
                            aria-describedby="cantidad-ayuda cantidad-error"
                            @class([
                                'admin-form-control',
                                'admin-form-control--error' => $errors->has('cantidad'),
                            ])
                            placeholder="0.0000"
                        >

                        <p
                            id="cantidad-ayuda"
                            class="mt-2 text-xs text-gray-500 dark:text-zinc-400"
                        >
                            Ingresa la cantidad recibida con hasta cuatro decimales.
                        </p>

                        @error('cantidad')
                            <p
                                id="cantidad-error"
                                class="mt-2 text-sm text-red-500"
                                role="alert"
                            >
                                {{ $message }}
                            </p>
                        @enderror
                                            </div>

                    {{-- -----------------------------------------
                        COSTO UNITARIO
                        Se utiliza para calcular el costo promedio
                        ponderado del producto en el almacén.
                    ------------------------------------------ --}}

                    <div>
                        <label
                            for="costo_unitario"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-200"
                        >
                            Costo unitario
                        </label>

                        <input
                            id="costo_unitario"
                            type="number"
                            name="costo_unitario"
                            value="{{ old('costo_unitario') }}"
                            min="0"
                            step="0.0001"
                            required
                            aria-describedby="costo-ayuda costo-error"
                            @class([
                                'admin-form-control',
                                'admin-form-control--error' => $errors->has('costo_unitario'),
                            ])
                            placeholder="0.0000"
                        >

                        <p
                            id="costo-ayuda"
                            class="mt-2 text-xs text-gray-500 dark:text-zinc-400"
                        >
                            El costo se utilizará para calcular el costo promedio del inventario.
                        </p>

                        @error('costo_unitario')
                            <p
                                id="costo-error"
                                class="mt-2 text-sm text-red-500"
                                role="alert"
                            >
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                {{-- ---------------------------------------------
                    MOTIVO
                    Documenta el origen o la razón de la entrada.
                ---------------------------------------------- --}}

                <div>
                    <label
                        for="motivo"
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-200"
                    >
                        Motivo de la entrada
                    </label>

                    <textarea
                        id="motivo"
                        name="motivo"
                        rows="4"
                        maxlength="1000"
                        required
                        aria-describedby="motivo-ayuda motivo-error"
                        @class([
                            'admin-form-control',
                            'admin-form-control--error' => $errors->has('motivo'),
                        ])
                        placeholder="Ejemplo: Compra inicial para abastecer el almacén."
                    >{{ old('motivo') }}</textarea>

                    <p
                        id="motivo-ayuda"
                        class="mt-2 text-xs text-gray-500 dark:text-zinc-400"
                    >
                        Describe brevemente el origen de las unidades recibidas.
                        Máximo 1000 caracteres.
                    </p>

                    @error('motivo')
                        <p
                            id="motivo-error"
                            class="mt-2 text-sm text-red-500"
                            role="alert"
                        >
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- ---------------------------------------------
                    ACCIONES
                    Registra la entrada o regresa al catálogo.
                ---------------------------------------------- --}}

                <div class="flex flex-wrap gap-3 border-t border-gray-200 pt-6 dark:border-zinc-700">
                    <button
                        type="submit"
                        class="ion-btn ion-btn--primary"
                    >
                        Registrar entrada
                    </button>

                    <a
                        href="{{ route('productos.productos.index', $empresa->id) }}"
                        class="ion-btn ion-btn--secondary"
                    >
                        Volver a productos
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
