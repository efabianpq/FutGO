@props([
    'tournament' => 'Copa del Barrio',
    'round' => 'Fecha 5',
    'homeCode' => 'LR',  'homeName' => 'Los Reyes',
    'awayCode' => 'DV',  'awayName' => 'Dep. Valle',
    'homeScore' => 3,    'awayScore' => 1,
    'scorer' => 'A. Soto ×2',
    'radius' => '10px',
])

{{-- Tarjeta de resultado compartible (gradiente verde, estética WhatsApp).
     Datos ficticios de barrio — NO reales, sin cifras de tracción. --}}
<div class="relative overflow-hidden text-white"
     style="border-radius:{{ $radius }}; background:linear-gradient(150deg, var(--color-green), var(--color-green-700));">
    <div class="flex items-center justify-between px-3 py-2 font-mono text-[9px] uppercase opacity-85"
         style="letter-spacing:.12em">
        <span>{{ $tournament }}</span><span>{{ $round }}</span>
    </div>
    <div class="px-3.5 pt-1.5 pb-3.5">
        <div class="grid items-center gap-2" style="grid-template-columns:1fr auto 1fr">
            <div class="text-center">
                <div class="w-[38px] h-[38px] rounded-md bg-white/15 flex items-center justify-center mx-auto mb-1.5 font-x font-extrabold text-[15px]" style="font-stretch:120%">{{ $homeCode }}</div>
                <div class="font-x font-bold text-[12.5px]" style="font-stretch:115%">{{ $homeName }}</div>
            </div>
            <div class="font-x font-black text-[38px] leading-none" style="font-stretch:120%; letter-spacing:.02em">{{ $homeScore }}<span class="opacity-60">-</span>{{ $awayScore }}</div>
            <div class="text-center">
                <div class="w-[38px] h-[38px] rounded-md bg-white/15 flex items-center justify-center mx-auto mb-1.5 font-x font-extrabold text-[15px]" style="font-stretch:120%">{{ $awayCode }}</div>
                <div class="font-x font-bold text-[12.5px]" style="font-stretch:115%">{{ $awayName }}</div>
            </div>
        </div>
        <div class="flex items-center justify-between mt-3 pt-2.5 text-[10px] border-t border-white/20">
            <span class="font-x font-extrabold inline-flex items-center gap-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><circle cx="12" cy="12" r="9"/><path d="M12 7l3.5 2.5-1.3 4.1h-4.4L8.5 9.5z"/></svg>
                <b style="color:#d5ffe4">{{ $scorer }}</b>
            </span>
            <span class="opacity-80">Compartir</span>
        </div>
    </div>
    <div class="absolute right-2.5 bottom-9 font-x font-black text-[11px] opacity-35">FutGO</div>
</div>
