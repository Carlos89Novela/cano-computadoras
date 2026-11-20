<!DOCTYPE html>
<html lang="es">
<head>
    {{-- ===================================================================== --}}
    {{-- SECCIÓN 1: ESTILOS TÉRMICOS DE CORTE DE CAJA (CORTE X / CORTE Z)    --}}
    {{-- ===================================================================== --}}
    <meta charset="UTF-8">
    <title>Corte de Caja {{ $tipoCorte }} - Turno #{{ $turno->id }}</title>
    <style>
        @page { margin: 4mm; }
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 72mm;
            margin: 0 auto;
            font-size: 11px;
            color: #000;
            line-height: 1.35;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        .double-line { border-top: 2px solid #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .badge {
            display: inline-block;
            border: 1px solid #000;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 12px;
            margin: 4px 0;
        }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 2: ENCABEZADO DEL TURNO Y TIPO DE CORTE (X o Z)             --}}
    {{-- ===================================================================== --}}
    <div class="center">
        <div class="bold" style="font-size: 13px;">{{ $turno->sucursal?->empresa?->nombre ?? 'LA FOGATA BISTRO' }}</div>
        <div>Sucursal: {{ $turno->sucursal?->nombre ?? 'Centro' }}</div>
        <div class="badge">
            {{ $tipoCorte === 'Z' ? 'CORTE FINAL DE TURNO (Z)' : 'CORTE PARCIAL / ARQUEO (X)' }}
        </div>
        <div>Turno #{{ $turno->id }} • Cajero: {{ $turno->user?->name }}</div>
        <div>Apertura: {{ $turno->apertura_at?->format('d/m/Y H:i') }}</div>
        @if ($turno->cierre_at)
            <div>Cierre: {{ $turno->cierre_at?->format('d/m/Y H:i') }}</div>
        @endif
        <div>Emisión: {{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <div class="double-line"></div>

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 3: 1. RESUMEN DE VENTAS POR FORMA DE PAGO SAT               --}}
    {{-- ===================================================================== --}}
    <div class="bold">1. RESUMEN DE VENTAS ({{ $desglose['cantidad_tickets'] }} tickets)</div>
    <table>
        <tr>
            <td>Efectivo (01):</td>
            <td class="right bold">${{ number_format($desglose['ventas_efectivo'], 2) }}</td>
        </tr>
        <tr>
            <td>Tarjetas (04/28):</td>
            <td class="right">${{ number_format($desglose['ventas_tarjeta'], 2) }}</td>
        </tr>
        <tr>
            <td>Transferencia (03):</td>
            <td class="right">${{ number_format($desglose['ventas_transferencia'], 2) }}</td>
        </tr>
        @if ($desglose['ventas_otros'] > 0)
            <tr>
                <td>Otros / Monedero:</td>
                <td class="right">${{ number_format($desglose['ventas_otros'], 2) }}</td>
            </tr>
        @endif
        <tr style="border-top: 1px solid #000;">
            <td class="bold">TOTAL VENDIDO:</td>
            <td class="right bold" style="font-size: 12px;">${{ number_format($desglose['total_vendido'], 2) }}</td>
        </tr>
    </table>

    <div class="line"></div>

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 4: 2. ARQUEO DE EFECTIVO EN GAVETA (CORTE X Y CORTE Z)       --}}
    {{-- ===================================================================== --}}
    @php
        $efectivoEsperado = (float) ($desglose['efectivo_esperado'] ?? 0);

        // En Corte X lee el parámetro ?contado=...; en Corte Z usa el monto real del turno o del desglose
        $efectivoContado = isset($desglose['efectivo_contado'])
            ? (float) $desglose['efectivo_contado']
            : ($tipoCorte === 'X' && request()->has('contado')
                ? (float) request()->query('contado', 0)
                : ($turno->monto_real_efectivo !== null ? (float) $turno->monto_real_efectivo : null));

        // Calcular diferencia (contado - esperado) cuando exista efectivo contado
        $diferenciaCaja = isset($desglose['diferencia'])
            ? (float) $desglose['diferencia']
            : ($tipoCorte === 'X' && request()->has('diferencia')
                ? (float) request()->query('diferencia', 0)
                : ($efectivoContado !== null ? round($efectivoContado - $efectivoEsperado, 2) : null));
    @endphp

    <div class="bold">2. ARQUEO DE EFECTIVO EN GAVETA</div>
    <table>
        <tr>
            <td>(+) Fondo Inicial:</td>
            <td class="right">${{ number_format($desglose['fondo_inicial'], 2) }}</td>
        </tr>
        <tr>
            <td>(+) Ventas en Efectivo:</td>
            <td class="right">${{ number_format($desglose['ventas_efectivo'], 2) }}</td>
        </tr>
        <tr>
            <td>(+) Otros Ingresos:</td>
            <td class="right">${{ number_format($desglose['total_ingresos'], 2) }}</td>
        </tr>
        <tr>
            <td>(-) Retiros / Gastos:</td>
            <td class="right">-${{ number_format($desglose['total_retiros'], 2) }}</td>
        </tr>
        <tr style="border-top: 1px dashed #000;">
            <td class="bold">EFECTIVO ESPERADO:</td>
            <td class="right bold">${{ number_format($efectivoEsperado, 2) }}</td>
        </tr>

        @if ($efectivoContado !== null)
            <tr>
                <td>Efectivo Contado:</td>
                <td class="right">${{ number_format($efectivoContado, 2) }}</td>
            </tr>
            <tr>
                <td class="bold">Diferencia:</td>
                <td class="right bold">${{ number_format($diferenciaCaja, 2) }}</td>
            </tr>
        @endif
    </table>

    {{-- ===================================================================== --}}
    {{-- DESGLOSE DE MONEDAS Y BILLETES CONTADOS EN CORTE X                    --}}
    {{-- ===================================================================== --}}
    @php
        $rawDenominaciones = request()->query('denominaciones');
        $mapaDenominaciones = [];

        if (is_string($rawDenominaciones) && $rawDenominaciones !== '') {
            $decodificado = json_decode($rawDenominaciones, true);
            if (is_array($decodificado)) {
                $mapaDenominaciones = $decodificado;
            }
        } elseif (is_array($rawDenominaciones)) {
            $mapaDenominaciones = $rawDenominaciones;
        }

        // Orden de denominaciones de mayor a menor
        $catalogoOrdenado = [
            1000 => '$1,000',
            500  => '$500',
            200  => '$200',
            100  => '$100',
            50   => '$50',
            20   => '$20',
            10   => '$10.00',
            5    => '$5.00',
            2    => '$2.00',
            1    => '$1.00',
            0.5  => '$0.50',
        ];

        $lineasDenominacion = [];
        foreach ($catalogoOrdenado as $valorDenom => $etiquetaDenom) {
            $claveStr = (string) $valorDenom;
            $cantDenom = isset($mapaDenominaciones[$claveStr])
                ? (int) $mapaDenominaciones[$claveStr]
                : (isset($mapaDenominaciones[$valorDenom]) ? (int) $mapaDenominaciones[$valorDenom] : 0);

            if ($cantDenom > 0) {
                $lineasDenominacion[] = [
                    'etiqueta' => $etiquetaDenom,
                    'cantidad' => $cantDenom,
                    'subtotal' => round($cantDenom * (float) $valorDenom, 2),
                ];
            }
        }
    @endphp

    @if ($tipoCorte === 'X' && ! empty($lineasDenominacion))
        <div class="line"></div>
        <div class="bold">DESGLOSE DE DENOMINACIONES</div>
        <table>
            @foreach ($lineasDenominacion as $itemDenom)
                <tr>
                    <td>{{ $itemDenom['cantidad'] }}x {{ $itemDenom['etiqueta'] }}</td>
                    <td class="right">${{ number_format($itemDenom['subtotal'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 5: 3. DETALLE DE RETIROS E INGRESOS (SI EXISTEN)              --}}
    {{-- ===================================================================== --}}
    @if ($desglose['movimientos']->isNotEmpty())
        <div class="line"></div>
        <div class="bold">3. DETALLE DE RETIROS E INGRESOS</div>
        <table>
            @foreach ($desglose['movimientos'] as $mov)
                <tr>
                    <td>{{ $mov->tipo === 'retiro' ? '[-]' : '[+]' }} {{ $mov->concepto }}</td>
                    <td class="right">${{ number_format((float)$mov->monto, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 6: 4. PRODUCTOS VENDIDOS EN EL TURNO (CORTE Z)                --}}
    {{-- ===================================================================== --}}
    @if ($tipoCorte === 'Z' && ! empty($desglose['productos_vendidos']))
        <div class="line"></div>
        <div class="bold">4. PRODUCTOS VENDIDOS EN EL TURNO</div>
        <table>
            @foreach ($desglose['productos_vendidos'] as $prod)
                <tr>
                    <td>{{ rtrim(rtrim(number_format($prod['cantidad'], 2), '0'), '.') }}x {{ $prod['nombre'] }}</td>
                    <td class="right">${{ number_format($prod['total'], 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($turno->observaciones_cierre)
        <div class="line"></div>
        <div><span class="bold">Notas:</span> {{ $turno->observaciones_cierre }}</div>
    @endif

    <div class="double-line"></div>

    {{-- ===================================================================== --}}
    {{-- SECCIÓN 7: FIRMAS O LEYENDA DE CORTE PARCIAL                          --}}
    {{-- ===================================================================== --}}
    @if ($tipoCorte === 'Z')
        <div class="center" style="margin-top: 24px;">
            ___________________________<br>
            Firma Cajero ({{ $turno->user?->name }})
            <br><br><br>
            ___________________________<br>
            Firma Supervisor / Gerente
        </div>
    @else
        <div class="center">
            *** COMPROBANTE DE ARQUEO PARCIAL ***<br>
            EL TURNO PERMANECE ABIERTO
        </div>
    @endif

    <div class="center no-print" style="margin-top: 15px;">
        <button onclick="window.print()" style="padding: 6px 14px; font-weight: bold; cursor: pointer;">🖨️ Reimprimir</button>
    </div>
</body>
</html>
