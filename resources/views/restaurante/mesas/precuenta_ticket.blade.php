<!DOCTYPE html>
<html lang="es">
<head>
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

    <div class="no-print">
        <button onclick="window.print()">Imprimir Precuenta</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

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

    <table>
        <thead>
            <tr>
                <th align="left">Cant.</th>
                <th align="left">Descripción</th>
                <th align="right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comanda->items as $item)
                @php $subItem = ((float)$item->cantidad) * ((float)$item->producto->precio_base); @endphp
                <tr>
                    <td valign="top">{{ number_format((float) $item->cantidad, 0) }}</td>
                    <td valign="top">{{ Str::limit($item->producto->nombre, 18) }}</td>
                    <td align="right" valign="top">${{ number_format($subItem, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table>
        <tr class="bold" style="font-size: 13px;">
            <td>TOTAL CONSUMO:</td>
            <td align="right">${{ number_format($totalConsumo, 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <div style="font-size: 10px;">
        <div class="bold text-center mb-1">PROPINAS SUGERIDAS (VOLUNTARIAS):</div>
        <div style="display: flex; justify-content: space-between;">
            <span>10%: ${{ number_format($totalConsumo * 0.10, 2) }}</span>
            <span>15%: ${{ number_format($totalConsumo * 0.15, 2) }}</span>
            <span>20%: ${{ number_format($totalConsumo * 0.20, 2) }}</span>
        </div>
    </div>

    <div class="divider"></div>

    {{-- Código QR de Autofacturación para el Comensal --}}
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