<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\Appearance;

/**
 * Where the administration picks the background of the panel (gradient, animated effect or its own picture) and the
 * default accent colour. It needs the right to manage the settings of the panel.
 */
class AppearanceController extends Controller
{
    public function __construct(private Appearance $appearance, private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.appearance', [
            'site' => $this->appearance->stored(),
            'hasImage' => $this->appearance->imagePath() !== null,
            'imageUrl' => $this->appearance->site()['image'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'accent' => ['required', 'string', 'in:' . implode(',', Appearance::ACCENTS)],
            'mode' => ['required', 'string', 'in:' . implode(',', Appearance::MODES)],
            'gradient' => ['required', 'string', 'in:' . implode(',', Appearance::GRADIENTS)],
            'effect' => ['required', 'string', 'in:' . implode(',', Appearance::EFFECTS)],
            'dim' => ['required', 'integer', 'min:0', 'max:80'],
            'blur' => ['required', 'integer', 'min:0', 'max:30'],
            'image' => ['nullable', 'file', 'max:8192'],
        ]);

        try {
            if ($request->hasFile('image')) {
                $this->appearance->storeImage($request->file('image'));
            }
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.appearance')->withInput();
        }

        if ($data['mode'] === 'image' && !$this->appearance->imagePath()) {
            $this->alert->danger('Choose a picture first.')->flash();

            return redirect()->route('admin.appearance')->withInput();
        }

        $this->appearance->saveSite([
            'accent' => $data['accent'],
            'mode' => $data['mode'],
            'gradient' => $data['gradient'],
            'effect' => $data['effect'],
            'dim' => (int) $data['dim'],
            'blur' => (int) $data['blur'],
            'user_choice' => $request->boolean('user_choice'),
        ]);
        $this->alert->success('The appearance of the panel was saved.')->flash();

        return redirect()->route('admin.appearance');
    }

    public function removeImage(): RedirectResponse
    {
        $this->appearance->removeImage();
        $this->alert->success('The background picture was removed.')->flash();

        return redirect()->route('admin.appearance');
    }
}
