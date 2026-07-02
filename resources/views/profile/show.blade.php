@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <p class="eyebrow">Mi cuenta</p>
    <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-3 mb-6">Mi perfil</h1>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif

    {{-- Foto de perfil --}}
    <div class="bg-white border border-line rounded-md shadow-card p-6 sm:p-8 mb-6">
        <div class="flex flex-col sm:flex-row items-center gap-6">
            <x-avatar :user="auth()->user()" size="xl" />
            <div class="flex-1 w-full">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Foto de perfil</p>
                <p class="text-[13px] text-ink-soft mt-1">JPG, PNG o WEBP · máximo 2 MB.</p>
                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data"
                      class="mt-3 flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required
                           class="text-[13px] file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-pitch file:text-bone file:font-display file:font-semibold file:uppercase file:text-[12px] file:cursor-pointer">
                    <x-btn type="submit" variant="primary" size="sm">Subir foto</x-btn>
                </form>
                @error('avatar')<p class="text-[12px] text-alerta mt-2">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

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
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Rol</p>
                <p class="font-display font-bold text-display-s text-pitch uppercase mt-1">{{ auth()->user()->role }}</p>
            </div>
            @if (auth()->user()->futgo_id)
                <div>
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Identificador FUTGO</p>
                    <p class="font-mono font-bold text-body text-pitch mt-1 tracking-wider">{{ auth()->user()->futgo_id }}</p>
                </div>
            @endif
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

            <div class="flex flex-col gap-1.5">
                <label for="document" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Documento de identidad <span class="text-ink-mute normal-case">(opcional)</span></label>
                <input id="document" name="document" type="text" maxlength="40"
                       value="{{ old('document', auth()->user()->document) }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('document') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                <p class="text-[12px] text-ink-mute">Refuerza la validación de identidad antifraude. Nunca se expone en el QR ni en enlaces.</p>
                @error('document')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="flex flex-col gap-1.5">
                    <label for="city" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Ciudad <span class="text-ink-mute normal-case">(FutGO Social)</span></label>
                    <input id="city" name="city" type="text" maxlength="120"
                           value="{{ old('city', auth()->user()->city) }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('city') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    <p class="text-[12px] text-ink-mute">Tu ciudad alimenta el Feed y las oportunidades cercanas.</p>
                    @error('city')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="play_level" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Nivel de juego</label>
                    <select id="play_level" name="play_level"
                            class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                        <option value="">Sin declarar</option>
                        @foreach (\App\Models\User::PLAY_LEVELS as $lvl)
                            <option value="{{ $lvl }}" @selected(old('play_level', auth()->user()->play_level) === $lvl)>{{ ucfirst(str_replace('_', ' ', $lvl)) }}</option>
                        @endforeach
                    </select>
                    <p class="text-[12px] text-ink-mute">Es el filtro del matching de oportunidades.</p>
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-body-s">
                <input type="checkbox" name="notifications_enabled" value="1"
                       @checked(auth()->user()->notifications_enabled)
                       class="w-[18px] h-[18px] rounded-sm accent-pitch border-line">
                <span class="text-ink">Recibir notificaciones por email</span>
            </label>

            <label class="inline-flex items-start gap-2 text-body-s">
                <input type="checkbox" name="accepts_direct_messages" value="1"
                       @checked(auth()->user()->accepts_direct_messages ?? true)
                       class="w-[18px] h-[18px] rounded-sm accent-pitch border-line mt-0.5">
                <div>
                    <span class="text-ink">Recibir mensajes directos de otros jugadores</span>
                    <p class="text-[12px] text-ink-mute mt-0.5">Si lo desactivás, los demás no podrán iniciarte conversaciones desde tu ficha pública.</p>
                </div>
            </label>

            <div class="flex justify-end">
                <x-btn type="submit" variant="primary">Guardar cambios</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
