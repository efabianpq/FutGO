@extends('layouts.app')

@section('title', 'Crear cuenta')

@section('content')
<div class="max-w-md mx-auto px-4 py-12 sm:py-16">
    <div class="text-center mb-8">
        <p class="eyebrow justify-center">Registro</p>
        <div class="flex items-center justify-center gap-2 mt-3">
            <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase">Crear cuenta</h1>
            <x-help-hint topic="register" />
        </div>
        <p class="text-body-s text-ink-soft mt-2">Crea tu cuenta gratis y empieza a jugar.</p>
    </div>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <form method="POST" action="{{ route('register.store') }}" class="space-y-5"
              x-data="{ birthdate: '{{ old('birthdate') }}', get isMinor() { if (!this.birthdate) return false; const d = new Date(this.birthdate); const age = (Date.now() - d.getTime()) / 31557600000; return age < 18; } }">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="nombre" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Nombre</label>
                    <input id="nombre" name="nombre" type="text" required autofocus value="{{ old('nombre') }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('nombre') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    @error('nombre')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="apellido" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Apellido</label>
                    <input id="apellido" name="apellido" type="text" required value="{{ old('apellido') }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('apellido') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    @error('apellido')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="email" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Correo electrónico</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('email') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('email')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="telefono" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Teléfono WhatsApp</label>
                <input id="telefono" name="telefono" type="tel" required inputmode="numeric" pattern="[0-9]{7,15}"
                       value="{{ old('telefono') }}" placeholder="3001234567"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('telefono') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                <p class="text-[12px] text-ink-mute">Solo números, 7 a 15 dígitos.</p>
                @error('telefono')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="documento" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Documento <span class="text-ink-mute normal-case tracking-normal">(opcional)</span></label>
                <input id="documento" name="documento" type="text" inputmode="numeric"
                       value="{{ old('documento') }}" placeholder="Cédula / documento"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('documento') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                <p class="text-[12px] text-ink-mute">Si un capitán ya te anotó en un equipo, lo usamos para encontrar tu historial y dejarte reclamarlo.</p>
                @error('documento')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="birthdate" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Fecha de nacimiento</label>
                <input id="birthdate" name="birthdate" type="date" required x-model="birthdate" value="{{ old('birthdate') }}"
                       max="{{ now()->toDateString() }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('birthdate') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                <p class="text-[12px] text-ink-mute">Debes tener al menos {{ config('privacy.min_age', 14) }} años.</p>
                @error('birthdate')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            @if(config('privacy.parental_consent', true))
            <div class="flex flex-col gap-1.5" x-show="isMinor" x-cloak>
                <label for="guardian_email" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Correo del padre/madre o representante</label>
                <input id="guardian_email" name="guardian_email" type="email" x-bind:required="isMinor" value="{{ old('guardian_email') }}"
                       placeholder="representante@correo.com"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('guardian_email') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                <p class="text-[12px] text-ink-mute">Al ser menor de edad, tu representante confirmará el registro. Ver <a href="{{ route('menores') }}" target="_blank" class="text-pitch underline">Política para menores</a>.</p>
                @error('guardian_email')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="password" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Contraseña</label>
                    <input id="password" name="password" type="password" required
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('password') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    @error('password')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="password_confirmation" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Confirmar</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                </div>
            </div>

            <div class="space-y-2.5 pt-1">
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="accept_terms" value="1" {{ old('accept_terms') ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-line text-pitch focus:ring-pitch">
                    <span class="text-[13px] text-ink-soft">He leído y acepto los <a href="{{ route('terminos') }}" target="_blank" class="text-pitch font-semibold underline">Términos y Condiciones</a>.</span>
                </label>
                @error('accept_terms')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="accept_privacy" value="1" {{ old('accept_privacy') ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-line text-pitch focus:ring-pitch">
                    <span class="text-[13px] text-ink-soft">Acepto la <a href="{{ route('privacidad') }}" target="_blank" class="text-pitch font-semibold underline">Política de Privacidad</a>.</span>
                </label>
                @error('accept_privacy')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="accept_marketing" value="1" {{ old('accept_marketing') ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-line text-pitch focus:ring-pitch">
                    <span class="text-[13px] text-ink-soft">Acepto recibir novedades y comunicaciones de FutGO <span class="text-ink-mute">(opcional)</span>.</span>
                </label>
            </div>

            <x-btn type="submit" variant="primary" size="lg" class="w-full">Crear cuenta</x-btn>
        </form>

        <p class="text-body-s text-ink-soft text-center mt-6">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="text-pitch font-display font-bold uppercase tracking-wide-cta text-[13px] hover:underline">Iniciar sesión</a>
        </p>
    </div>
</div>
@endsection
