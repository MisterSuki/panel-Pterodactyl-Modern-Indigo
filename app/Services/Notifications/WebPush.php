<?php

namespace Pterodactyl\Services\Notifications;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Models\PushSubscription;

/**
 * The notifications of the panel on the phones and computers of the people (Web Push): the keys of the panel (VAPID,
 * made once), the devices that asked for them, and sending one — encrypted for the device (RFC 8291, aes128gcm) and
 * signed by the panel (RFC 8292), with OpenSSL only.
 */
class WebPush
{
    // The start of the DER form of a P-256 public key: what is added before the 65 bytes of a key to read it.
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * The public key of the panel, for the browsers (base64url of the 65 bytes of the point). Made the first time.
     */
    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /**
     * Sends a notification to every device of a person (the ones that are gone are forgotten).
     */
    public function toUser(int $userId, string $title, ?string $body, ?string $url, string $kind = 'info'): int
    {
        $sent = 0;
        foreach (PushSubscription::query()->where('user_id', $userId)->get() as $subscription) {
            if ($this->send($subscription, ['title' => $title, 'body' => (string) $body, 'url' => $url ?: '/', 'kind' => $kind, 'tag' => 'pd-' . md5($title . $url)])) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * One notification to one device. A device that is gone (404 / 410) is deleted.
     */
    public function send(PushSubscription $subscription, array $data): bool
    {
        try {
            $payload = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $body = $this->encrypt($payload, $subscription->p256dh, $subscription->auth);
            $response = Http::timeout(8)->connectTimeout(4)->withHeaders([
                'Content-Encoding' => 'aes128gcm',
                'Content-Type' => 'application/octet-stream',
                'TTL' => '86400',
                'Urgency' => ($data['kind'] ?? '') === 'danger' ? 'high' : 'normal',
                'Authorization' => $this->vapidHeader($subscription->endpoint),
            ])->withBody($body, 'application/octet-stream')->post($subscription->endpoint);
        } catch (\Throwable) {
            return false;
        }

        if (in_array($response->status(), [404, 410], true)) {
            $subscription->delete();

            return false;
        }

        return $response->successful();
    }

    /**
     * The message encrypted for the device (RFC 8291): a new key of the panel for each message, the secret shared with
     * the device (ECDH), and AES-128-GCM in one record.
     *
     * @param string|null $ephemeral PEM of the key of the panel to use (tests only)
     * @param string|null $salt 16 bytes (tests only)
     */
    public function encrypt(string $payload, string $userPublic, string $userAuth, ?string $ephemeral = null, ?string $salt = null): string
    {
        $uaPublic = self::base64UrlDecode($userPublic);
        $auth = self::base64UrlDecode($userAuth);
        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04" || strlen($auth) < 16) {
            throw new \InvalidArgumentException('The keys of this device are not valid.');
        }

        $key = $ephemeral !== null ? openssl_pkey_get_private($ephemeral) : openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            throw new \RuntimeException('OpenSSL could not make a P-256 key.');
        }
        $asPublic = self::point((array) (openssl_pkey_get_details($key)['ec'] ?? []));
        $shared = openssl_pkey_derive(self::publicPem($uaPublic), $key, 32);
        if ($shared === false) {
            throw new \RuntimeException('The shared secret could not be made.');
        }

        $salt ??= random_bytes(16);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPublic . $asPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // One record: the message, then the delimiter of the last record.
        $tag = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('The message could not be encrypted.');
        }

        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }

    /**
     * "vapid t=<JWT>, k=<public key>": proves to the push service of the browser that the message comes from this panel.
     */
    public function vapidHeader(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $header = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::base64UrlEncode((string) json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $this->subject(),
        ], JSON_UNESCAPED_SLASHES));
        $signed = $header . '.' . $claims;

        $keys = $this->keys();
        $signature = '';
        if (!openssl_sign($signed, $signature, $keys['private'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('The notification could not be signed.');
        }

        return 'vapid t=' . $signed . '.' . self::base64UrlEncode(self::derToRaw($signature)) . ', k=' . $keys['public'];
    }

    /**
     * The keys of the panel: made once and kept (the private one encrypted with the key of the panel).
     *
     * @return array{public: string, private: string}
     */
    public function keys(): array
    {
        $public = (string) $this->settings->get('webpush:public', '');
        $stored = (string) $this->settings->get('webpush:private', '');
        if ($public !== '' && $stored !== '') {
            try {
                return ['public' => $public, 'private' => Crypt::decryptString($stored)];
            } catch (\Throwable) {
                // Made with another APP_KEY: new keys (the devices subscribe again).
            }
        }

        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false || !openssl_pkey_export($key, $pem)) {
            throw new \RuntimeException('OpenSSL could not make the keys of the notifications.');
        }
        $public = self::base64UrlEncode(self::point((array) (openssl_pkey_get_details($key)['ec'] ?? [])));
        $this->settings->set('webpush:public', $public);
        $this->settings->set('webpush:private', Crypt::encryptString($pem));
        // The devices subscribed with the old key cannot get anything any more.
        PushSubscription::query()->delete();

        return ['public' => $public, 'private' => $pem];
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    /**
     * The 65 bytes of a P-256 public key (0x04, x, y) from the details of OpenSSL.
     */
    public static function point(array $ec): string
    {
        $pad = fn (string $v) => str_pad($v, 32, "\0", STR_PAD_LEFT);

        return "\x04" . $pad((string) ($ec['x'] ?? '')) . $pad((string) ($ec['y'] ?? ''));
    }

    /**
     * A P-256 public key of 65 bytes as PEM, for OpenSSL.
     */
    public static function publicPem(string $point): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode((string) hex2bin(self::P256_SPKI_PREFIX) . $point), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * An ECDSA signature of OpenSSL (DER) as the 64 bytes r || s of a JWT.
     */
    public static function derToRaw(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7f : 0);
        $read = function () use ($der, &$offset): string {
            $length = ord($der[$offset + 1]);
            $value = substr($der, $offset + 2, $length);
            $offset += 2 + $length;

            return str_pad(ltrim($value, "\0"), 32, "\0", STR_PAD_LEFT);
        };

        return $read() . $read();
    }

    /**
     * Who to contact about the messages: the address of the panel.
     */
    private function subject(): string
    {
        $url = rtrim((string) config('app.url'), '/');

        return str_starts_with($url, 'https://') ? $url : 'mailto:' . (config('mail.from.address') ?: 'admin@localhost');
    }
}
