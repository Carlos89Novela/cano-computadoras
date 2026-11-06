<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Fiscal - {{ $venta->serie_folio_factura }}</title>
    <style>
        @page { margin: 10mm; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
            color: #1e293b;
            line-height: 1.25;
        }
        .header { width: 100%; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 8px; }
        .title { font-size: 13px; font-weight: bold; color: #0f172a; }
        .box { border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px; margin-bottom: 6px; }
        .box-title { font-weight: bold; font-size: 8.5px; margin-bottom: 4px; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        table.items th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 4px;
            font-size: 7.5px;
            text-transform: uppercase;
        }
        table.items td {
            border: 1px solid #e2e8f0;
            padding: 3.5px;
            font-size: 7.5px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: "Courier New", Courier, monospace; }
        .break-words { word-break: break-all; }
        .badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-weight: bold; font-size: 7px; background-color: #e0e7ff; color: #3730a3; }
    </style>
</head>
<body>

    <!-- Encabezado -->
    <table class="header">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="title">{{ $venta->empresa->nombre }}</div>
                <div><strong>RFC Emisor:</strong> {{ $venta->empresa->rfc ?? 'XAXX010101000' }}</div>
                <div><strong>Régimen Fiscal:</strong> 601 - General de Ley Personas Morales</div>
                <div><strong>Lugar de Expedición:</strong> C.P. {{ $venta->sucursal->codigo_postal ?? '83000' }}</div>
            </td>
            <td style="width: 45%; vertical-align: top;" class="text-right">
                <div style="font-size: 11px; font-weight: bold; color: #4338ca;">FACTURA ELECTRÓNICA (CFDI 4.0)</div>
                <div><strong>Serie y Folio:</strong> {{ $venta->serie_folio_factura }}</div>
                <div><strong>UUID / Folio Fiscal:</strong></div>
                <div class="font-mono badge">{{ $venta->uuid_fiscal }}</div>
                <div style="margin-top: 3px;"><strong>Fecha Timbrado:</strong> {{ $venta->facturado_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s') }}</div>
            </td>
        </tr>
    </table>

    <!-- Datos Receptor y Datos de Pago -->
    <table style="width: 100%; margin-bottom: 6px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 4px;">
                <div class="box">
                    <div class="box-title">RECEPTOR (CLIENTE)</div>
                    <div><strong>Nombre / Razón Social:</strong> {{ $venta->cliente_nombre }}</div>
                    <div><strong>RFC:</strong> {{ $venta->cliente_rfc }}</div>
                    <div><strong>Domicilio Fiscal (C.P.):</strong> {{ $venta->cliente_codigo_postal ?? '83000' }}</div>
                    <div><strong>Régimen Fiscal:</strong> {{ $venta->cliente_regimen_fiscal ?? '616 - Sin obligaciones fiscales' }}</div>
                    <div><strong>Uso de CFDI:</strong> {{ $venta->uso_cfdi_sat ?? 'S01 - Sin efectos fiscales' }}</div>
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 4px;">
                <div class="box">
                    <div class="box-title">CONDICIONES DE PAGO</div>
                    <div><strong>Forma de Pago:</strong> {{ $venta->forma_pago_sat }} - Efectivo / Pago Digital</div>
                    <div><strong>Método de Pago:</strong> {{ $venta->metodo_pago_sat }} - Pago en una sola exhibición</div>
                    <div><strong>Moneda:</strong> MXN (Peso Mexicano)</div>
                    <div><strong>Tipo de Comprobante:</strong> I - Ingreso</div>
                    <div><strong>Exportación:</strong> 01 - No aplica</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Conceptos / Partidas -->
    <table class="items" style="margin-bottom: 8px;">
        <thead>
            <tr>
                <th style="width: 10%;">Clave SAT</th>
                <th style="width: 10%;">SKU</th>
                <th style="width: 8%;" class="text-right">Cant.</th>
                <th style="width: 8%;">Unidad</th>
                <th style="width: 34%;">Descripción</th>
                <th style="width: 10%;" class="text-right">P. Unitario</th>
                <th style="width: 10%;" class="text-right">Impuesto</th>
                <th style="width: 10%;" class="text-right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $det)
                <tr>
                    <td class="text-center font-mono">{{ $det->clave_sat_producto ?? '43211500' }}</td>
                    <td class="font-mono">{{ $det->sku ?? 'GEN' }}</td>
                    <td class="text-right">{{ number_format((float) $det->cantidad, 2) }}</td>
                    <td class="text-center font-mono">{{ $det->clave_sat_unidad ?? 'H87' }}</td>
                    <td>{{ $det->descripcion_producto }}</td>
                    <td class="text-right font-mono">${{ number_format((float) $det->precio_unitario, 2) }}</td>
                    <td class="text-right font-mono">IVA 16% (${{ number_format((float) bcmul((string) $det->monto_iva_unitario, (string) $det->cantidad, 2), 2) }})</td>
                    <td class="text-right font-mono font-bold">${{ number_format((float) $det->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totales -->
    <table style="width: 100%; margin-bottom: 8px;">
        <tr>
            <td style="width: 65%; vertical-align: top;">
                <div class="box" style="font-size: 7.5px;">
                    <strong>Tipo de Relación:</strong> No aplica<br>
                    <strong>Efecto Fiscal:</strong> Este documento es una representación impresa de un CFDI versión 4.0.
                </div>
            </td>
            <td style="width: 35%; vertical-align: top; padding-left: 8px;">
                <table style="width: 100%; font-size: 8px;">
                    <tr>
                        <td style="padding: 2px;">Subtotal:</td>
                        <td class="text-right font-mono" style="padding: 2px;">${{ number_format((float) $venta->base_imponible, 2) }}</td>
                    </tr>
                    @if ((float) $venta->descuento_total > 0)
                        <tr>
                            <td style="padding: 2px;">Descuento:</td>
                            <td class="text-right font-mono" style="padding: 2px;">-${{ number_format((float) $venta->descuento_total, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 2px;">IVA Trasladado (16%):</td>
                        <td class="text-right font-mono" style="padding: 2px;">${{ number_format((float) $venta->iva_total, 2) }}</td>
                    </tr>
                    <tr style="border-top: 1px solid #0f172a; font-weight: bold; font-size: 9px;">
                        <td style="padding: 3px 2px;">Total:</td>
                        <td class="text-right font-mono" style="padding: 3px 2px; color: #0f172a;">${{ number_format((float) $venta->total, 2) }} MXN</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Sellos Digitales y Cadena Original -->
    <div class="box" style="font-size: 6.5px; line-height: 1.15;">
        <div style="margin-bottom: 3px;">
            <strong>Cadena Original del Complemento de Certificación Digital del SAT:</strong><br>
            <span class="font-mono break-words">||1.1|{{ $venta->uuid_fiscal }}|{{ now()->toIso8601String() }}|MOCK010101000|SELLO_DIGITAL_CANO_COMPUTADORAS_HASH_SHA256_VERIFICADO|30001000000500003416||</span>
        </div>
        <div style="margin-bottom: 3px;">
            <strong>Sello Digital del Emisor:</strong><br>
            <span class="font-mono break-words">dGVzdF9zZWxsb19kaWdpdGFsX2VtaXNvcl9jYW5vX2NvbXB1dGFkb3Jhc19zYV9kZV9jdl92ZXJzaW9uXzRfMF9zYXRfdmVyaWZpY2Fkb19va19zaGEyNTY=</span>
        </div>
        <div>
            <strong>Sello del SAT:</strong><br>
            <span class="font-mono break-words">c2VsbG9fc2F0X2F1dG9yaXphZG9fcG9yX3Byb3ZlZWRvcl9wYWNfc2FuZGJveF9jYW5vX2NvbXB1dGFkb3Jhc19jZmRpX3ZhbGlkYWRv</span>
        </div>
    </div>

</body>
</html>