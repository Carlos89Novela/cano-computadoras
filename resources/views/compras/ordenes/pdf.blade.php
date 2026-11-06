<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra - {{ $orden->folio }}</title>
    <style>
        @page {
            margin: 1cm;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
        }

        body {
            margin: 0;
            padding: 0;
            line-height: 1.25;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Cabecera / Membrete */
        .header-table td {
            vertical-align: top;
        }

        .empresa-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .folio-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            text-align: right;
            border-radius: 4px;
        }

        .folio-title {
            font-size: 9px;
            text-transform: uppercase;
            font-weight: bold;
            color: #64748b;
        }

        .folio-number {
            font-size: 14px;
            font-weight: bold;
            color: #4338ca;
            margin-top: 2px;
            font-family: monospace;
        }

        .badge-status {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 3px;
            margin-top: 3px;
        }

        .status-autorizada { background-color: #dcfce7; color: #166534; }
        .status-solicitada { background-color: #fef3c7; color: #92400e; }
        .status-borrador { background-color: #f1f5f9; color: #475569; }
        .status-recibida_parcial { background-color: #e0f2fe; color: #0369a1; }
        .status-recibida_total { background-color: #dbeafe; color: #1d4ed8; }
        .status-cancelada { background-color: #fee2e2; color: #991b1b; }

        /* Cajas de Información (Proveedor y Condiciones) */
        .info-table {
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .card-box {
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 8px;
            background-color: #fcfcfd;
            font-size: 9.5px;
        }

        .card-title {
            font-size: 8.5px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
            margin-bottom: 4px;
        }

        /* Tabla de Productos */
        .data-table {
            margin-top: 8px;
            border: 1px solid #cbd5e1;
        }

        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #0f172a;
        }

        .data-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            font-size: 9px;
        }

        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: bold; }

        /* Totales */
        .totals-table {
            margin-top: 8px;
            float: right;
            width: 40%;
        }

        .totals-table td {
            padding: 2px 4px;
            font-size: 9.5px;
        }

        .totals-total {
            font-size: 11px;
            border-top: 1.5px solid #0f172a;
            color: #0f172a;
        }

        .clear-fix {
            clear: both;
        }

        /* Firmas */
        .signatures {
            margin-top: 35px;
            width: 100%;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 42%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #64748b;
            margin-top: 25px;
            padding-top: 4px;
            font-size: 9px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- 1. Cabecera y Datos del Documento -->
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="empresa-title">{{ $orden->empresa?->nombre ?? 'CANO COMPUTADORAS' }}</div>
                <div>RFC: {{ $orden->empresa?->rfc ?? 'XAXX010101000' }}</div>
                <div>Sucursal de Entrega: <strong>{{ $orden->sucursal?->nombre }}</strong></div>
                <div>Almacén: {{ $orden->almacen?->nombre }}</div>
            </td>
            <td style="width: 40%;">
                <div class="folio-box">
                    <div class="folio-title">ORDEN DE COMPRA</div>
                    <div class="folio-number">{{ $orden->folio }}</div>
                    <div style="margin-top: 3px; font-size: 9px;">
                        Fecha: <strong>{{ $orden->fecha_emision?->format('d/m/Y') }}</strong>
                    </div>
                    <div>
                        <span class="badge-status status-{{ $orden->estado }}">
                            {{ strtoupper(str_replace('_', ' ', $orden->estado)) }}
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- 2. Proveedor y Logística (Tabla en 2 columnas sin alturas forzadas) -->
    <table class="info-table">
        <tr>
            <td style="width: 49%; vertical-align: top;">
                <div class="card-box">
                    <div class="card-title">Datos del Proveedor</div>
                    <div><strong>{{ $orden->proveedor?->razon_social ?? $orden->proveedor?->nombre_comercial ?? $orden->proveedor?->nombre }}</strong></div>
                    <div>RFC: <span class="font-mono">{{ $orden->proveedor?->rfc }}</span></div>
                    <div>Contacto: {{ $orden->proveedor?->contacto ?? 'N/A' }}</div>
                    <div>Teléfono: {{ $orden->proveedor?->telefono ?? 'N/A' }}</div>
                    <div>Correo: {{ $orden->proveedor?->correo ?? 'N/A' }}</div>
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top;">
                <div class="card-box">
                    <div class="card-title">Términos y Logística</div>
                    <div>Moneda: <strong>{{ $orden->moneda }}</strong></div>
                    <div>Fecha Estimada de Entrega: <strong>{{ $orden->fecha_entrega_esperada ? $orden->fecha_entrega_esperada->format('d/m/Y') : 'Inmediata' }}</strong></div>
                    <div>Elaborado por: {{ $orden->usuario?->name }}</div>
                    @if ($orden->autorizador)
                        <div>Autorizado por: <strong>{{ $orden->autorizador->name }}</strong> ({{ $orden->autorizada_at?->format('d/m/Y H:i') }})</div>
                    @endif
                    @if ($orden->notas)
                        <div style="margin-top: 2px; font-style: italic; color: #475569;">
                            Nota: {{ $orden->notas }}
                        </div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- 3. Detalle de Partidas -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">#</th>
                <th style="width: 18%;" class="text-left">SKU</th>
                <th style="width: 41%;" class="text-left">Descripción del Artículo</th>
                <th style="width: 10%;" class="text-right">Cantidad</th>
                <th style="width: 12%;" class="text-right">Costo Unit.</th>
                <th style="width: 14%;" class="text-right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orden->detalles as $idx => $item)
                <tr>
                    <td class="text-center font-mono">{{ $idx + 1 }}</td>
                    <td class="font-mono">{{ $item->producto?->sku ?? 'N/A' }}</td>
                    <td>{{ $item->producto?->nombre ?? 'Artículo sin descripción' }}</td>
                    <td class="text-right font-mono">{{ number_format((float) $item->cantidad, 2) }}</td>
                    <td class="text-right font-mono">${{ number_format((float) $item->costo_unitario, 2) }}</td>
                    <td class="text-right font-mono font-bold">${{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- 4. Cuadro de Totales -->
    <table class="totals-table">
        <tr>
            <td class="text-right" style="color: #64748b;">Subtotal:</td>
            <td class="text-right font-mono font-bold">${{ number_format((float) $orden->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right" style="color: #64748b;">IVA (16%):</td>
            <td class="text-right font-mono font-bold">${{ number_format((float) $orden->iva_total, 2) }}</td>
        </tr>
        <tr class="totals-total">
            <td class="text-right font-bold">TOTAL {{ $orden->moneda }}:</td>
            <td class="text-right font-mono font-bold" style="color: #4338ca;">${{ number_format((float) $orden->total, 2) }}</td>
        </tr>
    </table>

    <div class="clear-fix"></div>

    <!-- 5. Firmas -->
    <table class="signatures">
        <tr>
            <td class="signature-box">
                <div class="signature-line">
                    {{ $orden->usuario?->name }}<br>
                    <span style="font-size: 7.5px; font-weight: normal; color: #64748b;">ELABORÓ (COMPRAS)</span>
                </div>
            </td>
            <td style="width: 16%;"></td>
            <td class="signature-box">
                <div class="signature-line">
                    {{ $orden->autorizador?->name ?? 'PENDIENTE DE AUTORIZACIÓN' }}<br>
                    <span style="font-size: 7.5px; font-weight: normal; color: #64748b;">AUTORIZACIÓN / GERENCIA</span>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>