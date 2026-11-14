<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\PanelNotification;
use Pterodactyl\Models\StatusMaintenance;
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
            'statusVps' => $this->status->showVps(),
            'components' => $this->status->components(),
            'maintenances' => Schema::hasTable('status_maintenances')
                ? StatusMaintenance::query()->where('ends_at', '>=', now()->subDays(30))->orderByDesc('starts_at')->limit(30)->get()
                : collect(),
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
        $this->status->save($request->boolean('enabled'), (string) $request->input('title', ''), $request->boolean('show_nodes'), $request->boolean('show_vps'));
        $this->alert->success('The status page was saved.')->flash();

        return redirect()->route('admin.announcements');
    }

    /**
     * Plans a maintenance (or starts one now): the status page shows it, and what it concerns shows "Maintenance"
     * instead of a failure while it runs. Every user can be told in the bell.
     */
    public function storeMaintenance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'body' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'components' => ['required', 'array', 'min:1'],
            'components.*' => ['string', Rule::in(array_keys($this->status->components()))],
        ]);

        $timezone = (string) config('app.timezone');
        $start = Carbon::parse($data['starts_at'], $timezone);
        $end = Carbon::parse($data['ends_at'], $timezone);
        if ($end->lte($start)) {
            $this->alert->danger('The end of the maintenance must come after its start.')->flash();

            return redirect()->route('admin.announcements')->withInput();
        }

        StatusMaintenance::query()->create([
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'starts_at' => $start,
            'ends_at' => $end,
            'components' => array_values(array_unique($data['components'])),
        ]);

        if ($request->boolean('notify')) {
            $when = $start->format('d/m H:i') . ' → ' . ($end->isSameDay($start) ? $end->format('H:i') : $end->format('d/m H:i'));
            $body = trim(($data['body'] ?? '') . "\n" . $when);
            $this->notifier->broadcast('maintenance', $data['title'], $body, $this->status->enabled() ? '/status' : null, false);
        }

        $this->alert->success('The maintenance was saved: the status page shows it.')->flash();

        return redirect()->route('admin.announcements');
    }

    /**
     * Ends a maintenance in progress now (one still to come is removed).
     */
    public function endMaintenance(int $id): RedirectResponse
    {
        /** @var StatusMaintenance $maintenance */
        $maintenance = StatusMaintenance::query()->findOrFail($id);
        if ($maintenance->isUpcoming()) {
            $maintenance->delete();
        } elseif ($maintenance->isActive()) {
            $maintenance->update(['ends_at' => now()]);
        }
        $this->alert->success('The maintenance is over.')->flash();

        return redirect()->route('admin.announcements');
    }

    public function deleteMaintenance(int $id): RedirectResponse
    {
        StatusMaintenance::query()->whereKey($id)->delete();
        $this->alert->success('The maintenance was removed.')->flash();

        return redirect()->route('admin.announcements');
    }
}
