<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #14211b; font-size: 10px; }
        .head { border-bottom: 3px solid #00a847; padding-bottom: 8px; margin-bottom: 12px; }
        .brand { font-size: 18px; font-weight: bold; color: #00a847; letter-spacing: 1px; }
        .tname { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .title { font-size: 11px; color: #555; margin-top: 1px; }
        .meta { font-size: 8px; color: #888; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #0b2c1e; color: #fff; text-align: left; padding: 5px 6px; font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em; }
        td { padding: 4px 6px; border-bottom: 1px solid #e4e9e6; }
        tr:nth-child(even) td { background: #f4f7f5; }
        .empty { text-align: center; color: #999; padding: 18px; }
        .foot { margin-top: 14px; font-size: 7.5px; color: #aaa; text-align: center; }
    </style>
</head>
<body>
    <div class="head">
        <div class="brand">FutGO</div>
        <div class="tname">{{ $tournament->name }}</div>
        <div class="title">{{ $title }}</div>
        <div class="meta">Generado el {{ $generatedAt }} · {{ count($rows) }} registro(s)</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($headers) }}">Sin datos para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">FutGO · Donde crece el fútbol amateur · {{ $tournament->name }}</div>
</body>
</html>
