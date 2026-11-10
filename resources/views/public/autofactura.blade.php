<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Portal de Facturación Electrónica | {{ $venta->empresa->nombre }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen py-8 px-4 flex flex-col items-center justify-center font-sans">

    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl space-y-5"
         x-data="portalAutofactura({
             token: '{{ $venta->token_autofactura }}',
             yaFacturada: {{ $yaFacturada ? 'true' : 'false' }},
             expirada: {{ $expirada ? 'true' : 'false' }},
             urlTimbrar: '{{ route('autofactura.procesar', $venta->token_autofactura) }}',
             csrf: '{{ csrf_token() }}'
         })">

        <div class="text-center border-b border-slate-800 pb-4">
            <span class="text-2xl">🧾</span>
            <h1 class="text-lg font-bold text-white mt-1">{{ $venta->empresa->nombre }}</h1>
            <p class="text-xs text-slate-400">Portal de Emisión de CFDI 4.0</p>
        </div>

        {{-- Resumen de Consumo del Ticket --}}
        <div class="p-3.5 bg-slate-950 rounded-2xl border border-slate-800 text-xs space-y-1 font-mono">
            <div class="flex justify-between text-slate-400">
                <span>Folio Ticket:</span>
                <span class="text-white font-bold">{{ $venta->folio }}</span>
            </div>
            <div class="flex justify-between text-slate-400">
                <span>Fecha Consumo:</span>
                <span class="text-white">{{ $venta->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="flex justify-between text-slate-200 font-bold pt-1.5 border-t border-slate-800 text-sm">
                <span>Total Pagado:</span>
                <span class="text-emerald-400">${{ number_format((float) $venta->total, 2) }} MXN</span>
            </div>
        </div>

        {{-- Estado: Ya Facturada --}}
        <template x-if="yaFacturada">
            <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl text-center space-y-3">
                <p class="text-xs text-emerald-300 font-bold">✓ Esta venta ya ha sido facturada exitosamente.</p>
                <div class="flex gap-2">
                    <a href="{{ route('facturacion.ventas.descargar-xml', $venta->id) }}" target="_blank"
                       class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold font-mono">
                        Descargar XML
                    </a>
                    <a href="{{ route('facturacion.ventas.descargar-pdf', $venta->id) }}" target="_blank"
                       class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold">
                        Descargar PDF
                    </a>
                </div>
            </div>
        </template>

        {{-- Formulario para Capturar Datos Fiscales --}}
        <template x-if="!yaFacturada && !expirada && !facturaExitosa">
            <form @submit.prevent="solicitarFactura()" class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">RFC Fiscal *</label>
                    <input type="text" x-model="form.rfc" required maxlength="13" placeholder="ej. CACX850101XXX"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white uppercase font-mono focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-slate-300 font-medium mb-1">Nombre o Razón Social (Sin SA de CV) *</label>
                    <input type="text" x-model="form.razon_social" required placeholder="Nombre fiscal tal como en tu constancia"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white uppercase focus:outline-none focus:border-indigo-500">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Código Postal *</label>
                        <input type="text" x-model="form.codigo_postal" required maxlength="5" placeholder="83000"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">Régimen Fiscal *</label>
                        <select x-model="form.regimen_fiscal" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            <option value="601">601 - Personas Morales</option>
                            <option value="605">605 - Sueldos y Salarios</option>
                            <option value="612">612 - Personas Físicas Act. Emp.</option>
                            <option value="626">626 - RESICO</option>
                            <option value="616">616 - Sin obligaciones</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-slate-300 font-medium mb-1">Uso del CFDI *</label>
                    <select x-model="form.uso_cfdi" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        <option value="G03">G03 - Gastos en general</option>
                        <option value="G01">G01 - Adquisición de mercancías</option>
                        <option value="S01">S01 - Sin efectos fiscales</option>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-300 font-medium mb-1">Correo Electrónico (para recibir los archivos) *</label>
                    <input type="email" x-model="form.correo" required placeholder="cliente@correo.com"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                </div>

                <template x-if="mensajeError">
                    <p class="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs" x-text="mensajeError"></p>
                </template>

                <button type="submit" :disabled="procesando"
                        class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-2 mt-2">
                    <span x-show="!procesando">⚡ Emitir Factura SAT</span>
                    <span x-show="procesando">Timbrando comprobante fiscal...</span>
                </button>
            </form>
        </template>

        {{-- Éxito Inmediato con Enlaces de Descarga --}}
        <template x-if="facturaExitosa">
            <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl text-center space-y-3">
                <span class="text-3xl">🎉</span>
                <p class="text-xs text-emerald-300 font-bold" x-text="mensajeExito"></p>
                <div class="flex gap-2">
                    <a :href="urlXml" target="_blank" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold font-mono">
                        Descargar XML
                    </a>
                    <a :href="urlPdf" target="_blank" class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold">
                        Descargar PDF
                    </a>
                </div>
            </div>
        </template>
    </div>

    <script>
        function portalAutofactura(config) {
            return {
                token: config.token,
                yaFacturada: config.yaFacturada,
                expirada: config.expirada,
                urlTimbrar: config.urlTimbrar,
                csrf: config.csrf,
                procesando: false,
                mensajeError: '',
                facturaExitosa: false,
                mensajeExito: '',
                urlXml: '',
                urlPdf: '',

                form: {
                    rfc: '',
                    razon_social: '',
                    codigo_postal: '',
                    regimen_fiscal: '612',
                    uso_cfdi: 'G03',
                    correo: ''
                },

                async solicitarFactura() {
                    this.procesando = true;
                    this.mensajeError = '';

                    try {
                        const res = await fetch(this.urlTimbrar, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });

                        const data = await res.json();
                        if (res.ok) {
                            this.facturaExitosa = true;
                            this.mensajeExito = data.message;
                            this.urlXml = data.descargar_xml;
                            this.urlPdf = data.descargar_pdf;
                        } else {
                            this.mensajeError = data.message || 'Error al generar la factura.';
                        }
                    } catch (e) {
                        this.mensajeError = 'Error de conexión con el servicio fiscal.';
                    } finally {
                        this.procesando = false;
                    }
                }
            };
        }
    </script>
</body>
</html>