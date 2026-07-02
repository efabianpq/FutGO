@props([
    'followable',          // modelo seguible (Club, User, Tournament)
    'type',                // alias morph: club | user | tournament
    'size' => 'sm',        // tamaño del botón
])

@auth
@php
    $svc        = app(\App\Services\Social\FollowService::class);
    $viewer     = auth()->user();
    // Un jugador no se sigue a sí mismo: no mostramos el botón en ese caso.
    $isSelf     = $type === 'user' && (int) $followable->getKey() === (int) $viewer->id;
    $following  = ! $isSelf && $svc->isFollowing($viewer, $followable);
    $count      = $svc->followerCount($followable);
@endphp

@unless ($isSelf)
    <form method="POST" action="{{ route('social.follow.toggle', ['type' => $type, 'id' => $followable->getKey()]) }}" class="inline-block">
        @csrf
        <button type="submit"
                class="btn btn-{{ $size }} {{ $following ? 'btn-secondary' : 'btn-primary' }}"
                title="{{ $following ? 'Dejar de seguir' : 'Seguir para recibir novedades en tu Feed' }}">
            @if ($following)
                <x-icon name="check" class="w-4 h-4 inline -mt-0.5" /> Siguiendo
            @else
                <x-icon name="plus" class="w-4 h-4 inline -mt-0.5" /> Seguir
            @endif
            @if ($count > 0)
                <span class="opacity-70 font-mono text-[11px]">· {{ $count }}</span>
            @endif
        </button>
    </form>
@endunless
@endauth
