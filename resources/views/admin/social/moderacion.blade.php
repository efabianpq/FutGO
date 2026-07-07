@extends('layouts.app')
@section('title', 'Moderación · Admin')

@section('content')
@include('admin._nav')

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">Administración</p>
        <div class="flex items-center gap-2 mt-1">
            <h1 class="font-display font-bold text-display-s text-pitch uppercase">Moderación de contenido</h1>
            <x-help-hint topic="admin.social.moderacion.index" />
        </div>
        <p class="text-ink-soft text-[14px] mt-1">Revisión manual de reportes de la comunidad.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    {{-- Pendientes --}}
    <section class="mb-10">
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Pendientes ({{ $pending->count() }})</p>

        @if ($pending->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">No hay reportes pendientes.</div>
        @else
            <div class="flex flex-col gap-4">
                @foreach ($pending as $report)
                @php
                    $reportable = $report->reportable;
                    $type = class_basename($reportable ?? '');
                    $typeLabel = match($type) {
                        'Opportunity' => 'Oportunidad',
                        'Message'     => 'Mensaje',
                        'User'        => 'Jugador',
                        'Club'        => 'Club',
                        default       => $type,
                    };
                @endphp
                <div class="bg-white border border-line rounded-md shadow-card p-5" x-data="{ open: false }">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                        <div>
                            <span class="inline-block bg-alerta/10 text-alerta-deep font-mono text-[11px] uppercase tracking-wide-label px-2 py-0.5 rounded mr-2">{{ $typeLabel }}</span>
                            <span class="font-display font-semibold text-pitch text-[14px]">
                                {{ $report->reason }}
                            </span>
                        </div>
                        <span class="text-ink-mute text-[12px] font-mono whitespace-nowrap">{{ $report->created_at->format('d/m/Y H:i') }}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[13px] mb-3">
                        <div>
                            <p class="text-ink-mute text-[11px] uppercase tracking-wide-label font-mono mb-0.5">Reportado por</p>
                            <p class="font-semibold text-pitch">{{ $report->reporter?->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-ink-mute text-[11px] uppercase tracking-wide-label font-mono mb-0.5">Entidad reportada</p>
                            @if ($reportable instanceof \App\Models\Social\Opportunity)
                                <p class="font-semibold text-pitch">
                                    Oportunidad #{{ $reportable->id }}
                                    <span class="text-ink-soft font-normal">— {{ $reportable->typeLabel() }}</span>
                                </p>
                                @if ($reportable->is_hidden)
                                    <span class="text-[11px] text-alerta font-mono">[ya oculta]</span>
                                @endif
                            @elseif ($reportable instanceof \App\Models\User)
                                <p class="font-semibold text-pitch">{{ $reportable->name }}</p>
                                @if ($reportable->is_suspended)
                                    <span class="text-[11px] text-alerta font-mono">[ya suspendido]</span>
                                @endif
                            @elseif ($reportable instanceof \App\Models\Torneos\Club)
                                <p class="font-semibold text-pitch">{{ $reportable->name }}</p>
                            @elseif ($reportable instanceof \App\Models\Social\Message)
                                <p class="font-semibold text-pitch">Mensaje #{{ $reportable->id }}</p>
                                @if ($reportable->is_hidden)
                                    <span class="text-[11px] text-alerta font-mono">[ya oculto]</span>
                                @endif
                            @else
                                <p class="text-ink-soft">Entidad eliminada</p>
                            @endif
                        </div>
                    </div>

                    @if ($report->details)
                        <p class="text-[13px] text-ink-soft mb-3 italic">"{{ $report->details }}"</p>
                    @endif

                    <button @click="open = !open"
                        class="text-[13px] font-display font-semibold text-pitch-accent underline underline-offset-2">
                        <span x-text="open ? 'Cerrar formulario' : 'Tomar acción'"></span>
                    </button>

                    <div x-show="open" x-cloak class="mt-4 border-t border-line pt-4">
                        <form method="POST" action="{{ route('admin.social.moderacion.resolve', $report) }}">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-[12px] font-mono uppercase tracking-wide-label text-ink-mute mb-1">Acción *</label>
                                    <select name="action" required
                                        class="block w-full rounded-md border border-line px-3 py-2 text-[14px] focus:outline-none focus:ring-1 focus:ring-pitch"
                                        x-model="action" x-data="{ action: '' }">
                                        <option value="">Elige una acción…</option>
                                        <option value="dismissed">Desestimar (reporte infundado)</option>
                                        <option value="hidden">Ocultar contenido</option>
                                        <option value="suspended">Suspender usuario</option>
                                    </select>
                                </div>
                                <div x-data="{ action: '' }" x-show="$el.closest('form').querySelector('[name=action]').value === 'suspended'">
                                    <label class="block text-[12px] font-mono uppercase tracking-wide-label text-ink-mute mb-1">Duración suspensión (días, vacío=indefinida)</label>
                                    <input type="number" name="suspend_days" min="1" max="365" placeholder="Ej: 7"
                                        class="block w-full rounded-md border border-line px-3 py-2 text-[14px] focus:outline-none focus:ring-1 focus:ring-pitch">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-[12px] font-mono uppercase tracking-wide-label text-ink-mute mb-1">Notas internas (visible solo para admins)</label>
                                <textarea name="admin_notes" maxlength="500" rows="2"
                                    class="block w-full rounded-md border border-line px-3 py-2 text-[14px] focus:outline-none focus:ring-1 focus:ring-pitch"
                                    placeholder="Justificación de la decisión…"></textarea>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit"
                                    class="px-4 py-2 bg-pitch text-bone font-display font-semibold text-[13px] rounded-md hover:bg-pitch-deep transition-colors">
                                    Confirmar
                                </button>
                                <button type="button" @click="open = false"
                                    class="px-4 py-2 border border-line text-pitch font-display font-semibold text-[13px] rounded-md hover:bg-pitch-mist transition-colors">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Historial reciente --}}
    <section>
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Historial reciente (últimos 30)</p>
        @if ($resolved->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">Sin historial aún.</div>
        @else
            <div class="bg-white border border-line rounded-md shadow-card overflow-hidden overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-pitch-mist">
                        <tr>
                            <th class="text-left px-4 py-3 font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Fecha</th>
                            <th class="text-left px-4 py-3 font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Motivo</th>
                            <th class="text-left px-4 py-3 font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Acción</th>
                            <th class="text-left px-4 py-3 font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Admin</th>
                            <th class="text-left px-4 py-3 font-mono text-[11px] uppercase tracking-wide-label text-ink-mute hidden sm:table-cell">Notas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($resolved as $report)
                            <tr class="hover:bg-pitch-mist/40">
                                <td class="px-4 py-3 whitespace-nowrap text-ink-mute font-mono text-[11px]">{{ $report->reviewed_at?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $report->reason }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-mono uppercase
                                        {{ $report->resolution_action === 'suspended' ? 'bg-alerta/10 text-alerta-deep' : ($report->resolution_action === 'hidden' ? 'bg-amber-50 text-amber-700' : 'bg-gol/10 text-gol-deep') }}">
                                        {{ $report->resolutionActionLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $report->reviewer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-ink-soft hidden sm:table-cell">{{ Str::limit($report->admin_notes, 60) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

</div>
@endsection
