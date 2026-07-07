@php $r = request()->route()?->getName() ?? ''; @endphp
<div class="bg-pitch-mist border-b border-line">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center gap-1 py-3 font-display font-semibold text-[13px] uppercase tracking-[.10em]">
        <span class="font-mono text-[11px] tracking-wide-label uppercase text-pitch mr-3 inline-flex items-center gap-1"><x-icon name="gear" class="w-3.5 h-3.5" /> Admin</span>
        @foreach ([
            ['prefix' => 'admin.dashboard',               'route' => 'admin.dashboard',               'label' => 'Dashboard'],
            ['prefix' => 'admin.users.',                  'route' => 'admin.users.index',              'label' => 'Usuarios'],
            ['prefix' => 'admin.amistosos.',              'route' => 'admin.amistosos.index',          'label' => 'Amistosos'],
            ['prefix' => 'admin.social.moderacion.',      'route' => 'admin.social.moderacion.index',  'label' => 'Moderación'],
            ['prefix' => 'admin.torneos.reclamos.',       'route' => 'admin.torneos.reclamos.index',   'label' => 'Reclamos'],
            ['prefix' => 'admin.legal.',                  'route' => 'admin.legal.index',              'label' => 'Legal'],
            ['prefix' => 'admin.soporte.',                'route' => 'admin.soporte.dashboard',        'label' => 'Soporte'],
        ] as $item)
            <a href="{{ route($item['route']) }}"
               class="px-3 py-2 rounded-md transition-all duration-fast {{ str_starts_with($r, $item['prefix']) ? 'bg-pitch text-bone' : 'text-pitch hover:bg-white' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</div>
