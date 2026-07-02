<?php

namespace App\Http\Controllers\Admin\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\LegalDocument;
use App\Services\Privacy\LegalDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Admin de versiones de documentos legales.
 *
 * Solo admin (middleware 'admin'). No hay "editar": se publica una versión nueva.
 */
class LegalDocumentController extends Controller
{
    public function index(): View
    {
        $documents = LegalDocument::orderBy('type')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('type');

        return view('admin.privacy.legal.index', [
            'documents' => $documents,
            'types'     => LegalDocument::TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        $type = $request->query('type');
        abort_unless($type === null || array_key_exists($type, LegalDocument::TYPES), 404);

        // Pre-carga el contenido vigente como punto de partida para la nueva versión.
        $current = $type ? LegalDocument::currentFor($type) : null;

        return view('admin.privacy.legal.create', [
            'types'   => LegalDocument::TYPES,
            'type'    => $type,
            'current' => $current,
        ]);
    }

    public function store(Request $request, LegalDocumentService $service): RedirectResponse
    {
        $data = $request->validate([
            'type'               => ['required', Rule::in(array_keys(LegalDocument::TYPES))],
            'version'            => ['required', 'string', 'max:20'],
            'title'              => ['required', 'string', 'max:255'],
            'content'            => ['required', 'string'],
            'summary_of_changes' => ['nullable', 'string', 'max:2000'],
        ]);

        // La versión debe ser única por tipo (no reescribir una publicada).
        $exists = LegalDocument::ofType($data['type'])->where('version', $data['version'])->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['version' => 'Ya existe esa versión para este documento.']);
        }

        $service->publish($data, $request->user()->id);

        return redirect()->route('admin.legal.index')
            ->with('status', 'Versión publicada. Los usuarios deberán re-aceptar si corresponde.');
    }
}
