@php $r = request()->route()?->getName(); @endphp
<div class="bg-pachon-gold/20 border-b border-pachon-gold/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center gap-1 py-2 text-sm">
        <span class="font-bold text-pachon-green-dark mr-2">⚙️ Panel Admin:</span>
        @foreach ([
            'admin.dashboard'   => ['Dashboard', '📊'],
            'admin.results.index'=>['Resultados', '🎯'],
            'admin.codes.index' => ['Códigos',    '🎟️'],
            'admin.users.index' => ['Usuarios',   '👥'],
            'admin.fixture.index'=>['Fixture',    '📅'],
            'admin.settings.edit'=>['Configuración', '⚙️'],
        ] as $name => [$label, $icon])
            <a href="{{ route($name) }}"
               class="px-3 py-1.5 rounded-md transition {{ $r === $name ? 'bg-pachon-green text-white' : 'text-pachon-green-dark hover:bg-white/50' }}">
                {{ $icon }} {{ $label }}
            </a>
        @endforeach
    </div>
</div>
