<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-400 font-mono">
                    Compras & Tesorería • Proveedores
                </p>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    Cuentas por Pagar (CXP) y Conciliación XML
                </h2>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('compras.ordenes.index') }}" 
                   class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl border border-slate-700 transition">
                    ← Órdenes de Compra
                </a>
                <button type="button" @click="$dispatch('abrir-modal-subir-xml')"
                        class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg transition flex items-center gap-1.5">
                    <span>📄</span> Cargar XML de Proveedor
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6"
         x-data="moduloCxp({
             csrfToken: '{{ csrf_token() }}',
             urlSubirXml: '{{ route('compras.cxp.subir-xml') }}'
         })">

        {{-- Tarjetas de Indicadores Financieros --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs uppercase font-bold text-slate-400 block">Saldo Total Pendiente</span>
                <div class="mt-2 flex items-baseline gap-1">
                    <span class="text-3xl font-black font-mono text-white">${{ number_format($saldoTotalPendiente, 2) }}</span>
                    <span class="text-xs text-slate-400">MXN</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block">Pasivo exigible a proveedores</span>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs uppercase font-bold text-rose-400 block">Saldos Vencidos</span>
                <div class="mt-2 flex items-baseline gap-1">
                    <span class="text-3xl font-black font-mono text-rose-400">${{ number_format($totalVencido, 2) }}</span>
                    <span class="text-xs text-rose-400/80">MXN</span>
                </div>
                <span class="text-[11px] text-rose-400/60 mt-1 block">Facturas que rebasaron el plazo de crédito</span>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <span class="text-xs uppercase font-bold text-indigo-400 block">Total de Comprobantes</span>
                <div class="mt-2 flex items-baseline gap-1">
                    <span class="text-3xl font-black font-mono text-indigo-300">{{ $cuentas->count() }}</span>
                    <span class="text-xs text-slate-400">facturas registradas</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-1 block">Cuentas activas e históricas</span>
            </div>
        </div>

        {{-- Barra de Filtros --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('compras.cxp.index') }}" class="flex flex-wrap items-center gap-3 text-xs">
                <div>
                    <select name="estado" onchange="this.form.submit()" 
                            class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Todos los estados</option>
                        <option value="pendiente" {{ $estadoFiltro === 'pendiente' ? 'selected' : '' }}>Solo Pendientes</option>
                        <option value="parcial" {{ $estadoFiltro === 'parcial' ? 'selected' : '' }}>Con Abono Parcial</option>
                        <option value="pagada" {{ $estadoFiltro === 'pagada' ? 'selected' : '' }}>Liquidadas (Pagadas)</option>
                    </select>
                </div>

                <div>
                    <select name="proveedor_id" onchange="this.form.submit()" 
                            class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Todos los proveedores</option>
                        @foreach ($proveedores as $p)
                            <option value="{{ $p->id }}" {{ $proveedorId === $p->id ? 'selected' : '' }}>
                                {{ $p->razon_social ?: $p->nombre }} ({{ $p->rfc }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        {{-- Tabla de Cuentas por Pagar --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 text-[10px] uppercase font-semibold bg-slate-950/40">
                            <th class="py-3 px-4">Proveedor / RFC</th>
                            <th class="py-3 px-4">Folio / UUID Fiscal</th>
                            <th class="py-3 px-4">Emisión</th>
                            <th class="py-3 px-4">Vencimiento</th>
                            <th class="py-3 px-4 text-right">Total Factura</th>
                            <th class="py-3 px-4 text-right">Saldo Pendiente</th>
                            <th class="py-3 px-4 text-center">Estado</th>
                            <th class="py-3 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-mono">
                        @forelse ($cuentas as $c)
                            @php
                                $esVencida = in_array($c->estado, ['pendiente', 'parcial']) && $c->fecha_vencimiento->isPast();
                            @endphp
                            <tr class="hover:bg-slate-950/30 transition {{ $esVencida ? 'bg-rose-950/10' : '' }}">
                                <td class="py-3 px-4 font-sans">
                                    <div class="font-bold text-white">{{ $c->proveedor->razon_social ?: $c->proveedor->nombre }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $c->proveedor->rfc }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-slate-200 font-bold">{{ $c->serie ? $c->serie . '-' : '' }}{{ $c->folio ?? 'S/F' }}</div>
                                    <div class="text-[10px] text-slate-400 truncate max-w-[140px]" title="{{ $c->uuid_sat }}">
                                        {{ $c->uuid_sat ?? 'Sin UUID' }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-300">
                                    {{ $c->fecha_emision->format('d/m/Y') }}
                                </td>
                                <td class="py-3 px-4 {{ $esVencida ? 'text-rose-400 font-bold' : 'text-slate-300' }}">
                                    {{ $c->fecha_vencimiento->format('d/m/Y') }}
                                    @if ($esVencida)
                                        <span class="block text-[9px] uppercase tracking-wide">⚠️ Vencida</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right text-slate-300">
                                    ${{ number_format($c->total, 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-bold {{ $c->saldo_pendiente > 0 ? 'text-white' : 'text-slate-500' }}">
                                    ${{ number_format($c->saldo_pendiente, 2) }}
                                </td>
                                <td class="py-3 px-4 text-center font-sans">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] uppercase font-bold
                                        @if ($c->estado === 'pagada') bg-emerald-500/10 text-emerald-400 border border-emerald-500/20
                                        @elseif ($c->estado === 'parcial') bg-amber-500/10 text-amber-400 border border-amber-500/20
                                        @else bg-slate-800 text-slate-300 border border-slate-700 @endif">
                                        {{ $c->estado }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-sans space-x-1">
                                    @if ($c->saldo_pendiente > 0)
                                        <button type="button" @click="abrirModalAbono({{ Js::from($c) }})"
                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg text-[11px] transition">
                                            💵 Abonar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-500 font-sans">
                                    No se encontraron cuentas por pagar con los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal Carga de XML --}}
        <div x-show="modalSubirXml" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalSubirXml = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>📄 Cargar CFDI de Proveedor (.xml)</span>
                    </h3>
                    <button type="button" @click="modalSubirXml = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form @submit.prevent="subirArchivoXml()" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Archivo XML SAT *</label>
                        <input type="file" accept=".xml" @change="archivoXml = $event.target.files[0]" required
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-slate-300 focus:outline-none focus:border-indigo-500 file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Días de Crédito</label>
                            <input type="number" x-model.number="diasCredito" min="0" max="180"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Proveedor (Opcional)</label>
                            <select x-model="proveedorSeleccionadoId" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                                <option value="">Detectar por RFC del XML</option>
                                @foreach ($proveedores as $p)
                                    <option value="{{ $p->id }}">{{ $p->razon_social ?: $p->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Notas u Observaciones</label>
                        <input type="text" x-model="notas" placeholder="ej. Compra autorizada por gerencia"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="modalSubirXml = false" class="px-3 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs">Cancelar</button>
                        <button type="submit" :disabled="procesandoXml" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-bold rounded-xl text-xs flex items-center gap-1.5">
                            <span x-show="!procesandoXml">Procesar y Conciliar</span>
                            <span x-show="procesandoXml" class="animate-pulse">Leyendo XML...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Aplicar Abono / Pago --}}
        <div x-show="modalAbono" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalAbono = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white flex items-center gap-1.5">
                        <span>💵 Registrar Pago a Proveedor</span>
                    </h3>
                    <button type="button" @click="modalAbono = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <template x-if="cuentaActiva">
                    <form @submit.prevent="guardarPago()" class="space-y-3 text-xs">
                        <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800 space-y-1">
                            <div class="text-[11px] text-slate-400">Proveedor: <strong class="text-white" x-text="cuentaActiva.proveedor.razon_social"></strong></div>
                            <div class="text-[11px] text-slate-400">Saldo Exigible: <strong class="text-emerald-400 font-mono" x-text="`$${Number(cuentaActiva.saldo_pendiente).toFixed(2)}`"></strong></div>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Monto a Pagar *</label>
                            <input type="number" step="0.01" min="0.01" :max="cuentaActiva.saldo_pendiente" x-model.number="pagoForm.monto" required
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-indigo-500">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-slate-300 font-medium mb-1">Fecha de Pago</label>
                                <input type="date" x-model="pagoForm.fecha_pago" required
                                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-slate-300 font-medium mb-1">Forma de Pago SAT</label>
                                <select x-model="pagoForm.forma_pago_sat" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                                    <option value="03">03 - Transferencia</option>
                                    <option value="01">01 - Efectivo</option>
                                    <option value="02">02 - Cheque</option>
                                    <option value="04">04 - Tarjeta Crédito</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Referencia Bancaria / Folio SPEI</label>
                            <input type="text" x-model="pagoForm.referencia_bancaria" placeholder="ej. SPEI-998823"
                                   class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="modalAbono = false" class="px-3 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs">Cancelar</button>
                            <button type="submit" :disabled="procesandoPago" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold rounded-xl text-xs">
                                <span x-show="!procesandoPago">Confirmar Pago</span>
                                <span x-show="procesandoPago">Aplicando...</span>
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>

    <script>
        function moduloCxp(config) {
            return {
                csrf: config.csrfToken,
                urlSubirXml: config.urlSubirXml,
                modalSubirXml: false,
                modalAbono: false,
                procesandoXml: false,
                procesandoPago: false,

                archivoXml: null,
                diasCredito: 30,
                proveedorSeleccionadoId: '',
                notas: '',

                cuentaActiva: null,
                pagoForm: {
                    monto: 0,
                    fecha_pago: new Date().toISOString().slice(0, 10),
                    forma_pago_sat: '03',
                    referencia_bancaria: ''
                },

                init() {
                    window.addEventListener('abrir-modal-subir-xml', () => {
                        this.modalSubirXml = true;
                    });
                },

                async subirArchivoXml() {
                    if (!this.archivoXml) {
                        alert('Selecciona un archivo XML.');
                        return;
                    }

                    this.procesandoXml = true;
                    const formData = new FormData();
                    formData.append('xml_file', this.archivoXml);
                    formData.append('dias_credito', this.diasCredito);
                    if (this.proveedorSeleccionadoId) {
                        formData.append('proveedor_id', this.proveedorSeleccionadoId);
                    }
                    if (this.notas) {
                        formData.append('notas', this.notas);
                    }

                    try {
                        const res = await fetch(this.urlSubirXml, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        const data = await res.json();
                        if (res.ok) {
                            alert(`${data.message}\nProveedor: ${data.proveedor}\nTotal: $${data.total}`);
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error al procesar el archivo XML.');
                        }
                    } catch (e) {
                        alert('Error de conexión.');
                    } finally {
                        this.procesandoXml = false;
                    }
                },

                abrirModalAbono(cuenta) {
                    this.cuentaActiva = cuenta;
                    this.pagoForm.monto = Number(cuenta.saldo_pendiente);
                    this.modalAbono = true;
                },

                async guardarPago() {
                    this.procesandoPago = true;
                    try {
                        const res = await fetch(`/operacion/compras/cuentas-por-pagar/${this.cuentaActiva.id}/pagar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.pagoForm)
                        });

                        const data = await res.json();
                        if (res.ok) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error al aplicar el pago.');
                        }
                    } catch (e) {
                        alert('Error de conexión.');
                    } finally {
                        this.procesandoPago = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>