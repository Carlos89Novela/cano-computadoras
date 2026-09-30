<x-app-layout>
    {{-- =====================================================
         ENCABEZADO
         Muestra el contexto de navegación y el título
         para la edición de un proveedor existente.
    ====================================================== --}}

    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-purple-500">
                    Compras e inventario
                </p>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Editar proveedor
                </h2>

                <p class="text-xs text-zinc-400">
                    Empresa: <span class="font-semibold text-zinc-300">{{ $empresa->nombre }}</span>
                </p>
            </div>

            <div>
                <a
                    href="{{ route('proveedores.index', [
                        'empresa' => $empresa->id,
                    ]) }}"
                    class="inline-flex items-center rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-semibold text-zinc-300 shadow-sm transition hover:bg-zinc-700 hover:text-white"
                >
                    Volver al listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto w-full max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            {{-- =================================================
                 MENSAJES DE ÉXITO Y ERROR
            ================================================== --}}

            @if (session('success'))
                <div
                    class="rounded-lg border border-green-700 bg-green-950 p-4 text-green-200"
                    role="status"
                >
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="rounded-lg border border-red-700 bg-red-950 p-4 text-red-200"
                    role="alert"
                >
                    <p class="font-semibold">
                        No fue posible actualizar el proveedor.
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- =================================================
                 FICHA INFORMATIVA
                 Muestra la empresa, el estado actual y autoría.
            ================================================== --}}

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-zinc-950 p-4">
                        <dt class="text-xs font-semibold uppercase text-zinc-500">
                            Empresa
                        </dt>

                        <dd class="mt-1 font-semibold text-white">
                            {{ $empresa->nombre }}
                        </dd>
                    </div>

                    <div class="rounded-lg bg-zinc-950 p-4">
                        <dt class="text-xs font-semibold uppercase text-zinc-500">
                            Estado
                        </dt>

                        <dd class="mt-1 font-semibold">
                            @if ($proveedor->activo)
                                <span class="text-green-400">
                                    Activo
                                </span>
                            @else
                                <span class="text-red-400">
                                    Inactivo
                                </span>
                            @endif
                        </dd>
                    </div>

                    <div class="rounded-lg bg-zinc-950 p-4">
                        <dt class="text-xs font-semibold uppercase text-zinc-500">
                            Creado por
                        </dt>

                        <dd class="mt-1 font-semibold text-white">
                            {{ $proveedor->creadoPor?->name ?? 'Usuario no disponible' }}
                        </dd>
                    </div>
                </dl>

                @if (! $proveedor->activo && $proveedor->motivo_desactivacion)
                    <div class="mt-4 rounded-lg border border-red-900 bg-red-950/40 p-4 text-xs text-red-200">
                        <p class="font-semibold text-red-300">
                            Motivo de desactivación registrado:
                        </p>

                        <p class="mt-1 text-zinc-300">
                            {{ $proveedor->motivo_desactivacion }}
                        </p>
                    </div>
                @endif
            </section>

            {{-- =================================================
                 FORMULARIO DE EDICIÓN
                 Envía los datos validados mediante PUT a update.
            ================================================== --}}

            <form
                action="{{ route('proveedores.update', [
                    'empresa' => $empresa->id,
                    'proveedor' => $proveedor->id,
                ]) }}"
                method="POST"
                class="space-y-6 rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow"
            >
                @csrf
                @method('PUT')

                {{-- Fila 1: Código y Nombre --}}
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label
                            for="codigo"
                            class="mb-2 block text-sm font-semibold text-zinc-200"
                        >
                            Código del proveedor <span class="text-purple-400">*</span>
                        </label>

                        <input
                            id="codigo"
                            name="codigo"
                            type="text"
                            required
                            maxlength="40"
                            value="{{ old('codigo', $proveedor->codigo) }}"
                            @class([
                                'w-full rounded-lg border bg-zinc-950 p-3 font-mono text-white placeholder:text-zinc-500 uppercase focus:ring-purple-500',
                                'border-zinc-700 focus:border-purple-500' => ! $errors->has('codigo'),
                                'border-red-600 focus:border-red-500' => $errors->has('codigo'),
                            ])
                            placeholder="Ejemplo: PROV-001"
                        >

                        <p class="mt-1 text-xs text-zinc-400">
                            Identificador único dentro de la empresa. Solo letras mayúsculas, números y guiones.
                        </p>

                        @error('codigo')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="nombre"
                            class="mb-2 block text-sm font-semibold text-zinc-200"
                        >
                            Nombre comercial o descriptivo <span class="text-purple-400">*</span>
                        </label>

                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            required
                            maxlength="200"
                            value="{{ old('nombre', $proveedor->nombre) }}"
                            @class([
                                'w-full rounded-lg border bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:ring-purple-500',
                                'border-zinc-700 focus:border-purple-500' => ! $errors->has('nombre'),
                                'border-red-600 focus:border-red-500' => $errors->has('nombre'),
                            ])
                            placeholder="Ejemplo: Mayorista de Cómputo del Norte"
                        >

                        <p class="mt-1 text-xs text-zinc-400">
                            Nombre con el cual se identifica al proveedor en el día a día.
                        </p>

                        @error('nombre')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                {{-- Fila 2: Información Fiscal (Razón Social y RFC) --}}
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label
                            for="razon_social"
                            class="mb-2 block text-sm font-semibold text-zinc-200"
                        >
                            Razón social
                        </label>

                        <input
                            id="razon_social"
                            name="razon_social"
                            type="text"
                            maxlength="200"
                            value="{{ old('razon_social', $proveedor->razon_social) }}"
                            @class([
                                'w-full rounded-lg border bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:ring-purple-500',
                                'border-zinc-700 focus:border-purple-500' => ! $errors->has('razon_social'),
                                'border-red-600 focus:border-red-500' => $errors->has('razon_social'),
                            ])
                            placeholder="Ejemplo: Distribuidora Cano SA de CV"
                        >

                        <p class="mt-1 text-xs text-zinc-400">
                            Opcional. Razón social registrada ante la autoridad fiscal.
                        </p>

                        @error('razon_social')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="rfc"
                            class="mb-2 block text-sm font-semibold text-zinc-200"
                        >
                            RFC
                        </label>

                        <input
                            id="rfc"
                            name="rfc"
                            type="text"
                            maxlength="20"
                            value="{{ old('rfc', $proveedor->rfc) }}"
                            @class([
                                'w-full rounded-lg border bg-zinc-950 p-3 font-mono text-white placeholder:text-zinc-500 uppercase focus:ring-purple-500',
                                'border-zinc-700 focus:border-purple-500' => ! $errors->has('rfc'),
                                'border-red-600 focus:border-red-500' => $errors->has('rfc'),
                            ])
                            placeholder="Ejemplo: ABC123456XYZ"
                        >

                        <p class="mt-1 text-xs text-zinc-400">
                            Opcional. Registro Federal de Contribuyentes con homoclave.
                        </p>

                        @error('rfc')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                {{-- Fila 3: Información de Contacto --}}
                <div class="grid gap-6 sm:grid-cols-3">
                    <div>
                        <label
                            for="contacto"
                            class="mb-2 block text-sm font-semibold text-zinc-200"
                        >
                            Persona de contacto
                        </label>

                        <input
                            id="contacto"
                            name="contacto"
                            type="text"
                            maxlength="150"
                            value="{{ old('contacto', $proveedor->contacto) }}"
                            @class([
                                'w-full rounded-lg border bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:ring-purple-500',
                                'border-zinc-700 focus:border-purple-500' => ! $errors->has('contacto'),
                                'border-red-600 focus:border-red-500' => $errors->has('contacto'),
                            ])
                            placeholder="Ejemplo: Juan Pérez"
                        >

                        <p class="mt-1 text-xs text-zinc-400">
                            Opcional. Ejecutivo o vendedor asignado.
                        </p>

                        @error('contacto')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="telefono"
                            class="mb-2 block text-sm font-semibold text-zinc-200"
                        >
                            Teléfono
                        </label>

                        <input
                            id="telefono"
                            name="telefono"
                            type="text"
                            maxlength="30"
                            value="{{ old('telefono', $proveedor->telefono) }}"
                            @class([
                                'w-full rounded-lg border bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:ring-purple-500',
                                'border-zinc-700 focus:border-purple-500' => ! $errors->has('telefono'),
                                'border-red-600 focus:border-red-500' => $errors->has('telefono'),
                            ])
                            placeholder="Ejemplo: 6621234567"
                        >

                        <p class="mt-1 text-xs text-zinc-400">
                            Opcional. Teléfono para cotizaciones y pedidos.
                        </p>

                        @error('telefono')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="correo"
                            class="mb-2 block text-sm font-semibold text-zinc-200"
                        >
                            Correo electrónico
                        </label>

                        <input
                            id="correo"
                            name="correo"
                            type="email"
                            maxlength="255"
                            value="{{ old('correo', $proveedor->correo) }}"
                            @class([
                                'w-full rounded-lg border bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:ring-purple-500',
                                'border-zinc-700 focus:border-purple-500' => ! $errors->has('correo'),
                                'border-red-600 focus:border-red-500' => $errors->has('correo'),
                            ])
                            placeholder="ventas@proveedor.com"
                        >

                        <p class="mt-1 text-xs text-zinc-400">
                            Opcional. Correo para el envío de órdenes y facturas.
                        </p>

                        @error('correo')
                            <p class="mt-2 text-sm text-red-500">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                {{-- Fila 4: Dirección --}}
                <div>
                    <label
                        for="direccion"
                        class="mb-2 block text-sm font-semibold text-zinc-200"
                    >
                        Dirección física / fiscal
                    </label>

                    <textarea
                        id="direccion"
                        name="direccion"
                        rows="3"
                        maxlength="2000"
                        @class([
                            'w-full rounded-lg border bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:ring-purple-500',
                            'border-zinc-700 focus:border-purple-500' => ! $errors->has('direccion'),
                            'border-red-600 focus:border-red-500' => $errors->has('direccion'),
                        ])
                        placeholder="Calle, número, colonia, código postal, municipio y estado."
                    >{{ old('direccion', $proveedor->direccion) }}</textarea>

                    <p class="mt-1 text-xs text-zinc-400">
                        Opcional. Domicilio para entregas o facturación (máximo 2000 caracteres).
                    </p>

                    @error('direccion')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Fila 5: Notas internas --}}
                <div>
                    <label
                        for="notas"
                        class="mb-2 block text-sm font-semibold text-zinc-200"
                    >
                        Notas u observaciones internas
                    </label>

                    <textarea
                        id="notas"
                        name="notas"
                        rows="4"
                        maxlength="4000"
                        @class([
                            'w-full rounded-lg border bg-zinc-950 p-3 text-white placeholder:text-zinc-500 focus:ring-purple-500',
                            'border-zinc-700 focus:border-purple-500' => ! $errors->has('notas'),
                            'border-red-600 focus:border-red-500' => $errors->has('notas'),
                        ])
                        placeholder="Condiciones comerciales, días de crédito, tiempos estimados de entrega o acuerdos especiales."
                    >{{ old('notas', $proveedor->notas) }}</textarea>

                    <p class="mt-1 text-xs text-zinc-400">
                        Opcional. Notas internas no visibles para el proveedor (máximo 4000 caracteres).
                    </p>

                    @error('notas')
                        <p class="mt-2 text-sm text-red-500">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Aviso de Auditoría --}}
                <div class="rounded-lg border border-blue-900 bg-blue-950/40 p-4">
                    <p class="font-semibold text-blue-200">
                        Modificación auditada
                    </p>

                    <p class="mt-2 text-sm text-blue-100">
                        Se registrarán los valores anteriores y nuevos, el usuario responsable,
                        la fecha y hora exacta, la sesión y la dirección IP en la bitácora inmutable de auditoría.
                    </p>
                </div>

                {{-- Botones de Acción --}}
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="rounded-lg bg-purple-600 px-6 py-3 font-semibold text-white transition hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-zinc-900"
                    >
                        Guardar cambios
                    </button>

                    <a
                        href="{{ route('proveedores.index', [
                            'empresa' => $empresa->id,
                        ]) }}"
                        class="rounded-lg border border-zinc-700 bg-zinc-800 px-6 py-3 font-semibold text-zinc-200 transition hover:bg-zinc-700 hover:text-white"
                    >
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
