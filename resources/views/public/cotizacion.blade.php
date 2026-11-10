<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cotización de Reparación • {{ $orden->folio }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col justify-between antialiased selection:bg-indigo-500 selection:text-white">

    <div class="max-w-3xl mx-auto w-full px-4 py-8 sm:px-6 space-y-6"
         x-data="portalCotizacion({
             csrfToken: '{{ csrf_token() }}',
             urlAutorizar: '{{ route('public.cotizacion.autorizar', $orden->token_publico) }}',
             urlRechazar: '{{ route('public.cotizacion.rechazar', $orden->token_publico) }}'
         })">

        {{-- Encabezado de Identidad --}}
        <header class="flex items-center justify-between border-b border-slate-800 pb-5">
            <div>
                <span class="text-[11px] font-mono uppercase tracking-widest text-indigo-400 font-bold block">
                    Cano Computadoras • Servicio Técnico Especializado
                </span>
                <h1 class="text-2xl font-black text-white mt-1">Presupuesto de Reparación</h1>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400 block font-mono">Folio de Orden</span>
                <span class="text-lg font-bold font-mono text-white">{{ $orden->folio }}</span>
            </div>
        </header>

        {{-- Alertas de Estado --}}
        @if ($yaFirmada)
            <div class="p-4 rounded-2xl bg-emerald-950/40 border border-emerald-800/80 text-emerald-200 text-xs flex items-center gap-3">
                <span class="text-xl">✅</span>
                <div>
                    <strong class="font-bold block">Cotización Autorizada</strong>
                    <span>Esta orden cuenta con firma digital registrada el {{ $orden->firmado_at?->format('d/m/Y H:i') }}. El equipo se encuentra en fase técnica.</span>
                </div>
            </div>
        @elseif ($rechazada)
            <div class="p-4 rounded-2xl bg-rose-950/40 border border-rose-800/80 text-rose-200 text-xs flex items-center gap-3">
                <span class="text-xl">🛑</span>
                <div>
                    <strong class="font-bold block">Cotización Declinada</strong>
                    <span>El cliente declinó la reparación. Motivo: {{ $orden->motivo_rechazo ?? 'No especificado' }}.</span>
                </div>
            </div>
        @elseif ($expirada)
            <div class="p-4 rounded-2xl bg-amber-950/40 border border-amber-800/80 text-amber-200 text-xs flex items-center gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <strong class="font-bold block">Presupuesto Vencido</strong>
                    <span>El plazo de vigencia para esta cotización ha finalizado. Comuníquese con la sucursal para renovar el diagnóstico.</span>
                </div>
            </div>
        @endif

        {{-- Datos del Equipo y Diagnóstico --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl space-y-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Datos del Equipo</h3>
                <div class="text-sm font-semibold text-white">
                    {{ $orden->equipo?->tipo ?? 'Equipo' }}: {{ $orden->equipo?->marca }} {{ $orden->equipo?->modelo }}
                </div>
                <div class="text-xs text-slate-400 font-mono">
                    Serie: {{ $orden->equipo?->numero_serie ?? 'N/D' }}
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl space-y-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Cliente</h3>
                <div class="text-sm font-semibold text-white">{{ $orden->user?->name ?? 'Cliente' }}</div>
                <div class="text-xs text-slate-400">{{ $orden->user?->email }}</div>
            </div>
        </div>

        {{-- Diagnóstico Técnico --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-400">Diagnóstico & Problema Reportado</h3>
            <p class="text-xs text-slate-300 leading-relaxed font-sans">
                {{ $orden->diagnostico ?? ($orden->descripcion_falla ?? 'Diagnóstico general de componentes y verificación de funcionalidad.') }}
            </p>
        </div>

        {{-- Desglose de Refacciones y Mano de Obra --}}
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-800">
                <h3 class="text-xs font-bold uppercase tracking-wider text-white">Conceptos y Costos Estimados</h3>
            </div>
            <div class="p-5 space-y-4">
                <div class="flex justify-between items-center text-xs text-slate-300 py-1 border-b border-slate-800/60">
                    <span>Mano de Obra y Servicios Técnicos</span>
                    <span class="font-mono font-bold text-white">${{ number_format((float) ($orden->costo_estimado ?? 0), 2) }}</span>
                </div>

                @if ($orden->reservasInventario && $orden->reservasInventario->isNotEmpty())
                    <div class="space-y-2 pt-2">
                        <span class="text-[11px] font-bold uppercase text-slate-400 block">Refacciones Comprometidas</span>
                        @foreach ($orden->reservasInventario as $reserva)
                            <div class="flex justify-between items-center text-xs text-slate-400 pl-2">
                                <span>• {{ $reserva->producto?->nombre }} (x{{ number_format((float) $reserva->cantidad_reservada, 0) }})</span>
                                <span class="font-mono text-slate-300">Incluido en presupuesto</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="pt-3 border-t border-slate-800 flex justify-between items-baseline">
                    <span class="text-xs uppercase font-bold text-slate-300">Total a Autorizar</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-2xl font-black font-mono text-emerald-400">
                            ${{ number_format((float) ($orden->costo_estimado ?? 0), 2) }}
                        </span>
                        <span class="text-xs text-slate-400 font-mono">MXN</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Módulo de Firma Digital si está en espera de autorización --}}
        @if (!$yaFirmada && !$rechazada && !$expirada)
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>✍️ Firma Digital de Conformidad</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Dibuje su firma con el dedo en su pantalla o con el ratón para autorizar el inicio de la reparación.
                    </p>
                </div>

                {{-- Canvas de Firma --}}
                <div class="border-2 border-dashed border-slate-700 rounded-2xl bg-slate-950 p-2 flex flex-col items-center">
                    <canvas id="signatureCanvas" 
                            width="600" 
                            height="200" 
                            class="w-full max-w-full touch-none cursor-crosshair bg-slate-950 rounded-xl"></canvas>
                    
                    <div class="w-full flex justify-between items-center px-2 pt-2 text-[11px] text-slate-500 border-t border-slate-900 mt-2">
                        <span>Lienzo sensible al tacto</span>
                        <button type="button" @click="limpiarFirma()" class="text-indigo-400 hover:text-indigo-300 font-semibold">
                            Borrar y Reintentar
                        </button>
                    </div>
                </div>

                {{-- Botones de Acción --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                    <button type="button" 
                            @click="autorizarCotizacion()"
                            :disabled="procesando"
                            class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold rounded-xl text-xs shadow-lg transition flex items-center justify-center gap-2">
                        <span x-show="!procesando">✓ Aprobar Reparación</span>
                        <span x-show="procesando" class="animate-pulse">Registrando firma...</span>
                    </button>

                    <button type="button" 
                            @click="modalRechazo = true"
                            :disabled="procesando"
                            class="w-full py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs transition">
                        ✕ Declinar Presupuesto
                    </button>
                </div>
            </div>
        @endif

        {{-- Modal de Rechazo --}}
        <div x-show="modalRechazo" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.away="modalRechazo = false" class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-white">Declinar Cotización</h3>
                    <button type="button" @click="modalRechazo = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <label class="block text-slate-300 font-medium">Indique brevemente el motivo de rechazo *</label>
                    <textarea x-model="motivoRechazo" rows="3" placeholder="ej. Costo fuera de presupuesto, no deseo reparar por el momento..."
                              class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-white focus:outline-none focus:border-rose-500"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2 text-xs">
                    <button type="button" @click="modalRechazo = false" class="px-3 py-2 bg-slate-800 text-slate-300 rounded-xl">Cancelar</button>
                    <button type="button" @click="rechazarCotizacion()" :disabled="!motivoRechazo.trim()" 
                            class="px-4 py-2 bg-rose-600 hover:bg-rose-500 disabled:opacity-50 text-white font-bold rounded-xl">
                        Confirmar Rechazo
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        function portalCotizacion(config) {
            return {
                csrf: config.csrfToken,
                urlAutorizar: config.urlAutorizar,
                urlRechazar: config.urlRechazar,
                procesando: false,
                modalRechazo: false,
                motivoRechazo: '',

                canvas: null,
                ctx: null,
                dibujando: false,
                trazosRealizados: false,

                init() {
                    this.canvas = document.getElementById('signatureCanvas');
                    if (this.canvas) {
                        this.ctx = this.canvas.getContext('2d');
                        this.ctx.strokeStyle = '#ffffff';
                        this.ctx.lineWidth = 2.5;
                        this.ctx.lineCap = 'round';
                        this.ctx.lineJoin = 'round';
                        this.iniciarEventosCanvas();
                    }
                },

                iniciarEventosCanvas() {
                    const c = this.canvas;

                    const getPos = (e) => {
                        const rect = c.getBoundingClientRect();
                        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                        return {
                            x: (clientX - rect.left) * (c.width / rect.width),
                            y: (clientY - rect.top) * (c.height / rect.height)
                        };
                    };

                    const start = (e) => {
                        e.preventDefault();
                        this.dibujando = true;
                        this.trazosRealizados = true;
                        const pos = getPos(e);
                        this.ctx.beginPath();
                        this.ctx.moveTo(pos.x, pos.y);
                    };

                    const draw = (e) => {
                        if (!this.dibujando) return;
                        e.preventDefault();
                        const pos = getPos(e);
                        this.ctx.lineTo(pos.x, pos.y);
                        this.ctx.stroke();
                    };

                    const stop = () => {
                        this.dibujando = false;
                    };

                    c.addEventListener('mousedown', start);
                    c.addEventListener('mousemove', draw);
                    window.addEventListener('mouseup', stop);

                    c.addEventListener('touchstart', start, { passive: false });
                    c.addEventListener('touchmove', draw, { passive: false });
                    window.addEventListener('touchend', stop);
                },

                limpiarFirma() {
                    if (this.ctx && this.canvas) {
                        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                        this.trazosRealizados = false;
                    }
                },

                async autorizarCotizacion() {
                    if (!this.trazosRealizados) {
                        alert('Por favor estampe su firma digital en el lienzo antes de continuar.');
                        return;
                    }

                    if (!confirm('¿Confirma la autorización de esta reparación con su firma digital?')) {
                        return;
                    }

                    this.procesando = true;
                    const firmaBase64 = this.canvas.toDataURL('image/png');

                    try {
                        const res = await fetch(this.urlAutorizar, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ firma_base64: firmaBase64 })
                        });

                        const data = await res.json();
                        if (res.ok) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error al procesar la firma.');
                        }
                    } catch (e) {
                        alert('Error de conexión.');
                    } finally {
                        this.procesando = false;
                    }
                },

                async rechazarCotizacion() {
                    this.procesando = true;
                    try {
                        const res = await fetch(this.urlRechazar, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ motivo_rechazo: this.motivoRechazo })
                        });

                        const data = await res.json();
                        if (res.ok) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error al rechazar cotización.');
                        }
                    } catch (e) {
                        alert('Error de conexión.');
                    } finally {
                        this.procesando = false;
                    }
                }
            };
        }
    </script>
</body>
</html>