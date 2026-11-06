<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de Caja #{{ $turno->id }}</title>
    <style>
        @page {
            margin: 5mm;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            line-height: 1.25;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .border-top { border-top: 1px dashed #000; padding-top: 4px; margin-top: 4px; }
        .border-bottom { border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; }
        .firma { margin-top: 35px; border-top: 1px solid #000; text-align: center; font-size: 9px; }
    </style>
</head>
<body>

    <div class="text-center">
        <div class="font-bold" style="font-size: 13px;">{{ $turno->empresa?->nombre ?: 'CANO COMPUTADORAS' }}</div>
        <div>SUCURSAL: {{ $turno->sucursal?->nombre }}</div>
        <div>CORTE DE CAJA #{{ $turno->id }}</div>
    </div>

    <div class="border-top border-bottom" style="font-size: 9px;">
        <div>CAJERO: {{ $turno->user?->name }}</div>
        <div>APERTURA: {{ $turno->apertura_at?->format('d/m/Y H:i') }}</div>
        <div>CIERRE:   {{ $turno->cierre_at?->format('d/m/Y H:i') ?: 'EN OPERACION' }}</div>
    </div>

    <table>
        <tr>
            <td>(+) FONDO INICIAL:</td>
            <td class="text-right font-bold">${{ number_format((float) $turno->monto_inicial, 2) }}</td>
        </tr>
        <tr>
            <td>(+) VENTAS EN EFECTIVO:</td>
            <td class="text-right font-bold">${{ number_format((float) $turno->total_ventas_efectivo, 2) }}</td>
        </tr>
        <tr>
            <td>(+) ENTRADAS EXTRAORDINARIAS:</td>
            <td class="text-right font-bold">${{ number_format((float) $turno->total_otros_ingresos, 2) }}</td>
        </tr>
        <tr>
            <td>(-) RETIROS DE EFECTIVO:</td>
            <td class="text-right font-bold">-${{ number_format((float) $turno->total_retiros, 2) }}</td>
        </tr>
        <tr class="border-top font-bold">
            <td>(=) EFECTIVO ESPERADO:</td>
            <td class="text-right">${{ number_format((float) $turno->monto_esperado_efectivo, 2) }}</td>
        </tr>
        <tr>
            <td>(~) EFECTIVO CONTADO:</td>
            <td class="text-right font-bold">${{ number_format((float) $turno->monto_real_efectivo, 2) }}</td>
        </tr>
        <tr class="font-bold">
            <td>DIFERENCIA (ARQUEO):</td>
            @php $dif = (float) $turno->diferencia_efectivo; @endphp
            <td class="text-right">{{ $dif >= 0 ? '+' : '' }}${{ number_format($dif, 2) }}</td>
        </tr>
    </table>

    <div class="border-top">
        <div class="font-bold">OTROS MEDIOS DE COBRO:</div>
        <table>
            <tr>
                <td>TARJETA (VOUCHERS):</td>
                <td class="text-right font-bold">${{ number_format((float) $turno->total_ventas_tarjeta, 2) }}</td>
            </tr>
            <tr>
                <td>TRANSFERENCIA:</td>
                <td class="text-right font-bold">${{ number_format((float) $turno->total_ventas_transferencia, 2) }}</td>
            </tr>
        </table>
    </div>

    @if ($turno->observaciones_cierre)
        <div class="border-top" style="font-size: 9px;">
            <div class="font-bold">OBSERVACIONES:</div>
            <div>{{ $turno->observaciones_cierre }}</div>
        </div>
    @endif

    <div class="firma">
        FIRMA DEL CAJERO
    </div>

    <div class="firma" style="margin-top: 25px;">
        FIRMA SUPERVISOR / GERENTE
    </div>

</body>
</html>