@extends('layouts.app')
@section('title', 'Eliminar mi cuenta')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 py-8">
    <a href="{{ route('privacidad.centro') }}" class="text-[13px] text-muted hover:text-text">&larr; Centro de Privacidad</a>
    <h1 class="text-2xl font-bold text-text mt-3 mb-1">Eliminar mi cuenta</h1>

    @if(session('status'))
        <div class="my-4 p-3 rounded-sm bg-primary/10 text-primary text-sm">{{ session('status') }}</div>
    @endif

    @if($pending && $pending->status === \App\Models\Privacy\DataRequest::STATUS_PROCESSING)
        {{-- Estado: eliminación programada (periodo de gracia) --}}
        <div class="bg-surface border border-alerta/40 rounded-md p-5 mt-4">
            <h2 class="font-bold text-alerta mb-2">Eliminación programada</h2>
            <p class="text-[14px] text-text">Tu cuenta se eliminará el <strong>{{ $pending->executes_at->format('d/m/Y') }}</strong>
               ({{ $pending->executes_at->diffForHumans() }}).</p>
            <p class="text-[13px] text-muted mt-2">Puedes cancelar en cualquier momento antes de esa fecha.</p>

            <form method="POST" action="{{ route('privacidad.eliminar.cancelar') }}" class="mt-4">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-primary">Cancelar la eliminación</button>
            </form>
        </div>
    @elseif($pending && $pending->status === \App\Models\Privacy\DataRequest::STATUS_PENDING)
        {{-- Estado: esperando código --}}
        <div class="bg-surface border border-border rounded-md p-5 mt-4">
            <h2 class="font-bold text-text mb-2">Ingresa el código</h2>
            <p class="text-[14px] text-muted mb-4">Te enviamos un código de confirmación a tu correo.</p>

            <form method="POST" action="{{ route('privacidad.eliminar.verificar') }}" class="space-y-4">
                @csrf
                <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                       class="input text-center tracking-[0.5em] font-mono text-lg">
                @error('code')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-primary w-full">Confirmar eliminación</button>
            </form>

            <form method="POST" action="{{ route('privacidad.eliminar.cancelar') }}" class="mt-3">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-full text-center text-[13px] text-muted hover:text-text">Cancelar</button>
            </form>
        </div>
    @else
        {{-- Estado inicial: confirmar con contraseña --}}
        <div class="bg-surface border border-border rounded-md p-5 mt-4 space-y-4">
            <div class="text-[14px] text-text space-y-2">
                <p>Al eliminar tu cuenta:</p>
                <ul class="list-disc pl-5 text-muted space-y-1">
                    <li>Se anonimizan tu nombre, correo, teléfono, documento y foto.</li>
                    <li>Se cierran todas tus sesiones.</li>
                    <li>Tus estadísticas históricas se conservan de forma anónima ("Jugador eliminado") para no afectar los resultados de otros.</li>
                    <li>La eliminación se completa tras un periodo de gracia de {{ config('privacy.deletion_grace_days', 30) }} días, durante el cual puedes cancelar.</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('privacidad.eliminar.solicitar') }}" class="space-y-4">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label class="text-[12px] font-semibold text-muted uppercase">Confirma tu contraseña</label>
                    <input type="password" name="password" class="input">
                    @error('password')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="confirm" value="1" class="mt-0.5 h-4 w-4 rounded border-border text-alerta focus:ring-alerta">
                    <span class="text-[13px] text-muted">Entiendo que esta acción es irreversible.</span>
                </label>
                @error('confirm')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror

                <button type="submit" class="btn bg-alerta text-white hover:opacity-90">Eliminar mi cuenta</button>
            </form>
        </div>
    @endif
</div>
@endsection
