<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerMailBox;
use Pterodactyl\Services\Mail\MailHosting;

/**
 * The email of a site: turning it on for the domain of the site, the DNS records to add, and the mailboxes.
 */
class MailController extends ClientApiController
{
    public function __construct(private MailHosting $mail)
    {
        parent::__construct();
    }

    public function index(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_READ);

        return new JsonResponse($this->payload($server, false));
    }

    public function check(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_READ);

        return new JsonResponse($this->payload($server, true));
    }

    /**
     * @throws DisplayException
     */
    public function enable(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_CREATE);
        $domain = $this->mail->enable($server);
        Activity::event('server:mail.enable')->property('domain', $domain->hostname)->log();

        return new JsonResponse($this->payload($server, false));
    }

    public function disable(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_DELETE);
        $domain = $this->mail->domainOf($server);
        $this->mail->disable($server);
        if ($domain) {
            Activity::event('server:mail.disable')->property('domain', $domain->hostname)->log();
        }

        return new JsonResponse($this->payload($server, false));
    }

    /**
     * @throws DisplayException
     */
    public function store(Request $request, Server $server): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_CREATE);
        $data = $request->validate([
            'local' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        $box = $this->mail->addBox($server, $data['local'], $data['password']);
        Activity::event('server:mail.box-create')->property('address', $box->address)->log();

        return new JsonResponse($this->payload($server, false));
    }

    /**
     * @throws DisplayException
     */
    public function password(Request $request, Server $server, int $box): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_UPDATE);
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:128']]);

        $model = $this->find($server, $box);
        $this->mail->setPassword($model, $data['password']);
        Activity::event('server:mail.box-password')->property('address', $model->address)->log();

        return new JsonResponse($this->payload($server, false));
    }

    public function delete(Request $request, Server $server, int $box): JsonResponse
    {
        $this->authorizeFor($request, $server, Permission::ACTION_ALLOCATION_DELETE);
        $model = $this->find($server, $box);
        $model->delete();
        Activity::event('server:mail.box-delete')->property('address', $model->address)->log();

        return new JsonResponse($this->payload($server, false));
    }

    private function payload(Server $server, bool $check): array
    {
        $domain = $this->mail->domainOf($server);
        $web = $domain ? null : $this->mail->webDomainOf($server);
        $agent = $this->mail->agent();

        return [
            'available' => $this->mail->enabled(),
            'online' => (bool) ($agent['online'] ?? false),
            'hostname' => $this->mail->hostname(),
            'webmail' => $this->mail->webmail() !== '' ? 'https://' . $this->mail->webmail() : null,
            'max' => $this->mail->maxBoxes(),
            'quota_mb' => $this->mail->quotaMb(),
            // The domain email can be turned on for, while it is not.
            'candidate' => $web?->hostname,
            'domain' => $domain ? [
                'hostname' => $domain->hostname,
                'dkim_ready' => $domain->dkim !== null,
                'records' => $this->mail->records($domain, $check),
            ] : null,
            'boxes' => ServerMailBox::query()->where('server_id', $server->id)->orderBy('address')->get()
                ->map(fn (ServerMailBox $b) => [
                    'id' => $b->id,
                    'address' => $b->address,
                    'quota_mb' => $b->quota_mb,
                    'used_bytes' => $b->used_bytes,
                    'created_at' => $b->created_at?->toAtomString(),
                ])->values()->all(),
        ];
    }

    private function find(Server $server, int $id): ServerMailBox
    {
        return ServerMailBox::query()->where('server_id', $server->id)->findOrFail($id);
    }

    private function authorizeFor(Request $request, Server $server, string $permission): void
    {
        abort_unless($request->user()->can($permission, $server), 403);
    }
}
