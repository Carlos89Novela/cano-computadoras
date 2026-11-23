{{-- ===================================================================== --}}
{{-- VISTA: REGISTRAR ENTRADA DIRECTA A INVENTARIO POR EMPRESA / ALMACÉN --}}
{{-- Incrementa existencias en el almacén seleccionado, actualiza el      --}}
{{-- costo promedio ponderado y registra el movimiento en el Kardex.      --}}
{{-- Soporta operación multi-tenant y multi-giro (Restaurante, Taller    --}}
{{-- de Cómputo/Celulares, Abarrotes y Ferretería).                      --}}
{{-- ===================================================================== --}}
<x-app-layout title="Entrada a Inventario">
    @php
        // Identificar el giro comercial de la empresa actual para adaptar filtros y etiquetas
        $giroEmpresa = strtolower((string) ($empresa->giro_comercial ?? ''));
        $esRestaurante = in_array($giroEmpresa, ['restaurantes_alimentos', 'restaurante'], true);

        // En giro restaurante, filtrar únicamente insumos con prefijo SKU "INS-"
        // para no mezclar platillos, bebidas ni modificadores/extras en la entrada de almacén.
        // En otros giros (taller de cómputo/celulares, abarrotes, ferretería) se muestra el catálogo recibido.
        $productosDisponibles = $esRestaurante
            ? $productos->filter(function ($item) {
                $sku = strtoupper(trim((string) ($item->sku ?? '')));
                return str_starts_with($sku, 'INS-');
            })->values()
            : $productos;
    @endphp

    {{-- ----------------------------------------------------------------- --}}
    {{-- ENCABEZADO SUPERIOR: IDENTIFICA OPERACIÓN Y EMPRESA ACTIVA        --}}
    {{-- ----------------------------------------------------------------- --}}
    <x-slot name="header">
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
            {{-- ------------------------------------------------------------- --}}
            {{-- MENSAJE DE CONFIRMACIÓN TRAS GUARDAR UNA ENTRADA              --}}
            {{-- ------------------------------------------------------------- --}}
            @if (session('success'))
                <div
                    class="rounded-xl border border-emerald-700 bg-emerald-950/40 p-4 text-sm text-emerald-200"
                    role="status"
                >
                    {{ session('success') }}
                </div>
            @endif

            {{-- ------------------------------------------------------------- --}}
            {{-- TARJETA INTRODUCTORIA: ALCANCE E IMPACTO DE LA OPERACIÓN       --}}
            {{-- ------------------------------------------------------------- --}}
            <section class="rounded-xl bg-white p-6 shadow dark:bg-zinc-900">
                <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                    Nueva entrada
                </p>

                <h3 class="mt-2 text-xl font-bold text-gray-900 dark:text-white">
                    Aumentar existencias
                </h3>

                <p class="mt-2 text-sm text-gray-500 dark:text-zinc-400">
                    Selecciona el almacén, {{ $esRestaurante ? 'el insumo' : 'el producto o insumo' }} y la cantidad recibida.
                    La operación actualizará el saldo y generará un movimiento de inventario.
                </p>
            </section>

            {{-- ------------------------------------------------------------- --}}
            {{-- FORMULARIO DE REGISTRO DE ENTRADA DE INVENTARIO               --}}
            {{-- ------------------------------------------------------------- --}}
            <form
                action="{{ route('inventario.entradas.store', [
                    'empresa' => $empresa->id,
                ]) }}"
                method="POST"
                class="space-y-6 rounded-xl bg-white p-6 shadow dark:bg-zinc-900"
            >
                @csrf

                {{-- --------------------------------------------------------- --}}
                {{-- SELECCIÓN DE ALMACÉN Y PRODUCTO / INSUMO                  --}}
                {{-- --------------------------------------------------------- --}}
                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Almacén destino dentro de la empresa activa --}}
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

                    {{-- Insumo (en restaurante) o Producto / Insumo (en otros giros) --}}
                    <div>
                        <label
                            for="producto_id"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-zinc-200"
                        >
                            {{ $esRestaurante ? 'Insumo' : 'Producto / Insumo' }}
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
                                {{ $esRestaurante ? 'Selecciona un insumo' : 'Selecciona un producto' }}
                            </option>

                            @foreach ($productosDisponibles as $producto)
                                @php
                                    // Clasificación visual orientativa según prefijo de SKU para giros no filtrados
                                    $skuUpper = strtoupper((string) ($producto->sku ?? ''));
                                    $etiquetaTipo = $esRestaurante ? '' : match (true) {
                                        str_starts_with($skuUpper, 'INS-') => '[Insumo]',
                                        str_starts_with($skuUpper, 'EXT-') => '[Extra/Modificador]',
                                        str_starts_with($skuUpper, 'BEB-'),
                                        str_starts_with($skuUpper, 'ENT-'),
                                        str_starts_with($skuUpper, 'PF-'),
                                        str_starts_with($skuUpper, 'SOP-') => '[Platillo/Bebida]',
                                        default => '',
                                    };
                                @endphp
                                <option
                                    value="{{ $producto->id }}"
                                    data-costo="{{ $producto->costo_referencia ?? '' }}"
                                    data-sku="{{ $producto->sku }}"
                                    @selected(
                                        (string) old('producto_id')
                                        === (string) $producto->id
                                    )
                                >
                                    {{ $producto->nombre }}
                                    ({{ $producto->sku }})
                                    @if ($etiquetaTipo !== '')
                                        {{ $etiquetaTipo }}
                                    @endif
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

                {{-- --------------------------------------------------------- --}}
                {{-- CANTIDAD RECIBIDA Y COSTO UNITARIO (HASTA 4 DECIMALES)     --}}
                {{-- --------------------------------------------------------- --}}
                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Cantidad recibida --}}
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

                    {{-- Costo unitario utilizado para costo promedio ponderado --}}
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

                {{-- --------------------------------------------------------- --}}
                {{-- MOTIVO / JUSTIFICACIÓN DEL MOVIMIENTO DE ENTRADA          --}}
                {{-- --------------------------------------------------------- --}}
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

                {{-- --------------------------------------------------------- --}}
                {{-- BOTONES DE ACCIÓN: GUARDAR ENTRADA O VOLVER A PRODUCTOS   --}}
                {{-- --------------------------------------------------------- --}}
                <div class="flex flex-wrap gap-3 border-t border-gray-200 pt-6 dark:border-zinc-700">
                    <button
                        type="submit"
                        class="ion-btn ion-btn--primary"
                    >
                        Registrar entrada
                    </button>

                    <a
                        href="{{ route('productos.index', $empresa->id) }}"
                        class="ion-btn ion-btn--secondary"
                    >
                        Volver a productos
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ----------------------------------------------------------------- --}}
    {{-- SUGERENCIA DE COSTO REFERENCIA AL ELEGIR PRODUCTO SI ESTÁ VACÍO   --}}
    {{-- ----------------------------------------------------------------- --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const selectProducto = document.getElementById('producto_id');
            const inputCosto = document.getElementById('costo_unitario');

            if (!selectProducto || !inputCosto) return;

            selectProducto.addEventListener('change', () => {
                const opcionSeleccionada = selectProducto.options[selectProducto.selectedIndex];
                const costoReferencia = opcionSeleccionada ? opcionSeleccionada.getAttribute('data-costo') : '';

                // Solo precarga el costo de referencia si el campo está vacío o en cero
                const valorActual = inputCosto.value.trim();
                if ((valorActual === '' || Number(valorActual) === 0) && costoReferencia !== '' && Number(costoReferencia) > 0) {
                    inputCosto.value = Number(costoReferencia).toFixed(4);
                }
            });
        });
    </script>
</x-app-layout>
