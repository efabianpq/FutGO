@php
    $isEdit = $venue !== null;
@endphp

<form method="POST" action="{{ $action }}" class="bg-white border border-line rounded-md shadow-card p-6 space-y-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-md px-4 py-3 text-red-700 text-[13px]">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div class="sm:col-span-2">
            <label class="block font-mono text-[10px] tracking-wide-label uppercase text-ink-mute mb-1">Nombre de la cancha <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $venue?->name) }}" required maxlength="120"
                   placeholder="Ej: Complejo Deportivo La Palma"
                   class="w-full h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] @error('name') border-red-400 @enderror">
        </div>

        <div>
            <label class="block font-mono text-[10px] tracking-wide-label uppercase text-ink-mute mb-1">Ciudad <span class="text-red-500">*</span></label>
            <input type="text" name="city" value="{{ old('city', $venue?->city) }}" required maxlength="80"
                   placeholder="Asunción"
                   class="w-full h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] @error('city') border-red-400 @enderror">
        </div>

        <div>
            <label class="block font-mono text-[10px] tracking-wide-label uppercase text-ink-mute mb-1">Dirección</label>
            <input type="text" name="address" value="{{ old('address', $venue?->address) }}" maxlength="200"
                   placeholder="Av. España 1234"
                   class="w-full h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] @error('address') border-red-400 @enderror">
        </div>

        <div>
            <label class="block font-mono text-[10px] tracking-wide-label uppercase text-ink-mute mb-1">Tipo de superficie</label>
            <select name="surface_type" class="w-full h-[40px] px-2 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                <option value="">Sin especificar</option>
                @foreach ($surfaces as $key => $label)
                    <option value="{{ $key }}" @selected(old('surface_type', $venue?->surface_type) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block font-mono text-[10px] tracking-wide-label uppercase text-ink-mute mb-1">Capacidad aproximada</label>
            <input type="number" name="approx_capacity" value="{{ old('approx_capacity', $venue?->approx_capacity) }}"
                   min="1" max="100000" placeholder="22"
                   class="w-full h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] @error('approx_capacity') border-red-400 @enderror">
        </div>

        <div class="sm:col-span-2">
            <label class="block font-mono text-[10px] tracking-wide-label uppercase text-ink-mute mb-1">Link de ubicación (Google Maps u otro)</label>
            <input type="url" name="maps_url" value="{{ old('maps_url', $venue?->maps_url) }}" maxlength="1000"
                   placeholder="https://maps.google.com/..."
                   class="w-full h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] @error('maps_url') border-red-400 @enderror">
        </div>

        @if ($isEdit)
            <div class="sm:col-span-2 flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $venue?->is_active ?? true))
                       class="h-4 w-4 rounded border-line text-pitch focus:ring-pitch">
                <label for="is_active" class="text-[14px] text-ink-soft">Cancha activa (visible en el catálogo)</label>
            </div>
        @endif
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary btn-sm">{{ $isEdit ? 'Guardar cambios' : 'Registrar cancha' }}</button>
        <a href="{{ route('social.canchas.index') }}" class="btn btn-secondary btn-sm">Cancelar</a>
    </div>
</form>
