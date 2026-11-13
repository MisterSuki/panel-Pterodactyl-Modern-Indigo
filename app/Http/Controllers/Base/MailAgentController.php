<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Mail\MailHosting;

/**
 * Called by the mail agent (install-assets/mail/pterodactyl-mail-agent) on the machine of the mail server: no session,
 * no user, only the key the administration made for it ("Authorization: Bearer pml_...").
 */
class MailAgentController extends Controller
{
    public function __construct(private MailHosting $mail)
    {
    }

    public function feed(Request $request): JsonResponse
    {
        $this->authorizeAgent($request);

        return new JsonResponse($this->mail->feed());
    }

    public function report(Request $request): JsonResponse
    {
        $this->authorizeAgent($request);
        $data = $request->validate([
            'version' => ['nullable', 'string', 'max:32'],
            'running' => ['nullable', 'boolean'],
            'certificate' => ['nullable', 'boolean'],
            'webmail_certificate' => ['nullable', 'boolean'],
            'outbound_25' => ['nullable', 'boolean'],
            'ip' => ['nullable', 'ip'],
            'errors' => ['nullable', 'array', 'max:20'],
            'errors.*' => ['string', 'max:1000'],
            'dkim' => ['nullable', 'array', 'max:5000'],
            'dkim.*' => ['string', 'max:4096'],
            'usage' => ['nullable', 'array', 'max:50000'],
            'usage.*' => ['integer', 'min:0'],
        ]);
        $this->mail->saveReport($data);

        return new JsonResponse(['ok' => true]);
    }

    private function authorizeAgent(Request $request): void
    {
        abort_unless($this->mail->checkToken($request->bearerToken()), 403);
    }
}
