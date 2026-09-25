<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Database;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Databases\PhpMyAdminSignOn;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\PhpMyAdminRequest;

class DatabasePhpMyAdminController extends ClientApiController
{
    public function __construct(private PhpMyAdminSignOn $signOn)
    {
        parent::__construct();
    }

    /**
     * Returns a one-minute, single-use link that opens the database in phpMyAdmin already signed in.
     */
    public function __invoke(PhpMyAdminRequest $request, Server $server, Database $database): JsonResponse
    {
        if (!PhpMyAdminSignOn::enabled() || $database->server_id !== $server->id) {
            return new JsonResponse(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'phpMyAdmin is not available for this database.']]], 404);
        }

        $url = $this->signOn->issue($database);

        Activity::event('server:database.phpmyadmin')
            ->subject($database)
            ->property('name', $database->database)
            ->log();

        return new JsonResponse(['object' => 'phpmyadmin_link', 'attributes' => ['url' => $url]]);
    }
}
