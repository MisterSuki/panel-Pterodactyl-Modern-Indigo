<?php

namespace Pterodactyl\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Which alerts a server sends (crash, start, stop, memory, disk) and where: the bell of the panel, and a Discord webhook
 * kept encrypted.
 *
 * @property int $id
 * @property int $server_id
 * @property string|null $webhook
 * @property string|null $events
 */
class ServerAlert extends Model
{
    public const EVENTS = ['crash', 'start', 'stop', 'memory', 'disk'];

    public const DEFAULTS = ['crash' => true, 'start' => false, 'stop' => false, 'memory' => false, 'disk' => true, 'bell' => true];

    protected $table = 'server_alerts';

    protected $guarded = ['id'];

    protected $hidden = ['webhook'];

    public function webhookUrl(): ?string
    {
        if (!$this->webhook) {
            return null;
        }
        try {
            return Crypt::decryptString($this->webhook);
        } catch (DecryptException) {
            return null;
        }
    }

    public function setWebhookUrl(?string $url): void
    {
        $this->webhook = $url ? Crypt::encryptString($url) : null;
    }

    /**
     * @return array<string, bool>
     */
    public function settings(): array
    {
        $raw = json_decode((string) $this->events, true);
        $out = self::DEFAULTS;
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (is_array($raw) && array_key_exists($key, $raw)) {
                $out[$key] = (bool) $raw[$key];
            }
        }

        return $out;
    }

    /**
     * Discord webhooks only: nothing else can be called, so the address cannot be used to reach other machines.
     */
    public static function validWebhook(string $url): bool
    {
        return preg_match('#^https://(?:(?:ptb|canary)\.)?discord(?:app)?\.com/api/webhooks/\d{5,30}/[A-Za-z0-9_-]{20,200}$#', $url) === 1;
    }
}
