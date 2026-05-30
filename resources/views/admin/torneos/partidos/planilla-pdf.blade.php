<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla — Partido #{{ $match->match_number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        @page { size: A4 portrait; margin: 0.6cm; }
        body { color: #000; font-size: 6.8px; line-height: 1.05; }

        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #000; padding: 1px 2px; vertical-align: middle; }

        /* Bloques grandes con borde grueso */
        .blk { border: 2px solid #000; margin-bottom: 3px; }
        .blk table td, .blk table th { border: 1px solid #000; }

        .lbl { background: #d9d9d9; font-weight: bold; text-transform: uppercase; font-size: 6px; white-space: nowrap; }
        .sec { background: #cfcfcf; font-weight: bold; text-transform: uppercase; font-size: 7.5px; letter-spacing: .03em; }
        .ctr { text-align: center; }
        .b { font-weight: bold; }
        .val { font-size: 7px; }
        .noborder, .noborder td { border: none !important; }

        /* Encabezado */
        .logo { width: 13%; text-align: center; font-size: 6px; color: #555; height: 46px; vertical-align: middle; }
        .head-c { text-align: center; }
        .head-c .l1 { font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .head-c .l2 { font-size: 7px; text-transform: uppercase; }
        .head-c .l3 { font-size: 11px; font-weight: bold; text-transform: uppercase; margin-top: 2px; }

        /* Matriz de goles 1-36 */
        .mx td { width: 16.6%; text-align: center; font-size: 6px; height: 11px; color: #333; }

        /* Cajas de faltas */
        .fa td { text-align: center; font-size: 6px; height: 11px; }
        .fa .num { width: 11px; }

        .player td { height: 13px; }
        .empty td { height: 13px; }
        .firma { height: 38px; vertical-align: bottom; text-align: center; color: #888; font-size: 6px; }
        .footer { margin-top: 2px; font-size: 6px; color: #555; text-align: right; text-transform: uppercase; letter-spacing: .08em; }

        @php
            $categoryLabels = [
                'libre' => 'Libre', 'veteranos' => 'Veteranos', 'sub15' => 'Sub-15', 'sub17' => 'Sub-17',
                'sub20' => 'Sub-20', 'femenino' => 'Femenino', 'mixto' => 'Mixto',
            ];
            $homeName = $match->homeTeam?->name ?? 'Equipo A';
            $awayName = $match->awayTeam?->name ?? 'Equipo B';
            $pair = fn ($h, $a) => ($h !== null || $a !== null) ? (($h ?? 0) . ' - ' . ($a ?? 0)) : '';
            $ht    = $pair($match->home_score_ht, $match->away_score_ht);
            $et    = $pair($match->home_score_et, $match->away_score_et);
            $pens  = $pair($match->home_penalties, $match->away_penalties);
            $final = $pair($match->home_score, $match->away_score);
            $st = '';
            if ($match->home_score !== null && $match->home_score_ht !== null) {
                $st = max(0, $match->home_score - $match->home_score_ht) . ' - ' . max(0, ($match->away_score ?? 0) - ($match->away_score_ht ?? 0));
            }
            $winner = $match->winner_team_id
                ? ($match->winner_team_id === $match->home_team_id ? $homeName : $awayName)
                : '';
            $resultRows = [
                ['1er TIEMPO', $ht], ['2do TIEMPO', $st], ['1ra PRÓRROGA', $et],
                ['2da PRÓRROGA', ''], ['PENALES', $pens], ['RESULTADO FINAL', $final],
            ];
            $sides = [
                ['tag' => 'EQUIPO "A"', 'name' => $homeName, 'rows' => $homeRows, 's' => $sheet['home'] ?? []],
                ['tag' => 'EQUIPO "B"', 'name' => $awayName, 'rows' => $awayRows, 's' => $sheet['away'] ?? []],
            ];
            $minRows = 13;
        @endphp
    </style>
</head>
<body>

    {{-- ════ ENCABEZADO ════ --}}
    <div class="blk">
        <table class="noborder">
            <tr>
                <td class="logo noborder" style="border:1px solid #000 !important;">ESCUDO<br>LIGA</td>
                <td class="head-c noborder">
                    <div class="l1">{{ $tournament->name }}</div>
                    <div class="l2">{{ $categoryLabels[$tournament->category] ?? ucfirst($tournament->category ?? '') }} · {{ ucfirst($tournament->sport) }}{{ $tournament->city ? ' · '.$tournament->city : '' }}</div>
                    <div class="l3">Planilla Oficial de Juego</div>
                </td>
                <td class="logo noborder" style="border:1px solid #000 !important;">FEDERACIÓN /<br>PATROCINIO</td>
            </tr>
        </table>
    </div>

    {{-- ════ INFO + ÁRBITROS + RESULTADO ════ --}}
    <div class="blk">
        <table class="noborder">
            <tr>
                {{-- Columna izquierda: datos + árbitros --}}
                <td class="noborder" style="width:64%; vertical-align:top; border-right:2px solid #000 !important;">
                    <table>
                        <tr>
                            <td class="lbl" style="width:8%">A:</td><td class="val b" style="width:42%">{{ $homeName }}</td>
                            <td class="lbl" style="width:8%">B:</td><td class="val b">{{ $awayName }}</td>
                        </tr>
                    </table>
                    <table>
                        <tr>
                            <td class="lbl" style="width:14%">Torneo</td><td class="val">{{ $tournament->name }}</td>
                            <td class="lbl" style="width:16%">Categoría</td><td class="val" style="width:22%">{{ $categoryLabels[$tournament->category] ?? ucfirst($tournament->category ?? '') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Escenario</td><td class="val">{{ $match->venue ?? '' }}</td>
                            <td class="lbl">Rama</td><td class="val ctr">M&nbsp;&nbsp;/&nbsp;&nbsp;F</td>
                        </tr>
                        <tr>
                            <td class="lbl">Grupo</td><td class="val">{{ $match->group?->name ?? '' }}</td>
                            <td class="lbl">Hora</td><td class="val">{{ $match->scheduled_at?->format('H:i') ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Fase</td><td class="val">{{ $match->phase?->name }}</td>
                            <td class="lbl">J. N°</td><td class="val">{{ $match->match_number }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Fecha</td><td class="val" colspan="3">{{ $match->scheduled_at?->format('d/m/Y') ?? '' }}</td>
                        </tr>
                    </table>
                    <table>
                        @foreach ([
                            'Árbitro' => $match->referee, 'Segundo árbitro' => $match->second_referee,
                            'Tercer árbitro' => $match->third_referee, 'Cronometrador' => $match->timekeeper,
                            'Coordinador' => $match->coordinator,
                        ] as $rol => $nombre)
                            <tr><td class="lbl" style="width:24%">{{ $rol }}</td><td class="val">{{ $nombre }}</td></tr>
                        @endforeach
                    </table>
                </td>

                {{-- Columna derecha: RESULTADO --}}
                <td class="noborder" style="width:36%; vertical-align:top;">
                    <table>
                        <tr><td class="sec ctr" colspan="2">Resultado</td></tr>
                        @foreach ($resultRows as [$rotulo, $valor])
                            <tr>
                                <td class="lbl" style="width:62%">{{ $rotulo }}</td>
                                <td class="ctr b val">{{ $valor }}</td>
                            </tr>
                        @endforeach
                        <tr><td class="sec ctr" colspan="2">Equipo Ganador</td></tr>
                        <tr><td class="ctr b val" colspan="2" style="height:20px">{{ $winner }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ════ EQUIPOS ════ --}}
    @foreach ($sides as $side)
        @php $s = $side['s']; $pad = max(0, $minRows - count($side['rows'])); @endphp
        <div class="blk">
            <table class="noborder">
                {{-- Encabezado del equipo (ancho completo) --}}
                <tr>
                    <td class="sec noborder" style="border-bottom:1px solid #000 !important;">{{ $side['tag'] }}: {{ $side['name'] }}</td>
                </tr>
                <tr>
                    <td class="noborder">
                        <table class="noborder">
                            <tr>
                                <td class="lbl" style="width:8%; border:1px solid #000 !important;">Color</td>
                                <td style="border:1px solid #000 !important;">&nbsp;</td>
                                <td class="lbl ctr" style="width:26%; border:1px solid #000 !important;">S. Inicial: 1 = Titular &nbsp; O &nbsp; 2 = Suplente</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Cuerpo: jugadores (izq) + faltas/matriz (der) --}}
                <tr>
                    <td class="noborder" style="width:72%; vertical-align:top; border-right:2px solid #000 !important;">
                        <table>
                            <thead>
                                <tr class="lbl ctr">
                                    <td style="width:9%">Ficha</td>
                                    <td style="width:44%; text-align:left">Nombre del jugador</td>
                                    <td style="width:7%">N°</td>
                                    <td style="width:14%">S. Inicial</td>
                                    <td style="width:8%">Gol</td>
                                    <td style="width:9%">T.A.</td>
                                    <td style="width:9%">T.R.</td>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($side['rows'] as $r)
                                    <tr class="player">
                                        <td class="ctr val">{{ $r['ficha'] }}</td>
                                        <td class="val">{{ $r['name'] }}@if ($r['is_captain']) <span class="b">(C)</span>@endif</td>
                                        <td class="ctr val">{{ $r['number'] }}</td>
                                        <td class="ctr" style="font-size:6px">1&nbsp;&nbsp;O&nbsp;&nbsp;2</td>
                                        <td></td>
                                        <td class="ctr" style="font-size:6px">:</td>
                                        <td class="ctr" style="font-size:6px">:</td>
                                    </tr>
                                @endforeach
                                @for ($i = 0; $i < $pad; $i++)
                                    <tr class="empty">
                                        <td></td><td></td><td></td>
                                        <td class="ctr" style="font-size:6px">1&nbsp;&nbsp;O&nbsp;&nbsp;2</td>
                                        <td></td>
                                        <td class="ctr" style="font-size:6px">:</td>
                                        <td class="ctr" style="font-size:6px">:</td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>

                        {{-- Cuerpo técnico + firma --}}
                        <table style="margin-top:2px">
                            <tr>
                                <td class="lbl" style="width:18%">D.T.</td><td class="val" style="width:34%">{{ $s['coach'] ?? '' }}</td>
                                <td class="lbl ctr" style="width:20%" rowspan="3">Firma del Capitán</td>
                                <td class="firma" rowspan="3">{{ ($s['captain_signed'] ?? false) ? 'CONFIRMADO' : '' }}</td>
                            </tr>
                            <tr><td class="lbl">A. Técnico</td><td class="val">{{ $s['assistant'] ?? '' }}</td></tr>
                            <tr><td class="lbl">Delegado</td><td class="val">{{ $s['delegate'] ?? '' }}</td></tr>
                        </table>
                    </td>

                    {{-- Derecha: faltas + tiempos muertos + matriz 1-36 --}}
                    <td class="noborder" style="width:28%; vertical-align:top;">
                        <table class="fa">
                            <tr><td class="sec ctr" colspan="7">Faltas Acumulativas</td></tr>
                            <tr>
                                <td class="lbl">1º T</td>
                                @for ($n = 1; $n <= 5; $n++)<td class="num">{{ $n }}</td>@endfor
                                <td class="lbl">Tp</td>
                            </tr>
                            <tr>
                                <td class="lbl">2º T</td>
                                @for ($n = 1; $n <= 5; $n++)<td class="num">{{ $n }}</td>@endfor
                                <td class="lbl">Tp</td>
                            </tr>
                        </table>
                        <table style="margin-top:1px">
                            <tr><td class="lbl ctr" style="width:50%">1º Tp Muerto</td><td class="ctr">:</td></tr>
                            <tr><td class="lbl ctr">2º Tp Muerto</td><td class="ctr">:</td></tr>
                        </table>
                        <table class="mx" style="margin-top:1px">
                            <tr><td class="sec ctr" colspan="6">Goles (tachar)</td></tr>
                            @for ($row = 0; $row < 6; $row++)
                                <tr>
                                    @for ($col = 1; $col <= 6; $col++)
                                        <td>{{ $row * 6 + $col }}</td>
                                    @endfor
                                </tr>
                            @endfor
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach

    <div class="footer">{{ $tournament->name }} · Generada {{ $generatedAt }}</div>
</body>
</html>
