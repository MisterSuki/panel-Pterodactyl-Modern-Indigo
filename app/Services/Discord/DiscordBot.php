<?php

namespace Pterodactyl\Services\Discord;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Services\Auth\AuthFeatures;

/**
 * The Discord bot of the panel. It needs no program of its own: Discord sends the slash commands to the panel
 * (Interactions endpoint), and the panel uses the token of the bot only to declare its commands and to send private
 * messages. Its messages are Components V2 cards.
 */
class DiscordBot
{
    public const API = 'https://discord.com/api/v10';

    public const COLORS = ['success' => 0x30D158, 'warning' => 0xFF9F0A, 'danger' => 0xFF453A, 'info' => 0x0A84FF];

    // A message made of components (Components V2), and one only the person who asked sees.
    public const FLAG_COMPONENTS = 1 << 15;

    public const FLAG_EPHEMERAL = 1 << 6;

    public function __construct(private SettingsRepositoryInterface $settings, private CacheRepository $cache)
    {
    }

    public function applicationId(): string
    {
        return (string) ($this->settings->get('discord_bot:application_id', '') ?: AuthFeatures::discordClientId() ?: '');
    }

    public function publicKey(): string
    {
        return (string) $this->settings->get('discord_bot:public_key', '');
    }

    public function token(): ?string
    {
        $stored = (string) $this->settings->get('discord_bot:token', '');
        if ($stored === '') {
            return null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    public function save(string $applicationId, string $publicKey, ?string $token): void
    {
        $this->settings->set('discord_bot:application_id', $applicationId);
        $this->settings->set('discord_bot:public_key', strtolower($publicKey));
        if ($token !== null && $token !== '') {
            $this->settings->set('discord_bot:token', Crypt::encryptString($token));
        }
    }

    public function configured(): bool
    {
        return $this->applicationId() !== '' && preg_match('/^[0-9a-f]{64}$/', $this->publicKey()) === 1 && $this->token() !== null;
    }

    /**
     * Declares the slash commands to Discord, in English and in French.
     *
     * @throws \RuntimeException
     */
    public function registerCommands(): int
    {
        $server = [
            'type' => 3,
            'name' => 'server',
            'name_localizations' => ['fr' => 'serveur'],
            'description' => 'The server',
            'description_localizations' => ['fr' => 'Le serveur'],
            'required' => true,
            'autocomplete' => true,
        ];
        $command = fn (string $name, string $en, string $fr, bool $withServer) => array_filter([
            'type' => 1,
            'name' => $name,
            'description' => $en,
            'description_localizations' => ['fr' => $fr],
            'options' => $withServer ? [$server] : null,
        ]);
        $commands = [
            $command('servers', 'Your servers and their state', 'Vos serveurs et leur état', false),
            $command('status', 'The state of a server, with its buttons', 'L\'état d\'un serveur, avec ses boutons', true),
            $command('start', 'Start a server', 'Démarrer un serveur', true),
            $command('restart', 'Restart a server', 'Redémarrer un serveur', true),
            $command('stop', 'Stop a server', 'Arrêter un serveur', true),
        ];

        $response = $this->http()->put(self::API . '/applications/' . $this->applicationId() . '/commands', $commands);
        if (!$response->successful()) {
            throw new \RuntimeException('Discord refused the commands: ' . mb_substr($response->body(), 0, 300));
        }

        return count($commands);
    }

    /**
     * A private message from the bot. Discord only lets a bot write to someone who shares a server with it.
     */
    public function directMessage(string $discordId, array $card): bool
    {
        if (!$this->configured() || preg_match('/^\d{5,25}$/', $discordId) !== 1) {
            return false;
        }
        try {
            $channel = $this->cache->get('discord-dm:' . $discordId);
            if (!is_string($channel)) {
                $response = $this->http()->post(self::API . '/users/@me/channels', ['recipient_id' => $discordId]);
                $channel = (string) $response->json('id');
                if (!$response->successful() || $channel === '') {
                    return false;
                }
                $this->cache->put('discord-dm:' . $discordId, $channel, 86400);
            }
            $sent = $this->http()->post(self::API . "/channels/$channel/messages", [
                'flags' => self::FLAG_COMPONENTS,
                'components' => [$card],
                'allowed_mentions' => ['parse' => []],
            ]);
            if (!$sent->successful()) {
                Log::info('A Discord private message was refused.', ['status' => $sent->status(), 'body' => mb_substr($sent->body(), 0, 300)]);
            }

            return $sent->successful();
        } catch (\Throwable $exception) {
            Log::info('A Discord private message could not be sent.', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    /**
     * A card: a container in the colour of what it says, its title, its lines, and at the bottom its buttons.
     *
     * @param string[] $lines
     * @param array<int, array> $buttons
     */
    public static function card(string $title, array $lines, string $kind = 'info', array $buttons = []): array
    {
        $components = [['type' => 10, 'content' => mb_substr('### ' . $title, 0, 300)]];
        $body = trim(implode("\n", $lines));
        if ($body !== '') {
            $components[] = ['type' => 10, 'content' => mb_substr($body, 0, 3500)];
        }
        if ($buttons !== []) {
            $components[] = ['type' => 14, 'divider' => true, 'spacing' => 1];
            foreach (array_chunk($buttons, 5) as $row) {
                $components[] = ['type' => 1, 'components' => $row];
            }
        }

        return ['type' => 17, 'accent_color' => self::COLORS[$kind] ?? self::COLORS['info'], 'components' => $components];
    }

    public static function button(string $label, string $customId, int $style = 2, bool $disabled = false): array
    {
        return ['type' => 2, 'style' => $style, 'label' => mb_substr($label, 0, 80), 'custom_id' => $customId, 'disabled' => $disabled];
    }

    public static function link(string $label, string $url): ?array
    {
        return preg_match('#^https://#i', $url) === 1 ? ['type' => 2, 'style' => 5, 'label' => mb_substr($label, 0, 80), 'url' => $url] : null;
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Bot ' . $this->token()])->acceptJson()->connectTimeout(3)->timeout(8);
    }
}
