<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Services\Privacy\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Historial de consentimientos + preferencia de marketing.
 */
class ConsentController extends Controller
{
    public function __construct(private ConsentService $consents)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('privacy.center.consents', [
            'history'         => $this->consents->history($user),
            'marketingActive' => $this->consents->hasMarketingConsent($user),
        ]);
    }

    public function updateMarketing(Request $request): RedirectResponse
    {
        $accepted = $request->boolean('marketing');

        $this->consents->updateMarketing($request->user(), $accepted, $request);

        \App\Services\Privacy\AuditLogger::record('consent_accepted', $request->user(), null, [
            'type' => 'marketing', 'accepted' => $accepted,
        ]);

        return back()->with('status', $accepted
            ? 'Vas a recibir nuestras comunicaciones.'
            : 'Dejaste de recibir comunicaciones comerciales.');
    }
}
