<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Databases;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class PhpMyAdminRequest extends ClientApiRequest
{
    /**
     * Opening the database signed in is as powerful as seeing its password, so it needs the same permission.
     */
    public function permission(): string
    {
        return Permission::ACTION_DATABASE_VIEW_PASSWORD;
    }
}
