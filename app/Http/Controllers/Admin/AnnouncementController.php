<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\PanelNotification;
use Pterodactyl\Services\Notifications\Notifier;
use Pterodactyl\Services\Notifications\StatusPage;

/**
 * Announcements to every user (maintenance, updates, news): they appear in the bell of the panel, and on the public
 * status page when asked. Also the settings of the status page.
 */
class AnnouncementController extends Controller
{
    public function __construct(private Notifier $notifier, private StatusPage $status, private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.announcements', [
            'announcements' => PanelNotification::query()->whereNull('user_id')->latest('created_at')->limit(50)->get(),
            'statusEnabled' => $this->status->enabled(),
            'statusTitle' => $this->status->title(),
            'statusNodes' => $this->status->showNodes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:info,maintenance,update,warning,success'],
            'title' => ['required', 'string', 'max:191'],
            'body' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#i'],
        ]);
        $this->notifier->broadcast($data['kind'], $data['title'], $data['body'] ?? null, $data['url'] ?? null, $request->boolean('public'));
        $this->alert->success('The announcement was sent to every user.')->flash();

        return redirect()->route('admin.announcements');
    }

    public function delete(int $id): RedirectResponse
    {
        PanelNotification::query()->whereNull('user_id')->whereKey($id)->delete();
        $this->alert->success('The announcement was removed.')->flash();

        return redirect()->route('admin.announcements');
    }

    public function saveStatus(Request $request): RedirectResponse
    {
        $request->validate(['title' => ['nullable', 'string', 'max:80']]);
        $this->status->save($request->boolean('enabled'), (string) $request->input('title', ''), $request->boolean('show_nodes'));
        $this->alert->success('The status page was saved.')->flash();

        return redirect()->route('admin.announcements');
    }
}
