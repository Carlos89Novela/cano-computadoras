<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-400 font-mono">
                    Restaurante • Hostess & Recepción
                </p>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    📅 Agenda de Reservaciones
                </h2>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('restaurante.mesas.plano') }}" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl border border-slate-700 transition">
                    🗺️ Plano de Mesas
                </a>
                <button type="button" @click="$dispatch('abrir-modal-reservacion')"
                        class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg transition">
                    + Nueva Reservación
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4"
         x-data="moduloReservaciones({
             csrfToken: '{{ csrf_token() }}',
             urlStore: '{{ route('restaurante.reservaciones.store') }}',
             sucursalId: {{ $sucursal->id }}
         })">

        {{-- Filtro por fecha --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl flex items-center justify-between">
            <form method="GET" action="{{ route('restaurante.reservaciones.index') }}" class="flex items-center gap-3">
                <label class="text-xs text-slate-400 font-medium">Ver reservaciones del día:</label>
                <input type="date" name="fecha" value="{{ $fechaFiltro->format('Y-m-d') }}" onchange="this.form.submit()"
                       class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-indigo-500">
            </form>
            <span class="text-xs text-slate-400 font-mono">
                Total para el día: <strong class="text-white">{{ $reservaciones->count() }}</strong>
            </span>
        </div>

        {{-- Listado de Reservaciones --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 text-[10px] uppercase font-semibold bg-slate-950/40">
                            <th class="py-3 px-4">Hora</th>
                            <th class="py-3 px-4">Folio / Cliente</th>
                            <th class="py-3 px-4 text-center">Personas</th>
                            <th class="py-3 px-4">Mesa Asignada</th>
                            <th class="py-3 px-4 text-center">Estado</th>
                            <th class="py-3 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse ($reservaciones as $res)
                            <tr class="hover:bg-slate-950/30 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-indigo-400 text-sm">
                                    {{ $res->fecha_hora_reservacion->format('H:i') }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-white">{{ $res->cliente_nombre }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $res->folio }} • {{ $res->cliente_telefono ?? 'Sin teléfono' }}</div>
                                    @if ($res->notas_especiales)
                                        <div class="text-[10px] text-amber-400 italic mt-0.5">⚠️ {{ $res->notas_especiales }}</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold text-white">
                                    👥 {{ $res->numero_personas }}
                                </td>
                                <td class="py-3.5 px-4 font-mono text-slate-300">
                                    {{ $res->mesa ? $res->mesa->numero_o_nombre . ' (' . $res->mesa->area->nombre . ')' : 'Sin asignar' }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] uppercase font-bold
                                        @if ($res->estado === 'confirmada') bg-indigo-500/10 text-indigo-300 border border-indigo-500/20
                                        @elseif ($res->estado === 'sentada') bg-emerald-500/10 text-emerald-300 border border-emerald-500/20
                                        @elseif ($res->estado === 'cancelada') bg-rose-500/10 text-rose-300 border border-rose-500/20
                                        @else bg-slate-800 text-slate-400 @endif">
                                        {{ $res->estado }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right space-x-2">
                                    @if ($res->estado === 'confirmada')
                                        <button type="button" @click="sentarComensales({{ $res->id }}, '{{ $res->restaurante_mesa_id }}')"
                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg text-[11px] transition">
                                            🪑 Sentar
                                        </button>
                                        <button type="button" @click="cambiarEstado({{ $res->id }}, 'cancelada')"
                                                class="px-2.5 py-1 bg-slate-800 hover:bg-rose-950 hover:text-rose-300 text-slate-400 rounded-lg text-[11px] transition">
                                            Cancelar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500">
                                    No hay reservaciones agendadas para esta fecha.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal Nueva Reservación --}}
        <div x-show="modalCrear" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalCrear = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <h3 class="text-base font-bold text-white">Agendar Nueva Reservación</h3>
                <form @submit.prevent="guardarReservacion()" class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Nombre del Cliente *</label>
                        <input type="text" x-model="form.cliente_nombre" required placeholder="ej. Carlos Mendoza"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Teléfono</label>
                            <input type="text" x-model="form.cliente_telefono" placeholder="(662) 000-0000"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Comensales *</label>
                            <input type="number" min="1" max="50" x-model.number="form.numero_personas" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Fecha y Hora *</label>
                            <input type="datetime-local" x-model="form.fecha_hora_reservacion" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Mesa Sugerida</label>
                            <select x-model="form.restaurante_mesa_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                                <option value="">Asignar al llegar</option>
                                @foreach ($mesas as $m)
                                    <option value="{{ $m->id }}">{{ $m->numero_o_nombre }} ({{ $m->area->nombre }} - {{ $m->capacidad }} pers)</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Notas especiales</label>
                        <input type="text" x-model="form.notas_especiales" placeholder="ej. Aniversario, mesa tranquila"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                    </div>
                    <div class="flex justify-end gap-2 pt-3">
                        <button type="button" @click="modalCrear = false" class="px-3 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs">Cancelar</button>
                        <button type="submit" :disabled="guardando" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs">
                            <span x-show="!guardando">Confirmar Reservación</span>
                            <span x-show="guardando">Guardando...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function moduloReservaciones(config) {
            return {
                csrf: config.csrfToken,
                urlStore: config.urlStore,
                sucursalId: config.sucursalId,
                modalCrear: false,
                guardando: false,

                form: {
                    cliente_nombre: '',
                    cliente_telefono: '',
                    numero_personas: 2,
                    fecha_hora_reservacion: '',
                    restaurante_mesa_id: '',
                    notas_especiales: ''
                },

                init() {
                    window.addEventListener('abrir-modal-reservacion', () => {
                        this.modalCrear = true;
                    });
                },

                async guardarReservacion() {
                    this.guardando = true;
                    try {
                        const payload = Object.assign({}, this.form, { sucursal_id: this.sucursalId });
                        const res = await fetch(this.urlStore, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await res.json();
                        if (res.ok) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error al guardar reservación.');
                        }
                    } catch (e) {
                        alert('Error de conexión.');
                    } finally {
                        this.guardando = false;
                    }
                },

                async sentarComensales(id, mesaId) {
                    if (!mesaId) {
                        mesaId = prompt('Ingrese el ID de la mesa a asignar:');
                        if (!mesaId) return;
                    }
                    try {
                        const res = await fetch(`/restaurante/reservaciones/${id}/sentar`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ restaurante_mesa_id: mesaId })
                        });
                        const data = await res.json();
                        if (res.ok) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error.');
                        }
                    } catch (e) {
                        alert('Error de conexión.');
                    }
                },

                async cambiarEstado(id, nuevoEstado) {
                    if (!confirm('¿Desea cambiar el estado de la reservación a ' + nuevoEstado + '?')) return;
                    try {
                        const res = await fetch(`/restaurante/reservaciones/${id}/estado`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ estado: nuevoEstado })
                        });
                        if (res.ok) {
                            window.location.reload();
                        }
                    } catch (e) {
                        alert('Error al actualizar.');
                    }
                }
            };
        }
    </script>
</x-app-layout>