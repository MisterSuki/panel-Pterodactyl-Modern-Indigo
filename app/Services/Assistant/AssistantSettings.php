<?php

namespace Pterodactyl\Services\Assistant;

use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Models\User;

/**
 * The settings of the AI assistant: on or off, the Groq key (kept encrypted, or GROQ_API_KEY in .env), the model, who
 * may use it and how many questions a day.
 */
class AssistantSettings
{
    public const DEFAULT_MODEL = 'openai/gpt-oss-120b';

    // Models of Groq that answer well about servers (the administration may type another one).
    public const SUGGESTED_MODELS = ['openai/gpt-oss-120b', 'llama-3.3-70b-versatile', 'moonshotai/kimi-k2-instruct', 'openai/gpt-oss-20b'];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function enabled(): bool
    {
        return $this->settings->get('assistant:enabled', '0') === '1';
    }

    /**
     * The key of Groq: the one saved in the admin area, otherwise GROQ_API_KEY of .env.
     */
    public function key(): ?string
    {
        $stored = (string) $this->settings->get('assistant:key', '');
        if ($stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable) {
                // Saved with another APP_KEY: fall back on .env.
            }
        }
        $env = trim((string) config('pterodactyl.assistant.groq_key', ''));

        return $env !== '' ? $env : null;
    }

    /**
     * Where the key comes from: "panel", "env" or null.
     */
    public function keySource(): ?string
    {
        if ((string) $this->settings->get('assistant:key', '') !== '') {
            return 'panel';
        }

        return trim((string) config('pterodactyl.assistant.groq_key', '')) !== '' ? 'env' : null;
    }

    public function model(): string
    {
        $model = trim((string) $this->settings->get('assistant:model', ''));

        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    /**
     * "all" (everyone with access to the console of a server) or "staff" (the administration only).
     */
    public function audience(): string
    {
        return $this->settings->get('assistant:audience', 'all') === 'staff' ? 'staff' : 'all';
    }

    // Questions a person may ask in a day (0 = no limit).
    public function daily(): int
    {
        return max(0, (int) $this->settings->get('assistant:daily', '40'));
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->key() !== null;
    }

    public function allows(User $user): bool
    {
        return $this->ready() && ($this->audience() === 'all' || $user->isStaff());
    }

    public function save(bool $enabled, ?string $key, bool $forgetKey, string $model, string $audience, int $daily): void
    {
        $this->settings->set('assistant:enabled', $enabled ? '1' : '0');
        if ($forgetKey) {
            $this->settings->set('assistant:key', '');
        } elseif ($key !== null && trim($key) !== '') {
            $this->settings->set('assistant:key', Crypt::encryptString(trim($key)));
        }
        $this->settings->set('assistant:model', trim($model));
        $this->settings->set('assistant:audience', $audience === 'staff' ? 'staff' : 'all');
        $this->settings->set('assistant:daily', (string) max(0, min(10000, $daily)));
    }
}
