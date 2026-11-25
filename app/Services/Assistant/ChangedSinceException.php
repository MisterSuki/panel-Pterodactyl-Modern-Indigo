<?php

namespace Pterodactyl\Services\Assistant;

use Pterodactyl\Exceptions\DisplayException;

/**
 * Putting a file back: it was changed again after the change of the assistant (the person confirms first).
 */
class ChangedSinceException extends DisplayException
{
    public function __construct()
    {
        parent::__construct('The file was changed again after this change. Putting it back will also undo those changes.');
    }

    // 409: the page asks "put it back anyway?".
    public function getStatusCode(): int
    {
        return 409;
    }
}
