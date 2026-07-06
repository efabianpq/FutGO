{{-- Mini tabla de posiciones de ejemplo. Datos ficticios de barrio. --}}
@php
    $rows = [
        ['rk' => 1, 'code' => 'LR', 'name' => 'Los Reyes',    'pj' => 5, 'dg' => '+8', 'pts' => 13, 'top' => true],
        ['rk' => 2, 'code' => 'DV', 'name' => 'Dep. Valle',   'pj' => 5, 'dg' => '+3', 'pts' => 10, 'top' => false],
        ['rk' => 3, 'code' => 'LB', 'name' => 'Los Bohemios', 'pj' => 5, 'dg' => '+1', 'pts' => 8,  'top' => false],
        ['rk' => 4, 'code' => 'SM', 'name' => 'San Martín',   'pj' => 5, 'dg' => '−4', 'pts' => 4,  'top' => false],
    ];
@endphp

<div class="bg-surface border border-border rounded-lg overflow-hidden shadow-card-2">
    <div class="flex items-center justify-between px-4 py-3 text-white font-x font-bold text-[15px]"
         style="background:var(--color-green); font-stretch:115%">
        <span>Copa del Barrio · Zona A</span>
        <span class="font-mono text-[10px] uppercase rounded-pill px-2 py-0.5 bg-white/20" style="letter-spacing:.1em">Fecha 5 / 7</span>
    </div>
    <table class="w-full border-collapse">
        <thead>
            <tr class="font-mono text-[10px] uppercase text-subtle" style="letter-spacing:.08em">
                <th class="text-center px-4 py-2.5 font-medium border-b border-border">#</th>
                <th class="text-left px-4 py-2.5 font-medium border-b border-border">Equipo</th>
                <th class="text-center px-4 py-2.5 font-medium border-b border-border">PJ</th>
                <th class="text-center px-4 py-2.5 font-medium border-b border-border">DG</th>
                <th class="text-right px-4 py-2.5 font-medium border-b border-border">Pts</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
                <tr @class(['text-[13.5px]']) @style(['background:var(--color-green-tint)' => $r['top']])>
                    <td class="px-4 py-2.5 border-b border-border-soft text-center font-x font-extrabold @if($r['top']) text-green-strong @endif" style="font-stretch:115%">{{ $r['rk'] }}</td>
                    <td class="px-4 py-2.5 border-b border-border-soft">
                        <div class="flex items-center gap-2.5 font-semibold">
                            <span class="w-[26px] h-[26px] rounded-[7px] bg-surface-3 flex items-center justify-center font-x font-bold text-[11px] shrink-0" style="font-stretch:115%">{{ $r['code'] }}</span>
                            {{ $r['name'] }}
                        </div>
                    </td>
                    <td class="px-4 py-2.5 border-b border-border-soft text-center tabular-nums">{{ $r['pj'] }}</td>
                    <td class="px-4 py-2.5 border-b border-border-soft text-center tabular-nums">{{ $r['dg'] }}</td>
                    <td class="px-4 py-2.5 border-b border-border-soft text-right font-x font-extrabold" style="font-stretch:115%">{{ $r['pts'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="text-center font-mono text-[12px] text-subtle mt-3.5">Tabla y estadísticas, siempre al día</p>
