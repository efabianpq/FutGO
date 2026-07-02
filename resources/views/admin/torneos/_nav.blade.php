@php $r = request()->route()?->getName(); @endphp
<div class="bg-pitch-mist border-b border-line">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center gap-1 py-3 font-display font-semibold text-[13px] uppercase tracking-[.10em]">
        <span class="font-mono text-[11px] tracking-wide-label uppercase text-pitch mr-3">Torneos</span>

        <a href="{{ route('torneos.index') }}"
           class="px-3 py-2 rounded-md transition-all duration-fast {{ $r === 'torneos.index' ? 'bg-pitch text-bone' : 'text-pitch hover:bg-white' }}">
            Mis torneos
        </a>

        <a href="{{ route('admin.torneos.create') }}"
           class="px-3 py-2 rounded-md transition-all duration-fast {{ $r === 'admin.torneos.create' ? 'bg-pitch text-bone' : 'text-pitch hover:bg-white' }}">
            Nuevo torneo
        </a>

        @if (auth()->user()?->isAdmin())
            <a href="{{ route('admin.dashboard') }}"
               class="px-3 py-2 rounded-md transition-all duration-fast text-ink-mute hover:bg-white ml-auto">
                ← Panel Admin
            </a>
        @endif
    </div>
</div>
