@props([
    'title' => '',
    'dark' => false,   // en sección oscura
])

{{--
    Fila de característica: ícono (slot 'icon') + título + descripción.
    Pensada para vivir dentro de <x-landing.feature-list>.
--}}
<div @class([
        'flex gap-3.5 py-3.5 border-b last:border-b-0',
        'border-white/10' => $dark,
        'border-border-soft' => ! $dark,
    ])>
    <div class="w-[38px] h-[38px] rounded-md shrink-0 flex items-center justify-center"
         @style([
            'background:rgba(0,200,83,.18); color:#5ee08a' => $dark,
            'background:var(--color-green-tint); color:var(--color-green-strong)' => ! $dark,
         ])>
        <span class="w-5 h-5 inline-flex">{{ $icon ?? '' }}</span>
    </div>
    <div>
        <h4 @class(['text-[16px] font-bold mb-0.5', 'text-[#eef3ee]' => $dark, 'text-text' => ! $dark])>{{ $title }}</h4>
        <p @class(['text-[14px] leading-normal m-0', 'text-[#eef3ee]/70' => $dark, 'text-muted' => ! $dark])>{{ $slot }}</p>
    </div>
</div>
