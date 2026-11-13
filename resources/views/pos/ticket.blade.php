<!DOCTYPE html>
<html lang="es">
<head>
    {{-- ========================================================================= --}}
    {{-- SECCIÓN 1: METADATOS Y ESTILOS TÉRMICOS DE 80MM                           --}}
    {{-- ========================================================================= --}}
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

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 2: CONTROLES DE IMPRESIÓN (OCULTOS EN PAPEL)                      --}}
    {{-- ========================================================================= --}}
    <div class="no-print">
        <button onclick="window.print()">Imprimir Ticket</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 3: ENCABEZADO FISCAL Y DATOS DE LA VENTA                          --}}
    {{-- ========================================================================= --}}
    <div class="text-center">
        <div class="bold" style="font-size: 14px;">{{ $venta->empresa->nombre ?? 'LA FOGATA BISTRO' }}</div>
        <div>Sucursal: {{ $venta->sucursal->nombre ?? 'Sucursal Centro' }}</div>
        <div>RFC: {{ $venta->empresa->rfc ?? 'XAXX010101000' }}</div>
        <div class="divider"></div>
        <div class="bold">FOLIO: {{ $venta->folio }}</div>
        <div>Fecha: {{ $venta->created_at->format('d/m/Y H:i:s') }}</div>
        <div>Atendió: {{ $venta->user->name ?? 'Cajero' }}</div>
        <div>Cliente: {{ $venta->cliente_nombre }}</div>
        <div class="divider"></div>
    </div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 4: DETALLE DE PRODUCTOS / PLATILLOS COBRADOS                      --}}
    {{-- ========================================================================= --}}
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
                    <td valign="top">{{ \Illuminate\Support\Str::limit($det->descripcion_producto, 20) }}</td>
                    <td align="right" valign="top">${{ number_format((float) $det->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 5: DESGLOSE DE PROMOCIONES, COMBOS Y CANJE DE MONEDERO            --}}
    {{-- ========================================================================= --}}
    @php
        $notasStr = (string) ($venta->notas ?? '');
        $itemsPromosTicket = [];
        $descuentoPromos = 0.0;
        $monederoUsado = 0.0;

        // 1. Leer etiquetas compactas [P:id:ahorro]
        if (preg_match_all('/\[P:(\d+):([0-9.]+)\]/', $notasStr, $matchesP, PREG_SET_ORDER)) {
            foreach ($matchesP as $mp) {
                $promoModel = \App\Models\Promocion::find((int) $mp[1]);
                $ahorro = (float) $mp[2];
                $itemsPromosTicket[] = [
                    'nombre' => $promoModel?->nombre ?? 'Promoción / Combo',
                    'ahorro' => $ahorro,
                ];
                $descuentoPromos += $ahorro;
            }
        }

        // 2. Leer canje de monedero [M:monto]
        if (preg_match('/\[M:([0-9.]+)\]/', $notasStr, $matchM)) {
            $monederoUsado = (float) $matchM[1];
        }

        // 3. Respaldo automático: comparar suma bruta de partidas contra lo realmente pagado
        $sumaBrutaPartidas = (float) $venta->detalles->sum('total');
        $totalPagos = (float) $venta->pagos->sum('monto');
        $totalCobradoFinal = $totalPagos > 0 ? $totalPagos : (float) $venta->total;

        if (empty($itemsPromosTicket) && ($sumaBrutaPartidas - $totalCobradoFinal - $monederoUsado) > 0.01) {
            $ahorroDetectado = round($sumaBrutaPartidas - $totalCobradoFinal - $monederoUsado, 2);
            $itemsPromosTicket[] = [
                'nombre' => 'Descuento Promoción / Combo',
                'ahorro' => $ahorroDetectado,
            ];
            $descuentoPromos = $ahorroDetectado;
        }

        $subtotalNeto = $totalCobradoFinal / 1.16;
        $ivaNeto = $totalCobradoFinal - $subtotalNeto;
    @endphp

    @if (! empty($itemsPromosTicket) || $monederoUsado > 0)
        <div class="divider"></div>
        <div class="bold" style="font-size: 11px;">PROMOCIONES Y BENEFICIOS APLICADOS:</div>
        <table>
            <tr>
                <td>Importe Bruto:</td>
                <td align="right">${{ number_format($sumaBrutaPartidas, 2) }}</td>
            </tr>
            @foreach ($itemsPromosTicket as $pItem)
                <tr class="bold">
                    <td>🎁 {{ \Illuminate\Support\Str::limit($pItem['nombre'], 22) }}:</td>
                    <td align="right">-${{ number_format((float) $pItem['ahorro'], 2) }}</td>
                </tr>
            @endforeach
            @if ($monederoUsado > 0)
                <tr class="bold">
                    <td>💳 Canje Monedero:</td>
                    <td align="right">-${{ number_format($monederoUsado, 2) }}</td>
                </tr>
            @endif
        </table>
    @endif

    <div class="divider"></div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 6: SUBTOTAL, IVA Y TOTAL NETO COBRADO                             --}}
    {{-- ========================================================================= --}}
    <table>
        <tr>
            <td>Subtotal:</td>
            <td align="right">${{ number_format($subtotalNeto, 2) }}</td>
        </tr>
        <tr>
            <td>IVA (16%):</td>
            <td align="right">${{ number_format($ivaNeto, 2) }}</td>
        </tr>
        <tr class="bold" style="font-size: 13px;">
            <td>TOTAL:</td>
            <td align="right">${{ number_format($totalCobradoFinal, 2) }}</td>
        </tr>
    </table>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 7: DESGLOSE DE FORMAS DE PAGO Y CAMBIO ENTREGADO                  --}}
    {{-- ========================================================================= --}}
    <div>
        @foreach($venta->pagos as $pago)
            @php
                $etiquetaPago = match($pago->forma_pago_sat) {
                    '01' => 'Efectivo',
                    '04' => 'Tarjeta Crédito',
                    '28' => 'Tarjeta Débito',
                    '03' => 'Transferencia',
                    default => 'Otro (' . $pago->forma_pago_sat . ')',
                };
            @endphp
            <div>Pago: {{ $etiquetaPago }} (${{ number_format((float) $pago->monto, 2) }})</div>
            @if ($pago->forma_pago_sat == '01')
                <div>Monto Recibido: ${{ number_format((float) $pago->monto_recibido, 2) }}</div>
                <div>Cambio: ${{ number_format((float) $pago->cambio_devuelto, 2) }}</div>
            @elseif (! empty($pago->referencia_bancaria))
                <div>Ref.: {{ $pago->referencia_bancaria }}</div>
            @endif
        @endforeach
    </div>

    <div class="divider"></div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 8: BLOQUE DE AUTOFACTURACIÓN SAT CFDI 4.0                         --}}
    {{-- ========================================================================= --}}
    @php
        // Asegurar que la venta cuente con su token para el código QR
        if (empty($venta->token_autofactura)) {
            $nuevoToken = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(32));
            $venta->update([
                'token_autofactura' => $nuevoToken,
                'autofactura_limite_at' => now()->addDays(3),
            ]);
            $venta->refresh();
        }
        $urlFactura = route('autofactura.show', $venta->token_autofactura);
    @endphp

    <div class="text-center" style="margin-top: 6px;">
        <div class="bold" style="font-size: 11px;">¿REQUIERE FACTURA FISCAL?</div>
        <div style="font-size: 9px; margin-top: 2px; margin-bottom: 4px;">
            Escanee el siguiente código QR con su celular o ingrese a la dirección web:
        </div>

        <div style="display: flex; justify-content: center; margin: 4px 0;">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&margin=2&data={{ urlencode($urlFactura) }}"
                 alt="QR Autofacturación"
                 style="width: 120px; height: 120px; display: block; margin: 0 auto;">
        </div>

        <div style="font-size: 9px; margin-top: 4px;">
            Código: <strong style="font-family: monospace;">{{ substr($venta->token_autofactura, 0, 10) }}...</strong>
        </div>
        <div style="font-size: 8px; color: #333; margin-top: 2px;">
            Disponible durante las 72 horas posteriores a su consumo.
        </div>
    </div>

    <div class="divider"></div>

    {{-- ========================================================================= --}}
    {{-- SECCIÓN 9: PIE DE TICKET                                                  --}}
    {{-- ========================================================================= --}}
    <div class="text-center" style="font-size: 10px;">
        <div>¡Gracias por su visita!</div>
        <div>Comprobante simplificado para efectos fiscales locales.</div>
    </div>

</body>
</html>