@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <p class="eyebrow">Mi cuenta</p>
    <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-3 mb-6">Mi perfil</h1>

    <div class="bg-white border border-line rounded-md shadow-card p-6 sm:p-8 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pb-5 border-b border-line-soft">
            <div>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Nombre</p>
                <p class="font-display font-bold text-display-s text-ink mt-1">{{ auth()->user()->name }}</p>
            </div>
            <div>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Email</p>
                <p class="font-mono text-body-s text-ink mt-1 break-all">{{ auth()->user()->email }}</p>
            </div>
            <div>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Código usado</p>
                <p class="font-mono font-bold text-body text-pitch mt-1">{{ auth()->user()->invitation_code ?? '—' }}</p>
            </div>
            <div>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Rol</p>
                <p class="font-display font-bold text-display-s text-pitch uppercase mt-1">{{ auth()->user()->role }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div class="flex flex-col gap-1.5">
                <label for="phone_whatsapp" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Teléfono WhatsApp</label>
                <input id="phone_whatsapp" name="phone_whatsapp" type="tel" required inputmode="numeric" pattern="[0-9]{7,15}"
                       value="{{ old('phone_whatsapp', auth()->user()->phone_whatsapp) }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('phone_whatsapp') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                <p class="text-[12px] text-ink-mute">Solo números, 7 a 15 dígitos.</p>
                @error('phone_whatsapp')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <label class="inline-flex items-center gap-2 text-body-s">
                <input type="checkbox" name="notifications_enabled" value="1"
                       @checked(auth()->user()->notifications_enabled)
                       class="w-[18px] h-[18px] rounded-sm accent-pitch border-line">
                <span class="text-ink">Recibir notificaciones por email</span>
            </label>

            <div class="flex justify-end">
                <x-btn type="submit" variant="primary">Guardar cambios</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
