@php $r = request()->route()?->getName(); @endphp
<div class="bg-pitch-mist border-b border-line">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center gap-1 py-3 font-display font-semibold text-[13px] uppercase tracking-[.10em]">
        <span class="font-mono text-[11px] tracking-wide-label uppercase text-pitch mr-3 inline-flex items-center gap-1"><x-icon name="gear" class="w-3.5 h-3.5" /> Admin</span>
        @foreach ([
            'admin.dashboard'              => 'Dashboard',
            'admin.users.index'            => 'Usuarios',
            'admin.amistosos.index'        => 'Amistosos',
            'admin.social.moderacion.index'=> 'Moderación',
            'admin.torneos.reclamos.index' => 'Reclamos',
        ] as $name => $label)
            <a href="{{ route($name) }}"
               class="px-3 py-2 rounded-md transition-all duration-fast {{ $r === $name ? 'bg-pitch text-bone' : 'text-pitch hover:bg-white' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
