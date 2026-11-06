<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $venta->folio }}</title>
    <style>
        @page {
            margin: 0;
            size: 80mm auto;
        }
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
        .no-print {
            margin-bottom: 10px;
            padding: 6px;
            background: #eee;
            text-align: center;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print">
        <button onclick="window.print()">Imprimir Ticket</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    <div class="text-center">
        <div class="bold" style="font-size: 14px;">{{ $venta->empresa->nombre ?? 'CANO COMPUTADORAS' }}</div>
        <div>Sucursal: {{ $venta->sucursal->nombre ?? 'Matriz' }}</div>
        <div>RFC: {{ $venta->empresa->rfc ?? 'XAXX010101000' }}</div>
        <div class="divider"></div>
        <div class="bold">FOLIO: {{ $venta->folio }}</div>
        <div>Fecha: {{ $venta->created_at->format('d/m/Y H:i:s') }}</div>
        <div>Atendió: {{ $venta->user->name ?? 'Cajero' }}</div>
        <div>Cliente: {{ $venta->cliente_nombre }}</div>
        <div class="divider"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th align="left">Cant.</th>
                <th align="left">Desc.</th>
                <th align="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $det)
                <tr>
                    <td valign="top">{{ number_format((float) $det->cantidad, 0) }}</td>
                    <td valign="top">{{ Str::limit($det->descripcion_producto, 20) }}</td>
                    <td align="right" valign="top">${{ number_format((float) $det->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table>
        <tr>
            <td>Subtotal:</td>
            <td align="right">${{ number_format((float) $venta->subtotal_sin_impuestos, 2) }}</td>
        </tr>
        <tr>
            <td>IVA (16%):</td>
            <td align="right">${{ number_format((float) $venta->iva_total, 2) }}</td>
        </tr>
        <tr class="bold" style="font-size: 13px;">
            <td>TOTAL:</td>
            <td align="right">${{ number_format((float) $venta->total, 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <div>
        @foreach($venta->pagos as $pago)
            <div>Pago: {{ $pago->forma_pago_sat == '01' ? 'Efectivo' : ($pago->forma_pago_sat == '04' ? 'Tarjeta' : 'Transf.') }}</div>
            <div>Monto Recibido: ${{ number_format((float) $pago->monto_recibido, 2) }}</div>
            <div>Cambio: ${{ number_format((float) $pago->cambio_devuelto, 2) }}</div>
        @endforeach
    </div>

    <div class="divider"></div>

    <div class="text-center" style="font-size: 10px;">
        <div>¡Gracias por su compra!</div>
        <div>Comprobante simplificado para efectos fiscales locales.</div>
    </div>

</body>
</html>
