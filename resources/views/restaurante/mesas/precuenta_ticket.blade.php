<!DOCTYPE html>
<html lang="es">
<head>
    {{-- ========================================================================= --}}
    {{-- SECCIÓN 1: METADATOS Y ESTILOS TÉRMICOS DE 80MM                           --}}
    {{-- ========================================================================= --}}
    <meta charset="UTF-8">
    <title>Precuenta {{ $mesa->numero_o_nombre }}</title>
    <style>
        @page { margin: 0; size: 80mm auto; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 8px;
            width: 72mm;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { padding: 2px 0; }
        .no-print { margin-bottom: 10px; padding: 6px; background: #eee; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 2: CONTROLES DE IMPRESIÓN (OCULTOS EN PAPEL)                      --}}
    {{-- ========================================================================= --}}
    <div class="no-print">
        <button onclick="window.print()">Imprimir Precuenta</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 3: ENCABEZADO DEL ESTABLECIMIENTO Y DATOS DE LA COMANDA           --}}
    {{-- ========================================================================= --}}
    <div class="text-center">
        <div class="bold" style="font-size: 14px;">{{ $comanda->empresa->nombre }}</div>
        <div>Sucursal: {{ $comanda->sucursal->nombre }}</div>
        <div class="divider"></div>
        <div class="bold" style="font-size: 13px;">ESTADO DE CUENTA (PRECUENTA)</div>
        <div class="bold">{{ $mesa->numero_o_nombre }}</div>
        <div>Comanda: {{ $comanda->folio }}</div>
        <div>Mesero: {{ $comanda->mesero->name ?? 'Caja' }}</div>
        <div>Hora: {{ $comanda->created_at->format('H:i:s d/m/Y') }}</div>
        <div class="divider"></div>
    </div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 4: DETALLE CONSOLIDADO DE PLATILLOS Y BEBIDAS CONSUMIDOS          --}}
    {{-- ========================================================================= --}}
    <table>
        <thead>
            <tr>
                <th align="left">Cant.</th>
                <th align="left">Descripción</th>
                <th align="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @php $listaPartidas = $itemsConsolidados ?? $comanda->items; @endphp
            @foreach ($listaPartidas as $item)
                @php
                    $precioUnit = (float) ($item->producto?->precio_base ?? 0);
                    $subItem = ((float) $item->cantidad) * $precioUnit;
                @endphp
                <tr>
                    <td valign="top">{{ number_format((float) $item->cantidad, 0) }}</td>
                    <td valign="top">
                        {{ \Illuminate\Support\Str::limit($item->producto?->nombre ?? 'Platillo', 18) }}
                        @if($item->notas_especiales)
                            <br><span style="font-size: 9px; color: #555;">{{ $item->notas_especiales }}</span>
                        @endif
                    </td>
                    <td align="right" valign="top">${{ number_format($subItem, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 5: SUBTOTAL, PROMOCIONES / COMBOS APLICADOS Y TOTAL NETO          --}}
    {{-- ========================================================================= --}}
    <table>
        @if (! empty($promocionesAplicadas))
            <tr>
                <td>Subtotal Consumo:</td>
                <td align="right">${{ number_format($totalBruto ?? $totalConsumo, 2) }}</td>
            </tr>
            @foreach ($promocionesAplicadas as $promo)
                <tr class="bold">
                    <td>🎁 {{ \Illuminate\Support\Str::limit($promo['nombre'], 22) }}:</td>
                    <td align="right">-${{ number_format((float) $promo['ahorro'], 2) }}</td>
                </tr>
            @endforeach
        @endif

        <tr class="bold" style="font-size: 13px;">
            <td>TOTAL CONSUMO:</td>
            <td align="right">${{ number_format($totalConsumo, 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 6: PROPINAS SUGERIDAS (CALCULADAS SOBRE EL TOTAL NETO)            --}}
    {{-- ========================================================================= --}}
    <div style="font-size: 10px;">
        <div class="bold text-center mb-1">PROPINAS SUGERIDAS (VOLUNTARIAS):</div>
        <div style="display: flex; justify-content: space-between;">
            <span>10%: ${{ number_format($totalConsumo * 0.10, 2) }}</span>
            <span>15%: ${{ number_format($totalConsumo * 0.15, 2) }}</span>
            <span>20%: ${{ number_format($totalConsumo * 0.20, 2) }}</span>
        </div>
    </div>

    <div class="divider"></div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 7: CÓDIGO QR DE AUTOFACTURACIÓN PARA EL COMENSAL                  --}}
    {{-- ========================================================================= --}}
    <div class="text-center" style="margin-top: 8px;">
        <div class="bold" style="font-size: 10px;">¿REQUIERE FACTURA FISCAL?</div>
        <div style="font-size: 9px; margin-bottom: 5px;">Escanee el código QR desde su celular:</div>

        <div style="display: flex; justify-content: center; margin: 4px 0;">
            {!! QrCode::size(110)->generate(route('autofactura.show', $tokenFactura)) !!}
        </div>

        <div style="font-size: 9px;">Código web: <strong>{{ $tokenFactura }}</strong></div>
        <div style="font-size: 8px; margin-top: 4px;">Comprobante informativo no válido para deducibilidad fiscal.</div>
    </div>

</body>
</html>