<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de órdenes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            font-size: 12px;
        }
        .header {
            border-bottom: 2px solid #7c3aed;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        h2 {
            margin: 0 0 6px;
            font-size: 24px;
            color: #1f2937;
        }
        .meta {
            color: #4b5563;
            font-size: 12px;
        }
        .summary {
            margin-bottom: 20px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 12px;
        }
        .summary-row {
            display: block;
            margin-bottom: 6px;
        }
        .summary-label {
            font-weight: bold;
            color: #374151;
        }
        .state-box {
            display: inline-block;
            width: 32%;
            margin: 0 1% 8px 0;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-sizing: border-box;
        }
        .state-name {
            font-size: 11px;
            color: #4b5563;
            display: block;
            margin-bottom: 4px;
        }
        .state-total {
            font-weight: bold;
            color: #111827;
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
        }
        tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .summary-section {
            margin-top: 12px;
        }

        .summary-title {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
        }

        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            table-layout: fixed;
        }

        .summary-table td {
            width: 33.333%;
            padding: 0;
            vertical-align: top;
            border: 0;
        }

        .summary-card {
            min-height: 42px;
            padding: 9px 10px;
            border: 1px solid #d1d5db;
            background-color: #ffffff;
        }

        .summary-card-title {
            margin-bottom: 5px;
            font-size: 10px;
            color: #4b5563;
        }

        .summary-card-value {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
        }

        .summary-spacer {
            height: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>
            {{ $estado !== 'all' ? 'Reporte de órdenes — '.$estado : 'Reporte de órdenes' }}
        </h2>
        <div class="meta">Generado: {{ $fechaGeneracion }}</div>
    </div>

    <div class="summary">
        <div class="summary-row">
            <span class="summary-label">
                Total de órdenes:
            </span>

            {{ $totalOrdenes }}
        </div>

        <div class="summary-row">
            <span class="summary-label">
                Ingresos totales:
            </span>

            $ {{ number_format(
                (float) $totalIngresos,
                2,
                '.',
                ','
            ) }}
        </div>

        @if ($resumenPorEstado->isNotEmpty())
            <div class="summary-section">
                <div class="summary-title">
                    Resumen por estado:
                </div>

                @foreach ($resumenPorEstado->chunk(3) as $grupoEstados)
                    <table class="summary-table">
                        <tbody>
                            <tr>
                                @foreach ($grupoEstados as $nombreEstado => $resumen)
                                    <td>
                                        <div class="summary-card">
                                            <div class="summary-card-title">
                                                {{ $nombreEstado }}
                                            </div>

                                            <div class="summary-card-value">
                                                {{ $resumen['cantidad'] }}

                                                {{ $resumen['cantidad'] === 1
                                                    ? 'orden'
                                                    : 'órdenes' }}

                                                · $ {{ number_format(
                                                    (float) $resumen['total'],
                                                    2,
                                                    '.',
                                                    ','
                                                ) }}
                                            </div>
                                        </div>
                                    </td>
                                @endforeach

                                @for ($i = $grupoEstados->count(); $i < 3; $i++)
                                    <td></td>
                                @endfor
                            </tr>
                        </tbody>
                    </table>

                    @unless ($loop->last)
                        <div class="summary-spacer"></div>
                    @endunless
                @endforeach
            </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Equipo</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th>Costo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ordenes as $orden)
                <tr>
                    <td>{{ $orden->folio }}</td>
                    <td>{{ $orden->user?->name ?? '-' }}</td>
                    <td>{{ trim(($orden->equipo?->marca ?? '').' '.($orden->equipo?->modelo ?? '')) ?: '-' }}</td>
                    <td>{{ $orden->estado ?? '-' }}</td>
                    <td>{{ $orden->fecha_ingreso?->format('d/m/Y') ?? '-' }}</td>
                    <td>$ {{ number_format((float) ($orden->costo_final ?? 0), 2, '.', ',') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No hay órdenes para exportar con el filtro seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
