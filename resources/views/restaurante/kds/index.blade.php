<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KDS Cocina | {{ $empresa->nombre }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .grid-kds {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        @keyframes pulse-border {
            0%, 100% { border-color: #ef4444; box-shadow: 0 0 15px rgba(239, 68, 68, 0.4); }
            50% { border-color: #7f1d1d; box-shadow: none; }
        }
        .alerta-demora { animation: pulse-border 1.5s infinite; }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 font-sans antialiased overflow-hidden m-0 p-0 flex flex-col">

    <div class="h-full flex flex-col p-4 box-border">

        {{-- Encabezado KDS --}}
        <header class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-lg">
                    👨‍🍳
                </div>
                <div>
                    <h1 class="text-base font-bold flex items-center gap-2">
                        <span>Monitor KDS</span>
                        <span id="reloj-kds" class="text-xs font-mono px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-300">00:00:00</span>
                    </h1>
                    <p class="text-xs text-slate-400">{{ $empresa->nombre }} | {{ $sucursal?->nombre ?? 'Cocina Principal' }}</p>
                </div>
            </div>

            {{-- Filtros de Estación --}}
            <div class="flex items-center gap-2">
                <div class="flex bg-slate-900 p-1 rounded-xl border border-slate-800">
                    <button onclick="cambiarEstacion('todas', this)" class="btn-filtro px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white cursor-pointer">
                        Todas
                    </button>
                    <button onclick="cambiarEstacion('cocina', this)" class="btn-filtro px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🍳 Cocina
                    </button>
                    <button onclick="cambiarEstacion('parrilla', this)" class="btn-filtro px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🔥 Parrilla
                    </button>
                    <button onclick="cambiarEstacion('barra', this)" class="btn-filtro px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🍹 Barra
                    </button>
                    <button onclick="cambiarEstacion('postres', this)" class="btn-filtro px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer">
                        🍰 Postres
                    </button>
                </div>

                <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-slate-400 text-xs rounded-xl transition border border-slate-800">
                    Salir
                </a>
            </div>
        </header>

        {{-- Toast Banner de Nueva Comanda Entrante en Cocina --}}
        <div id="toast-nueva-comanda" class="hidden fixed top-5 left-1/2 -translate-x-1/2 z-50 bg-indigo-600 text-white border-2 border-indigo-300 px-6 py-3 rounded-2xl shadow-2xl flex items-center gap-3 animate-bounce">
            <span class="text-2xl">🔔</span>
            <div>
                <h4 class="text-xs font-black uppercase tracking-wider">¡Nueva Comanda Entrante!</h4>
                <p class="text-xs font-bold" id="toast-comanda-desc">Mesa X - 3 Platillos</p>
            </div>
        </div>

        {{-- Tablero de Comandas Activas --}}
        <div class="flex-1 overflow-y-auto pr-1">
            <div id="grid-comandas" class="grid-kds">
                <div class="col-span-full py-20 text-center text-slate-500 text-xs">
                    Cargando comandas activas...
                </div>
            </div>
        </div>

    </div>

    <script>
        const config = {
            csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            urlActivas: "{{ route('restaurante.kds.comandas-activas') }}",
            urlItemEstado: "{{ url('/restaurante/kds/items') }}",
            urlCompletar: "{{ url('/restaurante/kds/comandas') }}",
            sucursalId: {{ $sucursal?->id ?? 1 }}
        };

        let estacionActual = 'todas';
        let idsComandasConocidas = new Set();
        let primeraCarga = true;

        // Reloj en vivo
        setInterval(() => {
            const ahora = new Date();
            document.getElementById('reloj-kds').innerText = ahora.toLocaleTimeString();
        }, 1000);

        // Timbre de pase para cocina (Doble tono de alerta de comanda entrante)
        function sonarTimbreCocina() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                const ctx = new AudioContext();

                const sonarTono = (frec, inicio, dur) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(frec, inicio);
                    gain.gain.setValueAtTime(0.4, inicio);
                    gain.gain.exponentialRampToValueAtTime(0.001, inicio + dur);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(inicio);
                    osc.stop(inicio + dur);
                };

                const t = ctx.currentTime;
                sonarTono(880, t, 0.25);
                sonarTono(1174.66, t + 0.15, 0.35);
                sonarTono(1760, t + 0.35, 0.6);
            } catch (e) {
                console.log('Audio no interactuado aún');
            }
        }

        function cambiarEstacion(estacion, btn) {
            estacionActual = estacion;
            document.querySelectorAll('.btn-filtro').forEach(b => {
                b.className = 'btn-filtro px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white cursor-pointer';
            });
            btn.className = 'btn-filtro px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white cursor-pointer';
            cargarComandas();
        }

        async function cargarComandas() {
            try {
                const res = await fetch(`${config.urlActivas}?sucursal_id=${config.sucursalId}&estacion=${estacionActual}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) return;

                const comandas = await res.json();
                const grid = document.getElementById('grid-comandas');

                // Detección de comandas nuevas para timbrar
                let hayNueva = false;
                let ultimaComandaNueva = null;

                comandas.forEach(c => {
                    if (!idsComandasConocidas.has(c.id)) {
                        idsComandasConocidas.add(c.id);
                        if (!primeraCarga) {
                            hayNueva = true;
                            ultimaComandaNueva = c;
                        }
                    }
                });

                if (hayNueva && ultimaComandaNueva) {
                    sonarTimbreCocina();
                    mostrarToastNuevaComanda(ultimaComandaNueva);
                }

                primeraCarga = false;

                if (comandas.length === 0) {
                    grid.innerHTML = `
                        <div class="col-span-full py-24 text-center text-slate-500 text-xs">
                            <span class="text-3xl block mb-2">🍽️</span>
                            <span>No hay órdenes pendientes en esta estación.</span>
                        </div>
                    `;
                    return;
                }

                let html = '';
                const ahora = Math.floor(Date.now() / 1000);

                comandas.forEach(c => {
                    const tiempoEnviado = Math.floor(new Date(c.enviada_a_cocina_at).getTime() / 1000);
                    const minutosTranscurridos = Math.floor((ahora - tiempoEnviado) / 60);

                    // Semáforo de demoras
                    let semaforoClase = 'border-slate-800';
                    let badgeMinutos = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';

                    if (minutosTranscurridos >= 20) {
                        semaforoClase = 'alerta-demora border-rose-500';
                        badgeMinutos = 'bg-rose-500 text-white font-black';
                    } else if (minutosTranscurridos >= 10) {
                        semaforoClase = 'border-amber-500/80';
                        badgeMinutos = 'bg-amber-500/20 text-amber-300 border-amber-500/40';
                    }

                    html += `
                        <div class="bg-slate-900 border-2 ${semaforoClase} rounded-2xl flex flex-col justify-between shadow-xl overflow-hidden">
                            {{-- Cabecera Tarjeta --}}
                            <div class="p-3 bg-slate-950/80 border-b border-slate-800/80 flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-black text-white flex items-center gap-1.5">
                                        <span>🪑 ${c.mesa_o_referencia}</span>
                                    </h3>
                                    <span class="text-[10px] text-slate-400 font-mono">${c.folio}</span>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded-full border ${badgeMinutos}">
                                    ⏱️ ${minutosTranscurridos} min
                                </span>
                            </div>

                            {{-- Lista de Platillos --}}
                            <div class="p-3 space-y-2 flex-1 overflow-y-auto">
                    `;

                    c.items.forEach(item => {
                        const esListo = (item.estado === 'listo');
                        const textoTachado = esListo ? 'line-through text-slate-500' : 'text-slate-100 font-bold';
                        const badgeItem = esListo
                            ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                            : 'bg-slate-800 text-slate-300 border-slate-700';

                        html += `
                            <div onclick="toggleItemListo(${item.id}, '${item.estado}')"
                                 class="p-2 bg-slate-950/60 border border-slate-800/80 rounded-xl cursor-pointer hover:border-slate-600 transition flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-black text-amber-400">${parseFloat(item.cantidad)}x</span>
                                        <h4 class="text-xs ${textoTachado}">${item.producto ? item.producto.nombre : 'Platillo'}</h4>
                                    </div>
                                    ${item.notas_especiales ? `
                                        <p class="text-[10px] text-amber-300 mt-0.5 font-medium">👉 ${item.notas_especiales}</p>
                                    ` : ''}
                                </div>
                                <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded border ${badgeItem}">
                                    ${esListo ? '✓ Listo' : 'Pendiente'}
                                </span>
                            </div>
                        `;
                    });

                    html += `
                            </div>

                            {{-- Botón Despachar Comanda --}}
                            <div class="p-3 bg-slate-950/80 border-t border-slate-800/80">
                                <button onclick="despacharComandaCompleta(${c.id})"
                                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5 shadow-lg shadow-emerald-950/40 cursor-pointer">
                                    <span>✓</span> Despachar Comanda
                                </button>
                            </div>
                        </div>
                    `;
                });

                grid.innerHTML = html;
            } catch (e) {
                console.error('Error al actualizar KDS:', e);
            }
        }

        async function toggleItemListo(itemId, estadoActual) {
            const nuevoEstado = (estadoActual === 'listo') ? 'pendiente' : 'listo';
            try {
                const res = await fetch(`${config.urlItemEstado}/${itemId}/estado`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ estado: nuevoEstado })
                });
                if (res.ok) cargarComandas();
            } catch (e) {
                console.error(e);
            }
        }

        async function despacharComandaCompleta(comandaId) {
            try {
                const res = await fetch(`${config.urlCompletar}/${comandaId}/completar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        estacion: estacionActual
                    })
                });
                if (res.ok) cargarComandas();
            } catch (e) {
                console.error(e);
            }
        }

        function mostrarToastNuevaComanda(comanda) {
            const toast = document.getElementById('toast-nueva-comanda');
            const desc = document.getElementById('toast-comanda-desc');
            const totalItems = comanda.items ? comanda.items.length : 1;
            desc.innerText = `${comanda.mesa_o_referencia} • ${totalItems} platillo(s)`;
            toast.classList.remove('hidden');

            setTimeout(() => {
                toast.classList.add('hidden');
            }, 6000);
        }

        // Carga inicial y refresco cada 4 segundos
        cargarComandas();
        setInterval(cargarComandas, 4000);
    </script>
</body>
</html>
