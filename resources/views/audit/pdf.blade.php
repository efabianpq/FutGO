<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SoyPachonMundial — Reporte de auditoría</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        @page { size: letter landscape; margin: 14mm 12mm; }
        body { color: #1a1a1a; font-size: 9px; line-height: 1.35; }

        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #0a3d2e;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header .brand {
            display: table-cell;
            vertical-align: middle;
            color: #0a3d2e;
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .header .brand .dot { color: #d4a82a; }
        .header .meta {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 9px;
            color: #4a4a48;
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }
        .title {
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            color: #0a3d2e;
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }
        .subtitle {
            font-size: 10px;
            color: #4a4a48;
            margin-bottom: 14px;
        }

        table { width: 100%; border-collapse: collapse; }
        thead {
            background: #0a3d2e;
            color: #f5f1e8;
            font-size: 8.5px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        thead th { padding: 6px 5px; text-align: left; font-weight: 600; }
        thead th.right { text-align: right; }
        tbody td {
            padding: 5px;
            border-bottom: 0.5px solid #efeadc;
            vertical-align: top;
        }
        tbody tr:nth-child(even) { background: #faf7ef; }

        .col-match { width: 26%; font-weight: 600; }
        .col-phase { width: 11%; color: #4a4a48; }
        .col-date  { width: 13%; color: #4a4a48; font-size: 8px; }
        .col-off   { width: 9%; text-align: center; font-weight: 700; color: #0a3d2e; font-size: 11px; font-family: 'Courier New', monospace; }
        .col-user  { width: 18%; font-weight: 600; }
        .col-pred  { width: 9%; text-align: center; font-family: 'Courier New', monospace; font-weight: 700; color: #14593f; font-size: 11px; }
        .col-pts   { width: 7%; text-align: right; font-weight: 800; }

        .pts-5 { background: #f4d03f; color: #0a3d2e; }
        .pts-3 { background: #b8d2c4; color: #0a3d2e; }
        .pts-2 { background: #e8efe9; color: #0a3d2e; }
        .pts-1 { background: #fef0d4; color: #d4a82a; }
        .pts-0 { background: #efeadc; color: #8a8884; }

        .pts-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 9px;
            min-width: 28px;
            text-align: center;
        }

        .footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #e5dfd1;
            display: table;
            width: 100%;
            font-size: 8.5px;
            color: #4a4a48;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .footer .left { display: table-cell; width: 60%; }
        .footer .right { display: table-cell; width: 40%; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Pachón<span class="dot">·</span>Mundial <span style="font-size:9px; letter-spacing:.14em; color:#4a4a48; font-weight:500; margin-left:8px;">@SoyPachón</span></div>
        <div class="meta">Reporte de auditoría<br>{{ $generatedAt }}</div>
    </div>

    <div class="title">Detalle de pronósticos y puntos calculados</div>
    <div class="subtitle">
        Incluye únicamente partidos finalizados con resultado oficial cargado. Total: <strong>{{ number_format($totalRows, 0, ',', '.') }}</strong> registros.
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-match">Partido</th>
                <th class="col-phase">Fase</th>
                <th class="col-date">Fecha</th>
                <th class="col-off">Oficial</th>
                <th class="col-user">Usuario</th>
                <th class="col-pred">Pronóstico</th>
                <th class="col-pts right">Pts</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                @php
                    $cls = match ((int) $r['points']) {
                        5 => 'pts-5',
                        3 => 'pts-3',
                        2 => 'pts-2',
                        1 => 'pts-1',
                        default => 'pts-0',
                    };
                @endphp
                <tr>
                    <td class="col-match">{{ $r['match'] }}</td>
                    <td class="col-phase">{{ $r['phase'] }}</td>
                    <td class="col-date">{{ $r['date'] }}</td>
                    <td class="col-off">{{ $r['official'] }}</td>
                    <td class="col-user">{{ $r['user'] }}</td>
                    <td class="col-pred">{{ $r['prediction'] }}</td>
                    <td class="col-pts right"><span class="pts-badge {{ $cls }}">{{ $r['points'] }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="left">Total de registros: <strong>{{ number_format($totalRows, 0, ',', '.') }}</strong></div>
        <div class="right">soypachonmundial.online · {{ now()->format('Y-m-d H:i') }}</div>
    </div>
</body>
</html>
