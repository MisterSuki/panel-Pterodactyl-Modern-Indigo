<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Assistant\AssistantSettings;
use Pterodactyl\Services\Assistant\ServerAssistant;

/**
 * The settings of the AI assistant of the servers: on or off, the Groq key, the model, who may use it, and a test.
 */
class AssistantController extends Controller
{
    public function __construct(private AssistantSettings $settings, private ServerAssistant $assistant, private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.assistant', [
            'enabled' => $this->settings->enabled(),
            'keySource' => $this->settings->keySource(),
            'model' => $this->settings->model(),
            'models' => AssistantSettings::SUGGESTED_MODELS,
            'audience' => $this->settings->audience(),
            'daily' => $this->settings->daily(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'key' => ['nullable', 'string', 'max:300'],
            'forget_key' => ['sometimes', 'boolean'],
            'model' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/-]+$/'],
            'audience' => ['required', 'in:all,staff'],
            'daily' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
        $this->settings->save(
            $request->boolean('enabled'),
            $data['key'] ?? null,
            $request->boolean('forget_key'),
            $data['model'],
            $data['audience'],
            (int) $data['daily']
        );
        $this->alert->success('The AI assistant was saved.')->flash();

        return redirect()->route('admin.assistant');
    }

    public function test(): RedirectResponse
    {
        try {
            $answer = $this->assistant->test();
            $this->alert->success('The AI answered: ' . e(mb_substr($answer, 0, 80)))->flash();
        } catch (\Throwable $exception) {
            $this->alert->danger(e($exception->getMessage()))->flash();
        }

        return redirect()->route('admin.assistant');
    }
}
