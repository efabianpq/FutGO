{{-- Set centralizado de iconos SVG (outline, stroke-2px), mismo estilo que nav.blade.php.
     Uso: <x-icon name="trophy" class="w-5 h-5" />
     Ver mapeo completo emoji→icono en docs/HISTORIAL_SESIONES.md (limpieza de iconografía). --}}
@props([
    'name' => null,
    'class' => 'w-5 h-5',
])
@switch($name)
    @case('ball')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.5 4.5L19 8l-3 3.5L17 16l-5-2-5 2 1-4.5L5 8l4.5-.5z"/></svg>
        @break
    @case('trophy')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM7 6H4v1a3 3 0 003 3M17 6h3v1a3 3 0 01-3 3"/></svg>
        @break
    @case('megaphone')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c2.32.198 4.594.688 6.75 1.44l3.75 1.5V4.31l-3.75 1.5a24 24 0 01-6.75 1.44m0 9.18v-9.18m0 9.18c-1.03-.02-2.062.078-3.077.292a3.375 3.375 0 00-2.6 3.285A3.075 3.075 0 007.398 21h1.978a48 48 0 003.056-.223 2.25 2.25 0 001.867-1.983c.03-.27.076-.532.15-.784A1.5 1.5 0 0113.5 15.75"/></svg>
        @break
    @case('calendar')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
        @break
    @case('medal')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="15" r="6"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 3.5L8 10.2M14.5 3.5L16 10.2M12 12.5v5"/></svg>
        @break
    @case('handshake')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.27-.63 2.39-1.59 3.07a3.745 3.745 0 01-1.05 3.29 3.745 3.745 0 01-3.29 1.05A3.745 3.745 0 0112 21c-1.27 0-2.39-.63-3.07-1.59a3.746 3.746 0 01-3.29-1.05 3.745 3.745 0 01-1.05-3.29A3.745 3.745 0 013 12c0-1.27.63-2.39 1.59-3.07a3.745 3.745 0 011.05-3.29 3.746 3.746 0 013.29-1.05A3.746 3.746 0 0112 3c1.27 0 2.39.63 3.07 1.59a3.746 3.746 0 013.29 1.05 3.746 3.746 0 011.05 3.29A3.745 3.745 0 0121 12z"/></svg>
        @break
    @case('bolt')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
        @break
    @case('map-pin')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.14-7.5 11.25-7.5 11.25S4.5 17.64 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
        @break
    @case('assist')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
        @break
    @case('card')
        <svg class="{{ $class }}" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="3" width="12" height="18" rx="2.5"/></svg>
        @break
    @case('thumb-up')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.63 10.5c.8 0 1.53-.45 2.03-1.08a9.04 9.04 0 012.86-2.4c.72-.38 1.35-.96 1.65-1.72.2-.5.32-1.06.32-1.67V3a.75.75 0 01.75-.75A2.25 2.25 0 0116.5 4.5c0 1.15-.26 2.24-.72 3.22-.27.56.1 1.28.72 1.28h3.13c1.02 0 1.94.7 2.05 1.72.05.42.07.85.07 1.28a11.95 11.95 0 01-2.65 7.52c-.39.48-.99.73-1.6.73H13.48c-.48 0-.96-.08-1.42-.23l-3.11-1.04a4.5 4.5 0 00-1.42-.23H5.9M5.9 18.75c.08.2.17.4.27.6.35.7-.09 1.63-.87 1.63H3.75c-.97 0-1.75-.79-1.75-1.75V11c0-.97.78-1.75 1.75-1.75h1.56c.34 0 .68.1.97.28"/></svg>
        @break
    @case('thumb-down')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.37 13.5c-.8 0-1.53.45-2.03 1.08a9.04 9.04 0 01-2.86 2.4c-.72.38-1.35.96-1.65 1.72-.2.5-.32 1.06-.32 1.67V21a.75.75 0 01-.75.75 2.25 2.25 0 01-2.25-2.25c0-1.15.26-2.24.72-3.22.27-.56-.1-1.28-.72-1.28H4.24c-1.02 0-1.94-.7-2.05-1.72A11 11 0 012.12 12a11.95 11.95 0 012.65-7.52c.39-.48.99-.73 1.6-.73h5.14c.48 0 .96.08 1.42.23l3.11 1.04c.46.15.94.23 1.42.23h2.04M18.1 5.25a1.72 1.72 0 01-.27-.6c-.35-.7.09-1.63.87-1.63h2.05c.97 0 1.75.79 1.75 1.75V13c0 .97-.78 1.75-1.75 1.75h-1.56c-.34 0-.68-.1-.97-.28"/></svg>
        @break
    @case('warning')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.3 3.38c-.87 1.5.22 3.37 1.95 3.37h14.7c1.73 0 2.82-1.87 1.95-3.37L13.95 3.38c-.87-1.5-3.03-1.5-3.9 0L2.7 16.13zM12 15.75h.007v.008H12v-.008z"/></svg>
        @break
    @case('check-circle')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25 6-6M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @break
    @case('check')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        @break
    @case('x-mark')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        @break
    @case('star')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.12 5.11c.07.18.25.3.44.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.19.56l1.29 5.39a.56.56 0 01-.84.6l-4.72-2.88a.56.56 0 00-.59 0l-4.72 2.88a.56.56 0 01-.84-.6l1.28-5.39a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44c.19-.05.37-.17.44-.35L11.48 3.5z"/></svg>
        @break
    @case('chat')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 10.5h7.5m-7.5 3H12m-9.75 1.51c0 1.6 1.12 3 2.7 3.23 1.13.16 2.27.29 3.43.38.35.02.67.21.86.5L12 21l2.76-4.13c.19-.29.5-.48.86-.5 1.16-.09 2.3-.22 3.43-.38 1.58-.24 2.7-1.63 2.7-3.23V6.74c0-1.6-1.12-3-2.7-3.23A48 48 0 0012 3c-2.39 0-4.74.17-7.04.51-1.58.24-2.71 1.64-2.71 3.24v6.02z"/></svg>
        @break
    @case('phone')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5h3A2.25 2.25 0 0115.75 3.75v16.5a2.25 2.25 0 01-2.25 2.25h-3a2.25 2.25 0 01-2.25-2.25V3.75A2.25 2.25 0 0110.5 1.5zM10.5 18h3"/></svg>
        @break
    @case('target')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.25"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="0.75" fill="currentColor"/></svg>
        @break
    @case('ban')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.64 5.64l12.72 12.72"/></svg>
        @break
    @case('inbox')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86c.5 0 .96.28 1.2.72l.5.98c.24.44.7.72 1.2.72h4.98c.5 0 .96-.28 1.2-.72l.5-.98c.24-.44.7-.72 1.2-.72h3.86M4.5 20.25h15a1.5 1.5 0 001.5-1.5V9.94a1.5 1.5 0 00-.09-.5L18.6 4.06a1.5 1.5 0 00-1.41-.98H6.81a1.5 1.5 0 00-1.41.98L3.09 9.44a1.5 1.5 0 00-.09.5v8.81a1.5 1.5 0 001.5 1.5z"/></svg>
        @break
    @case('lock')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
        @break
    @case('id-card')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5h-15A1.5 1.5 0 003 6v12a1.5 1.5 0 001.5 1.5zm6.75-10.5a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zM4.5 19.5v-1.13a3.38 3.38 0 013.38-3.37h1.5a3.38 3.38 0 013.37 3.37v1.13"/></svg>
        @break
    @case('shield')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 9.75m-3-7.04A11.96 11.96 0 013.6 6 12 12 0 003 9.75c0 5.59 3.82 10.29 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.31-.21-2.57-.6-3.75h-.15c-3.2 0-6.1-1.25-8.25-3.29z"/></svg>
        @break
    @case('trending-up')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.31 4.31a11.95 11.95 0 015.81-5.52l1.63-.79M21.75 8.25h-6.38M21.75 8.25v6.38"/></svg>
        @break
    @case('gear')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.59 3.94c.09-.54.56-.94 1.11-.94h2.59c.55 0 1.02.4 1.11.94l.22 1.28c.06.37.31.69.64.87.08.04.15.08.22.13.32.2.72.26 1.08.13l1.21-.46a1.13 1.13 0 011.37.49l1.3 2.25c.27.48.16 1.08-.26 1.43l-1 .83c-.29.24-.44.61-.43.99a7 7 0 010 .26c-.01.38.14.75.43.99l1 .83c.42.35.53.95.26 1.43l-1.3 2.25a1.12 1.12 0 01-1.37.49l-1.22-.46c-.35-.13-.75-.07-1.07.12-.07.05-.15.09-.22.13-.33.18-.58.5-.65.87l-.21 1.28c-.09.54-.56.94-1.11.94h-2.59c-.55 0-1.02-.4-1.11-.94l-.21-1.28c-.07-.37-.32-.69-.65-.87a6 6 0 01-.22-.13c-.32-.19-.72-.25-1.07-.12l-1.22.46a1.13 1.13 0 01-1.37-.49l-1.3-2.25a1.12 1.12 0 01.26-1.43l1-.83c.29-.24.44-.61.43-.99a7 7 0 010-.26c.01-.38-.14-.75-.43-.99l-1-.83a1.12 1.12 0 01-.26-1.43l1.3-2.25a1.13 1.13 0 011.37-.49l1.22.46c.35.13.75.07 1.07-.12.07-.05.15-.09.22-.13.33-.18.58-.5.65-.87l.21-1.28z"/><circle cx="12" cy="12" r="3"/></svg>
        @break
    @case('plus')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        @break
    @case('send')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.27 3.13A59.77 59.77 0 0121.49 12 59.77 59.77 0 013.27 20.88L6 12zm0 0h7.5"/></svg>
        @break
    @case('photo')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.16-5.16a2.25 2.25 0 013.18 0l5.16 5.16m-1.5-1.5l1.41-1.41a2.25 2.25 0 013.18 0l2.91 2.91M3 21h18a1.5 1.5 0 001.5-1.5V4.5A1.5 1.5 0 0021 3H3a1.5 1.5 0 00-1.5 1.5v15A1.5 1.5 0 003 21z"/><circle cx="15.75" cy="7.5" r="0.75" fill="currentColor"/></svg>
        @break
    @case('book')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.04A8.97 8.97 0 006 3.75c-1.05 0-2.06.18-3 .51v14.25A8.99 8.99 0 016 18c2.3 0 4.41.87 6 2.29m0-14.25a8.97 8.97 0 016-2.29c1.05 0 2.06.18 3 .51v14.25A8.99 8.99 0 0018 18a8.97 8.97 0 00-6 2.29m0-14.25v14.25"/></svg>
        @break
    @case('bug')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="14" r="6"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 4l1.5 3M15 4l-1.5 3M3.5 12H6m12 0h2.5M6 18l-2 2m14-2l2 2M9 10h6"/></svg>
        @break
    @case('lightbulb')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6 6 0 001.5-.19m-1.5.19a6 6 0 01-1.5-.19m3.75 7.48a12 12 0 01-4.5 0m3.75 2.38a14 14 0 01-3 0M14.25 18v-.19c0-.98.66-1.82 1.51-2.32a7.5 7.5 0 10-7.52 0c.85.5 1.51 1.34 1.51 2.32V18"/></svg>
        @break
    @case('clipboard')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 3.75h6a1.5 1.5 0 011.5 1.5v.75a1.5 1.5 0 01-1.5 1.5H9a1.5 1.5 0 01-1.5-1.5v-.75A1.5 1.5 0 019 3.75zM6.75 6h-.375A2.25 2.25 0 004.125 8.25v10.5A2.25 2.25 0 006.375 21h11.25a2.25 2.25 0 002.25-2.25V8.25A2.25 2.25 0 0017.625 6H17.25"/></svg>
        @break
    @case('heart')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.49-2.1-4.5-4.69-4.5-1.93 0-3.6 1.13-4.31 2.73-.72-1.6-2.38-2.73-4.31-2.73C5.1 3.75 3 5.76 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
        @break
    @case('sub-in')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 15.5V8m0 0l-3 3m3-3l3 3"/></svg>
        @break
    @case('sub-out')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8.5V16m0 0l-3-3m3 3l3-3"/></svg>
        @break
    @case('user')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/></svg>
        @break
    @case('bar-chart')
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5v-6.75m0 6.75h1.5m-1.5 0H3m1.5 0V13.5m6 6v-11.25m0 11.25h1.5m-1.5 0H9m1.5 0V8.25m6 11.25V4.5m0 15h1.5m-1.5 0H15m1.5 0V4.5"/></svg>
        @break
    @default
        <svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
@endswitch
