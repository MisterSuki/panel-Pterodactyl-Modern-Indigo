<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Landing\LandingContent;

/**
 * Where the administration changes the home page that visitors see before they sign in (see LandingContent). It needs the
 * right to manage the settings of the panel (the "admin.can:settings" of the routes).
 */
class LandingController extends Controller
{
    public function __construct(private LandingContent $landing, private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.landing', [
            'enabled' => $this->landing->enabled(),
            'content' => $this->landing->get(app()->getLocale()),
            'icons' => LandingContent::ICONS,
            'slots' => LandingContent::MAX_FEATURES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'features' => ['nullable', 'array', 'max:' . LandingContent::MAX_FEATURES],
            'features.*.title' => ['nullable', 'string', 'max:200'],
            'features.*.text' => ['nullable', 'string', 'max:1000'],
            'features.*.icon' => ['nullable', 'string', 'max:20'],
        ]);

        if ($request->has('reset')) {
            $this->landing->reset();
            $this->landing->setEnabled($request->boolean('enabled'));
            $this->alert->success('The home page went back to its original texts.')->flash();

            return redirect()->route('admin.home-page');
        }

        $this->landing->setEnabled($request->boolean('enabled'));
        $this->landing->save($request->all());
        $this->alert->success('The home page was saved.')->flash();

        return redirect()->route('admin.home-page');
    }
}
