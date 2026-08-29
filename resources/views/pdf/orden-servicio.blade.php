<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <title>
        Orden {{ $orden->folio }}
    </title>

    <style>
        @page {
            margin: 35px;
        }

        body {
            margin: 0;
            color: #222222;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }

        .header {
            padding-bottom: 18px;
            border-bottom: 3px solid #7e22ce;
        }

        .brand {
            color: #7e22ce;
            font-size: 24px;
            font-weight: bold;
        }

        .subtitle {
            color: #666666;
            font-size: 11px;
        }

        .folio-box {
            margin-top: 20px;
            padding: 15px;
            color: #ffffff;
            background-color: #581c87;
        }

        .folio-label {
            font-size: 11px;
        }

        .folio {
            font-size: 20px;
            font-weight: bold;
        }

        .status {
            margin-top: 6px;
        }

        .section {
            margin-top: 25px;
        }

        .section-title {
            margin-bottom: 10px;
            padding-bottom: 5px;
            color: #581c87;
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px solid #cccccc;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            width: 50%;
            padding: 7px 8px;
            vertical-align: top;
            border: 1px solid #dddddd;
        }

        .label {
            color: #666666;
            font-size: 10px;
            text-transform: uppercase;
        }

        .value {
            margin-top: 3px;
            font-weight: bold;
        }

        .description {
            padding: 12px;
            background-color: #f4f4f4;
            border-left: 4px solid #7e22ce;
        }

        .cost-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cost-table th,
        .cost-table td {
            padding: 9px;
            text-align: left;
            border: 1px solid #dddddd;
        }

        .cost-table th {
            color: #ffffff;
            background-color: #581c87;
        }

        .history-item {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f4f4f4;
            border-left: 4px solid #7e22ce;
        }

        .history-status {
            color: #581c87;
            font-weight: bold;
        }

        .history-date {
            color: #666666;
            font-size: 10px;
        }

        .footer {
            margin-top: 35px;
            padding-top: 12px;
            color: #777777;
            text-align: center;
            font-size: 10px;
            border-top: 1px solid #cccccc;
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="brand">
            Cano Computadoras
        </div>

        <div class="subtitle">
            Servicio profesional de reparación y mantenimiento de computadoras
        </div>
    </div>

    <div class="folio-box">
        <div class="folio-label">
            ORDEN DE SERVICIO
        </div>

        <div class="folio">
            {{ $orden->folio }}
        </div>

        <div class="status">
            Estado actual: {{ $orden->estado }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">
            Información del cliente
        </div>

        <table class="info-table">
            <tr>
                <td>
                    <div class="label">
                        Cliente
                    </div>

                    <div class="value">
                        {{ $orden->user->name }}
                    </div>
                </td>

                <td>
                    <div class="label">
                        Correo electrónico
                    </div>

                    <div class="value">
                        {{ $orden->user->email }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">
            Información del equipo
        </div>

        <table class="info-table">
            <tr>
                <td>
                    <div class="label">
                        Tipo
                    </div>

                    <div class="value">
                        {{ $orden->equipo->tipo }}
                    </div>
                </td>

                <td>
                    <div class="label">
                        Marca y modelo
                    </div>

                    <div class="value">
                        {{ $orden->equipo->marca }}
                        {{ $orden->equipo->modelo }}
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="label">
                        Número de serie
                    </div>

                    <div class="value">
                        {{ $orden->equipo->numero_serie ?: 'No registrado' }}
                    </div>
                </td>

                <td>
                    <div class="label">
                        Fecha de ingreso
                    </div>

                    <div class="value">
                        {{ $orden->fecha_ingreso->format('d/m/Y') }}
                    </div>
                </td>
            </tr>