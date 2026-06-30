{{-- Icono de una pestaña de la bottom nav (mobile).
     Props: $icon (home|ball|trophy|users|chart|avatar), $user, $pending --}}
@switch($icon)
    @case('home')
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11l9-8 9 8M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10"/></svg>
        @break
    @case('ball')
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.5 4.5L19 8l-3 3.5L17 16l-5-2-5 2 1-4.5L5 8l4.5-.5z"/></svg>
        @break
    @case('trophy')
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM7 6H4v1a3 3 0 003 3M17 6h3v1a3 3 0 01-3 3"/></svg>
        @break
    @case('users')
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-4-4M9 20H4v-1a4 4 0 014-4h2a4 4 0 014 4v1H9zm6-9a3 3 0 100-6 3 3 0 000 6zm-6 0a3 3 0 100-6 3 3 0 000 6z"/></svg>
        @break
    @case('chart')
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 3 5-5"/></svg>
        @break
    @case('avatar')
        <div class="relative">
            <x-avatar :user="$user" size="xs" />
            @if (($pending ?? 0) > 0)
                <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-green border-2 border-bg"></span>
            @endif
        </div>
        @break
@endswitch
