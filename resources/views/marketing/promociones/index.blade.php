<x-app-layout>
    {{-- ========================================================================= --}}
    {{-- SECCIÓN 1: ENCABEZADO SUPERIOR Y ACCESO DIRECTO AL PUNTO DE VENTA         --}}
    {{-- ========================================================================= --}}
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="text-[10px] uppercase font-extrabold tracking-wider px-2 py-0.5 rounded bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">
                    🎁 {{ $empresa->nombre }}
                </span>
                <h1 class="text-xl font-black text-white tracking-tight mt-1">
                    Promociones, Combos y Monedero Electrónico
                </h1>
            </div>
            <a href="{{ route('pos.index') }}" class="px-3.5 py-2 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/30 rounded-xl text-xs font-bold transition">
                💳 Ir al Punto de Venta
            </a>
        </div>
    </x-slot>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 2: CONTENEDOR PRINCIPAL CON ESTADO ALPINE (CREAR / EDITAR)        --}}
    {{-- ========================================================================= --}}
    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6"
         x-data="gestorPromociones(@js($productos->map(fn($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'precio' => (float) $p->precio_base])))">

        {{-- Barra de Navegación de Pestañas y Botón de Nueva Promoción --}}
        <div class="flex items-center justify-between flex-wrap gap-3 bg-slate-900 border border-slate-800 p-3 rounded-2xl">
            <div class="flex gap-2">
                <button type="button" @click="tabActiva = 'promociones'"
                        :class="tabActiva === 'promociones' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-950 text-slate-400 hover:text-white'"
                        class="px-4 py-2 rounded-xl text-xs transition cursor-pointer">
                    🏷️ Promociones y Combos ({{ $promociones->count() }})
                </button>
                <button type="button" @click="tabActiva = 'lealtad'"
                        :class="tabActiva === 'lealtad' ? 'bg-amber-600 text-white font-bold' : 'bg-slate-950 text-slate-400 hover:text-white'"
                        class="px-4 py-2 rounded-xl text-xs transition cursor-pointer">
                    ⭐ Clientes Destacados, Puntos y Monedero
                </button>
            </div>

            <button x-show="tabActiva === 'promociones'" type="button" @click="abrirNuevaPromocion()"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs shadow-lg transition cursor-pointer">
                + Nueva Promoción / Combo
            </button>
        </div>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 3: PESTAÑA 1 - TARJETAS DE PROMOCIONES Y COMBOS CONFIGURADOS       --}}
        {{-- ========================================================================= --}}
        <div x-show="tabActiva === 'promociones'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @php
                $nombresDias = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];
                $etiquetasTipo = [
                    '2x1' => '🔥 2x1',
                    'segundo_al_porcentaje' => '🏷️ 2do con Descuento',
                    'descuento_porcentaje' => '⏰ Hora Feliz / % Descuento',
                    'precio_fijo_combo' => '🍔 Combo Precio Fijo',
                    'regalo_en_compra' => '🎁 Regalo en Compra',
                ];
            @endphp

            @forelse ($promociones as $promo)
                @php $vigenteAhora = $promo->estaVigenteAhora(); @endphp
                <div class="p-5 bg-slate-900 border {{ $vigenteAhora ? 'border-emerald-500/40' : 'border-slate-800' }} rounded-2xl flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                                {{ $etiquetasTipo[$promo->tipo] ?? $promo->tipo }}
                            </span>
                            @if ($vigenteAhora)
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300">● Vigente Ahora</span>
                            @else
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">○ Fuera de horario/fecha</span>
                            @endif
                        </div>

                        <h3 class="text-base font-bold text-white">{{ $promo->nombre }}</h3>
                        @if ($promo->descripcion)
                            <p class="text-xs text-slate-400 mt-1">{{ $promo->descripcion }}</p>
                        @endif

                        <div class="mt-3 p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 text-xs space-y-1">
                            @if ($promo->tipo === 'segundo_al_porcentaje')
                                <div class="text-emerald-400 font-bold">2da unidad al {{ (int)$promo->valor_descuento }}% de descuento</div>
                            @elseif ($promo->tipo === 'descuento_porcentaje')
                                <div class="text-emerald-400 font-bold">{{ (int)$promo->valor_descuento }}% de descuento directo</div>
                            @elseif ($promo->tipo === 'precio_fijo_combo')
                                <div class="text-emerald-400 font-bold">Precio Especial Combo: ${{ number_format((float)$promo->precio_combo, 2) }}</div>
                            @elseif ($promo->tipo === '2x1')
                                <div class="text-emerald-400 font-bold">Lleva 2 y paga 1 (100% desc. en el 2do)</div>
                            @endif

                            <div class="text-[11px] text-slate-300">
                                <strong>Días:</strong>
                                @if (empty($promo->dias_semana) || count($promo->dias_semana) >= 7)
                                    Todos los días
                                @else
                                    {{ collect($promo->dias_semana)->map(fn($d) => $nombresDias[(int)$d] ?? '')->implode(', ') }}
                                @endif
                            </div>

                            @if ($promo->hora_inicio && $promo->hora_fin)
                                <div class="text-[11px] text-amber-300">
                                    <strong>⏰ Horario:</strong> {{ substr($promo->hora_inicio, 0, 5) }} a {{ substr($promo->hora_fin, 0, 5) }} hrs
                                </div>
                            @endif

                            @if ($promo->fecha_inicio || $promo->fecha_fin)
                                <div class="text-[11px] text-slate-400">
                                    <strong>📅 Vigencia:</strong>
                                    {{ $promo->fecha_inicio?->format('d/m/Y') ?? 'Inicio' }} al {{ $promo->fecha_fin?->format('d/m/Y') ?? 'Indefinido' }}
                                </div>
                            @endif

                            @if ($promo->solo_clientes_destacados)
                                <div class="text-[10px] font-bold text-amber-400">⭐ Exclusivo Clientes Destacados / VIP</div>
                            @endif
                        </div>

                        @if ($promo->items->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($promo->items as $it)
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-300">
                                        {{ $it->producto?->nombre ?? ('Categoría: ' . $it->categoria?->nombre) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Acciones de la tarjeta: Pausar/Activar, Editar y Eliminar --}}
                    <div class="pt-3 border-t border-slate-800 flex items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('marketing.promociones.toggle', $promo) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-3 py-1.5 rounded-lg font-semibold cursor-pointer {{ $promo->activo ? 'bg-amber-500/20 text-amber-300' : 'bg-emerald-500/20 text-emerald-300' }}">
                                    {{ $promo->activo ? 'Pausar' : 'Activar' }}
                                </button>
                            </form>

                            <button type="button"
                                    @click='editarPromocion(@json($promo))'
                                    class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 rounded-lg font-bold transition cursor-pointer">
                                ✏️ Editar
                            </button>
                        </div>

                        <form method="POST" action="{{ route('marketing.promociones.destroy', $promo) }}" onsubmit="return confirm('¿Eliminar esta promoción?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-rose-400 hover:text-rose-300 font-semibold cursor-pointer">Eliminar</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-16 text-center bg-slate-900 border border-slate-800 rounded-2xl text-slate-400 text-xs">
                    Aún no hay promociones o combos registrados. Haz clic en <strong>+ Nueva Promoción / Combo</strong> para crear tu primera oferta.
                </div>
            @endforelse
        </div>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 4: PESTAÑA 2 - CLIENTES DESTACADOS, PUNTOS Y MONEDERO ELECTRÓNICO --}}
        {{-- ========================================================================= --}}
        <div x-show="tabActiva === 'lealtad'" x-cloak class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
            <div class="p-4 border-b border-slate-800">
                <form method="POST" action="{{ route('marketing.promociones.clientes.store') }}" class="p-4 bg-slate-950/60 border-b border-slate-800 grid grid-cols-1 sm:grid-cols-6 gap-2 text-xs mb-3">
                    @csrf
                    <input type="text" name="nombre" required placeholder="Nombre completo *" class="sm:col-span-2 bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white">
                    <input type="text" name="telefono_whatsapp" placeholder="WhatsApp / Teléfono" class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white">
                    <select name="nivel_lealtad" class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white">
                        <option value="regular">Nivel: Regular</option>
                        <option value="frecuente">Nivel: Frecuente</option>
                        <option value="destacado">⭐ Destacado VIP</option>
                    </select>
                    <input type="number" step="0.01" name="saldo_monedero" placeholder="Saldo inicial ($)" class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl cursor-pointer">
                        + Registrar Cliente
                    </button>
                </form>
                <h2 class="text-sm font-bold text-white">Cartera de Lealtad, Puntos y Monedero Electrónico</h2>
                <p class="text-xs text-slate-400">Administra clientes destacados (VIP), abonos a monedero electrónico y puntos acumulados.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-slate-400 uppercase text-[10px]">
                        <tr>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Nivel</th>
                            <th class="px-4 py-3">Puntos</th>
                            <th class="px-4 py-3">Saldo Monedero</th>
                            <th class="px-4 py-3">Ajustar Nivel / Abonar Monedero</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($clientesLealtad as $cli)
                            <tr>
                                <td class="px-4 py-3 font-bold text-white">
                                    {{ $cli->nombre }}
                                    <span class="block text-[10px] font-normal text-slate-400">{{ $cli->rfc ?? $cli->correo ?? 'Cliente Registrado' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($cli->nivel_lealtad === 'destacado')
                                        <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-bold">⭐ Destacado VIP</span>
                                    @elseif ($cli->nivel_lealtad === 'frecuente')
                                        <span class="px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 font-bold">💎 Frecuente</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">Regular</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-indigo-400">{{ number_format((int)$cli->puntos_acumulados) }} pts</td>
                                <td class="px-4 py-3 font-mono font-bold text-emerald-400">${{ number_format((float)$cli->saldo_monedero, 2) }}</td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('marketing.promociones.clientes.lealtad', $cli) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <select name="nivel_lealtad" class="bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white">
                                            <option value="regular" @selected($cli->nivel_lealtad === 'regular')>Regular</option>
                                            <option value="frecuente" @selected($cli->nivel_lealtad === 'frecuente')>Frecuente</option>
                                            <option value="destacado" @selected($cli->nivel_lealtad === 'destacado')>⭐ Destacado</option>
                                        </select>
                                        <input type="number" step="0.01" name="abono_monedero" placeholder="+$ Monedero" class="w-24 bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white">
                                        <input type="number" name="puntos_ajuste" placeholder="+/- Pts" class="w-20 bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white">
                                        <button type="submit" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg text-xs cursor-pointer">Aplicar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay clientes registrados en esta empresa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- SECCIÓN 5: MODAL UNIFICADO (CREAR O EDITAR PROMOCIÓN / COMBO)             --}}
        {{-- ========================================================================= --}}
        <div x-show="modalPromo" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalPromo = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 space-y-4 shadow-2xl max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider"
                        x-text="editandoId ? '✏️ Editar Promoción o Combo' : '🎁 Configurar Nueva Promoción o Combo'"></h3>
                    <button type="button" @click="modalPromo = false" class="text-slate-400 hover:text-white text-lg cursor-pointer">&times;</button>
                </div>

                <form :action="editandoId ? `/marketing/promociones/${editandoId}` : '{{ route('marketing.promociones.store') }}'"
                      method="POST"
                      class="space-y-4 text-xs">
                    @csrf
                    <input type="hidden" name="_method" :value="editandoId ? 'PUT' : 'POST'">

                    {{-- Nombre y Tipo --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-slate-300 block mb-1">Nombre de la Promoción *</label>
                            <input type="text" name="nombre" x-model="formPromo.nombre" required placeholder="Ej. Martes 2x1 en Cappuccinos"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="text-slate-300 block mb-1">Mecánica / Tipo *</label>
                            <select name="tipo" x-model="formPromo.tipo" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                                <option value="2x1">🔥 2x1 (Paga 1 y lleva 2)</option>
                                <option value="segundo_al_porcentaje">🏷️ En la compra de 1, el 2do al % de descuento</option>
                                <option value="descuento_porcentaje">⏰ Hora Feliz / Descuento % directo</option>
                                <option value="precio_fijo_combo">🍔 Combo / Paquete a Precio Fijo</option>
                            </select>
                        </div>
                    </div>

                    {{-- Descripción opcional --}}
                    <div>
                        <label class="text-slate-300 block mb-1">Descripción o condiciones (opcional)</label>
                        <input type="text" name="descripcion" x-model="formPromo.descripcion" placeholder="Ej. Aplica en consumo en comedor y para llevar"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                    </div>

                    {{-- Parámetros económicos, cupón y puntos --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div x-show="formPromo.tipo === 'segundo_al_porcentaje' || formPromo.tipo === 'descuento_porcentaje'">
                            <label class="text-slate-300 block mb-1">% Descuento (ej. 50 para 50%)</label>
                            <input type="number" step="1" min="1" max="100" name="valor_descuento" x-model.number="formPromo.valor_descuento"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                        </div>

                        <div x-show="formPromo.tipo === 'precio_fijo_combo'">
                            <label class="text-slate-300 block mb-1">Precio Final del Combo ($)</label>
                            <input type="number" step="0.01" min="0" name="precio_combo" x-model="formPromo.precio_combo" placeholder="199.00"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                        </div>

                        <div>
                            <label class="text-slate-300 block mb-1">Cupón Opcional</label>
                            <input type="text" name="codigo_cupon" x-model="formPromo.codigo_cupon" placeholder="Ej. HORA2X1"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white uppercase">
                        </div>

                        <div>
                            <label class="text-slate-300 block mb-1">Puntos Extra al Comprar</label>
                            <input type="number" min="0" name="puntos_extra_otorgados" x-model.number="formPromo.puntos_extra_otorgados"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                        </div>
                    </div>

                    {{-- Días de la semana + Checkbox "Todos los días" --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-slate-300 font-semibold">Días válidos:</label>
                            <label class="inline-flex items-center gap-1.5 text-emerald-400 font-bold cursor-pointer select-none">
                                <input type="checkbox"
                                       x-model="todosLosDias"
                                       @change="toggleTodosLosDias()"
                                       class="rounded text-emerald-500 bg-slate-900 border-slate-700">
                                <span>Todos los días</span>
                            </label>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach ([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'] as $numDia => $nomDia)
                                <label class="px-2.5 py-1.5 rounded-lg border flex items-center gap-1.5 cursor-pointer transition"
                                       :class="formPromo.dias_semana.includes({{ $numDia }}) ? 'bg-indigo-600/30 border-indigo-500 text-white font-bold' : 'bg-slate-950 border-slate-800 text-slate-300'">
                                    <input type="checkbox"
                                           name="dias_semana[]"
                                           value="{{ $numDia }}"
                                           x-model.number="formPromo.dias_semana"
                                           @change="todosLosDias = (formPromo.dias_semana.length === 7)"
                                           class="rounded text-indigo-600 bg-slate-900 border-slate-700">
                                    <span>{{ $nomDia }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Horarios y Fechas de Vigencia --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="text-slate-300 block mb-1">Hora Inicio (Hora Feliz)</label>
                            <input type="time" name="hora_inicio" x-model="formPromo.hora_inicio" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="text-slate-300 block mb-1">Hora Fin</label>
                            <input type="time" name="hora_fin" x-model="formPromo.hora_fin" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="text-slate-300 block mb-1">Vigencia Desde</label>
                            <input type="datetime-local" name="fecha_inicio" x-model="formPromo.fecha_inicio" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                        </div>
                        <div>
                            <label class="text-slate-300 block mb-1">Vigencia Hasta</label>
                            <input type="datetime-local" name="fecha_fin" x-model="formPromo.fecha_fin" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                        </div>
                    </div>

                    {{-- Selector Interactivo de Productos con Buscador y Checkboxes --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {{-- Columna Izquierda: Buscador y Lista con Checkboxes --}}
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-slate-300 font-semibold">Productos que participan</label>
                                <span class="text-[11px] text-indigo-400 font-bold" x-text="`${formPromo.producto_ids.length} seleccionados`"></span>
                            </div>

                            {{-- Inputs ocultos que envían los IDs seleccionados al formulario --}}
                            <template x-for="idSel in formPromo.producto_ids" :key="idSel">
                                <input type="hidden" name="producto_ids[]" :value="idSel">
                            </template>

                            {{-- Etiquetas de productos ya seleccionados --}}
                            <div x-show="formPromo.producto_ids.length > 0" class="flex flex-wrap gap-1 max-h-16 overflow-y-auto p-1.5 bg-slate-950/80 rounded-lg border border-indigo-500/30">
                                <template x-for="idSel in formPromo.producto_ids" :key="'tag-' + idSel">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-600/30 text-indigo-200 text-[10px] font-semibold">
                                        <span x-text="obtenerNombreProducto(idSel)" class="truncate max-w-[140px]"></span>
                                        <button type="button" @click="toggleProducto(idSel)" class="text-indigo-300 hover:text-white font-bold cursor-pointer">&times;</button>
                                    </span>
                                </template>
                            </div>

                            {{-- Barra de búsqueda instantánea --}}
                            <input type="text" x-model="filtroProducto" placeholder="🔍 Filtrar platillo o bebida..."
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-white focus:border-indigo-500 focus:outline-none">

                            {{-- Lista desplazable con checkboxes táctiles --}}
                            <div class="h-36 overflow-y-auto bg-slate-950 border border-slate-800 rounded-xl divide-y divide-slate-800/70">
                                <template x-for="prod in productosFiltrados" :key="prod.id">
                                    <label @click.prevent="toggleProducto(prod.id)"
                                           :class="formPromo.producto_ids.includes(prod.id) ? 'bg-indigo-600/20 text-white' : 'text-slate-300 hover:bg-slate-900'"
                                           class="flex items-center justify-between gap-2 px-3 py-2 cursor-pointer transition select-none">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <input type="checkbox" :checked="formPromo.producto_ids.includes(prod.id)"
                                                   class="rounded text-indigo-500 bg-slate-900 border-slate-700 pointer-events-none">
                                            <span class="text-xs truncate" x-text="prod.nombre"></span>
                                        </div>
                                        <span class="text-[11px] font-mono text-emerald-400 shrink-0" x-text="`$${prod.precio.toFixed(2)}`"></span>
                                    </label>
                                </template>
                                <div x-show="productosFiltrados.length === 0" class="p-4 text-center text-slate-500 text-xs">
                                    No se encontraron productos con ese nombre.
                                </div>
                            </div>
                        </div>

                        {{-- Columna Derecha: Categoría completa y Exclusividad VIP --}}
                        <div class="space-y-3">
                            <div>
                                <label class="text-slate-300 font-semibold block mb-1">O aplicar a toda una Categoría</label>
                                <select name="categoria_id" x-model="formPromo.categoria_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                                    <option value="">-- Ninguna categoría completa --</option>
                                    @foreach ($categorias as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[10px] text-slate-500 mt-1">Si seleccionas productos específicos a la izquierda, deja este campo vacío.</p>
                            </div>

                            <label class="flex items-center gap-2 p-3 bg-amber-950/20 border border-amber-500/30 rounded-xl cursor-pointer">
                                <input type="checkbox" name="solo_clientes_destacados" value="1" x-model="formPromo.solo_clientes_destacados" class="rounded text-amber-500 bg-slate-950 border-slate-700">
                                <span class="text-amber-300 font-bold text-xs">⭐ Exclusivo para Clientes Destacados / VIP</span>
                            </label>
                        </div>
                    </div>

                    {{-- Botones de Acción del Modal --}}
                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-800">
                        <button type="button" @click="modalPromo = false" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl cursor-pointer">Cancelar</button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl cursor-pointer"
                                x-text="editandoId ? 'Actualizar Promoción' : 'Guardar Promoción'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 6: LÓGICA ALPINE.JS PARA CREACIÓN, EDICIÓN Y FILTROS              --}}
    {{-- ========================================================================= --}}
    <script>
        function gestorPromociones(catalogoProductos) {
            return {
                tabActiva: 'promociones',
                modalPromo: false,
                editandoId: null,
                todosLosDias: false,
                filtroProducto: '',
                catalogoProductos: catalogoProductos || [],

                formPromo: {
                    nombre: '',
                    descripcion: '',
                    tipo: '2x1',
                    valor_descuento: 50,
                    precio_combo: '',
                    codigo_cupon: '',
                    puntos_extra_otorgados: 0,
                    dias_semana: [],
                    hora_inicio: '',
                    hora_fin: '',
                    fecha_inicio: '',
                    fecha_fin: '',
                    producto_ids: [],
                    categoria_id: '',
                    solo_clientes_destacados: false,
                },

                get productosFiltrados() {
                    const q = this.filtroProducto.toLowerCase().trim();
                    if (!q) return this.catalogoProductos;
                    return this.catalogoProductos.filter(p => p.nombre.toLowerCase().includes(q));
                },

                toggleProducto(id) {
                    const numId = Number(id);
                    if (this.formPromo.producto_ids.includes(numId)) {
                        this.formPromo.producto_ids = this.formPromo.producto_ids.filter(item => item !== numId);
                    } else {
                        this.formPromo.producto_ids.push(numId);
                    }
                },

                obtenerNombreProducto(id) {
                    return this.catalogoProductos.find(p => p.id === Number(id))?.nombre || '';
                },

                toggleTodosLosDias() {
                    if (this.todosLosDias) {
                        this.formPromo.dias_semana = [0, 1, 2, 3, 4, 5, 6];
                    } else {
                        this.formPromo.dias_semana = [];
                    }
                },

                abrirNuevaPromocion() {
                    this.editandoId = null;
                    this.filtroProducto = '';
                    this.todosLosDias = false;
                    this.formPromo = {
                        nombre: '',
                        descripcion: '',
                        tipo: '2x1',
                        valor_descuento: 50,
                        precio_combo: '',
                        codigo_cupon: '',
                        puntos_extra_otorgados: 0,
                        dias_semana: [],
                        hora_inicio: '',
                        hora_fin: '',
                        fecha_inicio: '',
                        fecha_fin: '',
                        producto_ids: [],
                        categoria_id: '',
                        solo_clientes_destacados: false,
                    };
                    this.modalPromo = true;
                },

                editarPromocion(promo) {
                    this.editandoId = promo.id;
                    this.filtroProducto = '';

                    const diasGuardados = Array.isArray(promo.dias_semana) && promo.dias_semana.length > 0
                        ? promo.dias_semana.map(Number)
                        : [0, 1, 2, 3, 4, 5, 6];

                    this.todosLosDias = (diasGuardados.length === 7);

                    const prodIds = (promo.items || [])
                        .map(i => i.producto_id)
                        .filter(Boolean)
                        .map(Number);

                    const catItem = (promo.items || []).find(i => i.categoria_id);

                    // Formatear fechas ISO a YYYY-MM-DDTHH:MM para el input datetime-local
                    const formatearInputFecha = (fechaStr) => {
                        if (!fechaStr) return '';
                        return fechaStr.replace(' ', 'T').substring(0, 16);
                    };

                    this.formPromo = {
                        nombre: promo.nombre || '',
                        descripcion: promo.descripcion || '',
                        tipo: promo.tipo || '2x1',
                        valor_descuento: Number(promo.valor_descuento || 0),
                        precio_combo: promo.precio_combo || '',
                        codigo_cupon: promo.codigo_cupon || '',
                        puntos_extra_otorgados: Number(promo.puntos_extra_otorgados || 0),
                        dias_semana: diasGuardados,
                        hora_inicio: promo.hora_inicio ? promo.hora_inicio.substring(0, 5) : '',
                        hora_fin: promo.hora_fin ? promo.hora_fin.substring(0, 5) : '',
                        fecha_inicio: formatearInputFecha(promo.fecha_inicio),
                        fecha_fin: formatearInputFecha(promo.fecha_fin),
                        producto_ids: prodIds,
                        categoria_id: catItem ? String(catItem.categoria_id) : '',
                        solo_clientes_destacados: Boolean(promo.solo_clientes_destacados),
                    };

                    this.modalPromo = true;
                },
            };
        }
    </script>
</x-app-layout>