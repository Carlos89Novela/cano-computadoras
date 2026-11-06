<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kardex - {{ $producto->sku }}</title>
    <style>
        @page { margin: 12mm; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            color: #1e293b;
        }
        .header { margin-bottom: 15px; border-bottom: 2px solid #0f172a; padding-bottom: 8px; }
        .title { font-size: 14px; font-weight: bold; color: #0f172a; }
        .subtitle { font-size: 9px; color: #64748b; margin-top: 2px; }
        .meta-box { width: 100%; margin-bottom: 12px; }
        .meta-box td { padding: 2px 4px; vertical-align: top; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 5px; }
        table.data th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 5px 4px;
            font-size: 8px;
            text-transform: uppercase;
        }
        table.data td {
            border: 1px solid #e2e8f0;
            padding: 4px;
            font-size: 8px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 7px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="title">{{ $empresa->nombre }}</div>
                    <div class="subtitle">REPORTE OFICIAL DE KARDEX Y TRAZABILIDAD DE INVENTARIO</div>
                </td>
                <td class="text-right" style="vertical-align: bottom;">
                    <div>Fecha Emisión: {{ now()->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-box">
        <tr>
            <td style="width: 50%;">
                <strong>PRODUCTO:</strong> {{ $producto->nombre }}<br>
                <strong>SKU / CÓDIGO:</strong> {{ $producto->sku }} | <strong>UNIDAD:</strong> {{ strtoupper($producto->unidad_medida) }}
            </td>
            <td style="width: 50%;" class="text-right">
                <strong>ALMACÉN:</strong> {{ $almacen ? $almacen->nombre . ' (' . $almacen->codigo . ')' : 'CONSOLIDADO (TODOS LOS ALMACENES PERMITIDOS)' }}<br>
                @if($fechaInicio || $fechaFin)
                    <strong>RANGO:</strong> {{ $fechaInicio ?: 'Inicio' }} al {{ $fechaFin ?: 'Presente' }}
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 10%;">Fecha</th>
                <th style="width: 12%;">Folio</th>
                <th style="width: 15%;">Tipo / Afectación</th>
                <th style="width: 13%;">Almacén</th>
                <th style="width: 8%;" class="text-right">Cantidad</th>
                <th style="width: 8%;" class="text-right">Stock Ant.</th>
                <th style="width: 8%;" class="text-right">Stock Nvo.</th>
                <th style="width: 10%;" class="text-right">Costo Unit.</th>
                <th style="width: 16%;">Motivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimientos as $m)
                <tr>
                    <td class="text-center">{{ $m->ocurrido_at->format('d/m/Y H:i') }}</td>
                    <td class="font-bold">{{ $m->folio }}</td>
                    <td>{{ strtoupper(str_replace('_', ' ', $m->tipo)) }}</td>
                    <td>{{ $m->almacen->nombre }}</td>
                    <td class="text-right font-bold">{{ number_format((float) $m->cantidad, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $m->existencia_anterior, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format((float) $m->existencia_nueva, 2) }}</td>
                    <td class="text-right">${{ number_format((float) $m->costo_unitario, 2) }}</td>
                    <td>{{ $m->motivo }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 15px; color: #64748b;">
                        No se registraron movimientos en el periodo seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Documento interno de control contable - Cano Computadoras. Valuación bajo método de Costo Promedio Ponderado (CPP).
    </div>

</body>
</html>