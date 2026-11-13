<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\ServerMailBox;
use Pterodactyl\Models\ServerMailDomain;
use Pterodactyl\Services\Mail\MailHosting;

/**
 * Email seen from the administration: the names of the mail server, the limits, the agent and its state, and the
 * domains that have email.
 */
class MailController extends Controller
{
    public function __construct(private MailHosting $mail, private AlertsMessageBag $alert)
    {
    }

    public function index(Request $request): View
    {
        $hostname = $this->mail->hostname();
        $webmail = $this->mail->webmail();
        $agent = $this->mail->agent();

        // Where the names lead, and the reverse DNS of the machine: without it, Gmail and Outlook refuse the mail.
        $dns = $this->mail->addresses(array_filter([$hostname, $webmail]));
        $ip = (string) ($agent['ip'] ?? ($dns[$hostname][0] ?? ''));
        $ptr = $ip !== '' ? @gethostbyaddr($ip) : null;
        $ptr = is_string($ptr) && $ptr !== $ip ? strtolower(rtrim($ptr, '.')) : null;

        // The steps of the setup, each "done", "now" (what to do next), "wait" (the machine is working on it) or "todo".
        $named = $hostname !== '' && $webmail !== '';
        $pointed = $named && !empty($dns[$hostname]) && !empty($dns[$webmail]) && $dns[$hostname] === $dns[$webmail];
        $online = (bool) ($agent['online'] ?? false);
        $started = $online && !empty($agent['running']) && !empty($agent['certificate']) && !empty($agent['webmail_certificate']);
        $port25 = $agent['outbound_25'] ?? null;
        $steps = [
            'names' => $named ? 'done' : 'now',
            'dns' => $pointed ? 'done' : ($named ? 'now' : 'todo'),
            'install' => $online ? 'done' : ($pointed ? 'now' : 'todo'),
            'start' => $started ? 'done' : ($online ? 'wait' : 'todo'),
            'ptr' => $named && $ptr === $hostname ? 'done' : ($ip !== '' ? 'now' : 'todo'),
            'port25' => $port25 === true ? 'done' : ($port25 === false ? 'now' : ($online ? 'wait' : 'todo')),
        ];

        return view('admin.mail', [
            'steps' => $steps,
            'hostname' => $hostname,
            'webmail' => $webmail,
            'maxBoxes' => $this->mail->maxBoxes(),
            'quotaMb' => $this->mail->quotaMb(),
            'agent' => $agent,
            'hasToken' => $this->mail->hasToken(),
            'token' => $request->session()->get('mail_token'),
            'panelUrl' => rtrim((string) config('app.url'), '/'),
            'dns' => $dns,
            'ip' => $ip,
            'ptr' => $ptr,
            'domains' => ServerMailDomain::query()->with('server')->withCount('boxes')->orderBy('hostname')->limit(500)->get(),
            'boxes' => ServerMailBox::query()->count(),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $host = ['required', 'string', 'max:191', 'regex:/^(?=.{4,191}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'];
        $data = $request->validate([
            'hostname' => $host,
            'webmail' => $host,
            'max_boxes' => ['required', 'integer', 'min:1', 'max:100'],
            'quota_mb' => ['required', 'integer', 'min:50', 'max:102400'],
        ]);
        $this->mail->saveSettings($data);
        $this->alert->success('The settings were saved.')->flash();

        return redirect()->route('admin.mail');
    }

    /**
     * A new key for the agent: the old one stops working. It is shown once, in the install command.
     */
    public function token(): RedirectResponse
    {
        $token = $this->mail->newToken();

        return redirect()->route('admin.mail')->with('mail_token', $token);
    }
}
