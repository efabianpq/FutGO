@extends('layouts.app')
@section('title', 'Publicar versión legal · Admin')

@section('content')
@include('admin._nav')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-text mb-6">Publicar nueva versión</h1>

    <form method="POST" action="{{ route('admin.legal.store') }}" class="space-y-5 bg-surface border border-border rounded-md p-6">
        @csrf

        <div class="flex flex-col gap-1.5">
            <label class="text-[12px] font-semibold text-muted uppercase">Documento</label>
            <select name="type" class="input">
                @foreach($types as $t => $label)
                    <option value="{{ $t }}" @selected(old('type', $type) === $t)>{{ $label }}</option>
                @endforeach
            </select>
            @error('type')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-[12px] font-semibold text-muted uppercase">Versión</label>
            <input name="version" value="{{ old('version') }}" placeholder="1.1" class="input">
            <p class="text-[12px] text-muted">Al cambiar la versión vigente de Términos o Privacidad, los usuarios deberán re-aceptar.</p>
            @error('version')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-[12px] font-semibold text-muted uppercase">Título</label>
            <input name="title" value="{{ old('title', $current?->title) }}" class="input">
            @error('title')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-[12px] font-semibold text-muted uppercase">Resumen de cambios (opcional)</label>
            <textarea name="summary_of_changes" rows="2" class="input">{{ old('summary_of_changes') }}</textarea>
            @error('summary_of_changes')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-[12px] font-semibold text-muted uppercase">Contenido (Markdown)</label>
            <textarea name="content" rows="16" class="input font-mono text-[13px]">{{ old('content', $current?->content) }}</textarea>
            @error('content')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary">Publicar versión</button>
            <a href="{{ route('admin.legal.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
