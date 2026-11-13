<?php

namespace Pterodactyl\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\User;
use Pterodactyl\Models\UserPasskey;

/**
 * Passkeys (WebAuthn): signing in with Face ID, Touch ID, Windows Hello, a phone or a security key, without a password.
 *
 * The device keeps a private key and only ever gives the panel its public key, and a signature of a random challenge
 * each time the person signs in. The checks are the ones of the WebAuthn specification:
 *  - the challenge is the one the panel just made, used once, and recent;
 *  - the page that asked is this site (the origin, and the hash of the site name in what the device signed);
 *  - the person was there and unlocked the device (the "user present" and "user verified" flags);
 *  - the signature is right for the public key saved when the passkey was added;
 *  - the counter of the device went up, when it keeps one (a passkey copied off a security key would repeat it).
 *
 * The public key is read by the browser (getPublicKey()), so no CBOR has to be decoded here: it arrives as a standard
 * SubjectPublicKeyInfo. Attestation is not asked for: nothing is said about the maker of the device.
 */
class Passkeys
{
    // How long a challenge can be answered, in seconds.
    private const TIMEOUT = 300;

    // At most this many passkeys on one account.
    public const MAX = 10;

    // ES256 (P-256), EdDSA (Ed25519) and RS256: what browsers and devices make.
    private const ALGORITHMS = [-7, -8, -257];

    // The start of the SubjectPublicKeyInfo of an Ed25519 key; the 32 bytes of the key follow.
    private const ED25519_PREFIX = '302a300506032b6570032100';

    /**
     * What the browser needs to make a passkey for this person on this site.
     */
    public function registrationOptions(Request $request, User $user): array
    {
        $rp = $this->rpId($request);
        $challenge = random_bytes(32);
        $request->session()->put('passkey:register', ['challenge' => self::encode($challenge), 'rp' => $rp, 'user' => $user->id, 'until' => time() + self::TIMEOUT]);

        return [
            'challenge' => self::encode($challenge),
            'rp' => ['name' => (string) config('app.name', 'Pterodactyl'), 'id' => $rp],
            'user' => ['id' => self::encode((string) $user->uuid), 'name' => (string) $user->email, 'displayName' => (string) $user->username],
            'pubKeyCredParams' => array_map(fn (int $alg) => ['type' => 'public-key', 'alg' => $alg], self::ALGORITHMS),
            'timeout' => self::TIMEOUT * 1000,
            'attestation' => 'none',
            'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required'],
            // The device says so if it already holds a passkey of this account, instead of making a second one.
            'excludeCredentials' => $this->of($user)->where('rp_id', $rp)
                ->map(fn (UserPasskey $p) => ['type' => 'public-key', 'id' => $p->credential_id])->values()->all(),
        ];
    }

    /**
     * Checks a new passkey and saves it.
     *
     * @param array{id: string, client_data: string, authenticator_data: string, public_key: string, algorithm: int, transports?: array<int, string>|null, name?: string|null} $data
     *
     * @throws DisplayException
     */
    public function register(Request $request, User $user, array $data): UserPasskey
    {
        $pending = $request->session()->pull('passkey:register');
        if (!is_array($pending) || ($pending['user'] ?? null) !== $user->id || ($pending['until'] ?? 0) < time()) {
            throw new DisplayException('The request expired. Try again.');
        }
        if ($this->of($user)->count() >= self::MAX) {
            throw new DisplayException('An account can have at most ' . self::MAX . ' passkeys.');
        }

        $rp = (string) $pending['rp'];
        $clientJson = self::decode($data['client_data']);
        $this->checkClient($clientJson, 'webauthn.create', (string) $pending['challenge'], $rp);

        $auth = self::decode($data['authenticator_data']);
        $this->checkAuthenticator($auth, $rp, true);

        // The attested credential data: 16 bytes of AAGUID, the length of the id, the id, then the key.
        $length = unpack('n', substr($auth, 53, 2))[1] ?? 0;
        $credential = substr($auth, 55, $length);
        if ($length < 16 || $length > 128 || strlen($credential) !== $length || !hash_equals($credential, self::decode($data['id']))) {
            throw new DisplayException('The passkey could not be verified.');
        }
        $id = self::encode($credential);
        if (UserPasskey::query()->where('credential_id', $id)->exists()) {
            throw new DisplayException('This passkey is already saved.');
        }

        $algorithm = (int) $data['algorithm'];
        $der = self::decode($data['public_key']);
        $this->checkKey($der, $algorithm);

        $transports = array_values(array_intersect((array) ($data['transports'] ?? []), ['usb', 'nfc', 'ble', 'internal', 'hybrid', 'smart-card']));

        return UserPasskey::query()->create([
            'user_id' => $user->id,
            'name' => mb_substr(trim((string) ($data['name'] ?? '')) ?: 'Passkey', 0, 64),
            'rp_id' => $rp,
            'credential_id' => $id,
            'public_key' => base64_encode($der),
            'algorithm' => $algorithm,
            'sign_count' => unpack('N', substr($auth, 33, 4))[1] ?? 0,
            'transports' => $transports ? implode(',', $transports) : null,
        ]);
    }

    /**
     * What the browser needs to sign in with a passkey. No account is named: the device shows the passkeys it holds
     * for this site, and the one chosen says whose it is.
     */
    public function loginOptions(Request $request): array
    {
        $challenge = random_bytes(32);
        $rp = $this->rpId($request);
        $request->session()->put('passkey:login', ['challenge' => self::encode($challenge), 'until' => time() + self::TIMEOUT]);

        return [
            'challenge' => self::encode($challenge),
            'rpId' => $rp,
            'timeout' => self::TIMEOUT * 1000,
            'userVerification' => 'required',
            'allowCredentials' => [],
        ];
    }

    /**
     * Checks the answer of the device and gives the account it opens.
     *
     * @param array{id: string, client_data: string, authenticator_data: string, signature: string, user_handle?: string|null} $data
     *
     * @throws DisplayException
     */
    public function login(Request $request, array $data): User
    {
        $pending = $request->session()->pull('passkey:login');
        if (!is_array($pending) || ($pending['until'] ?? 0) < time()) {
            throw new DisplayException('The request expired. Try again.');
        }

        $passkey = UserPasskey::query()->with('user')->where('credential_id', self::encode(self::decode($data['id'])))->first();
        if (!$passkey || !$passkey->user) {
            throw new DisplayException('This passkey is not known by the panel. It may have been removed from the account.');
        }
        $user = $passkey->user;

        $clientJson = self::decode($data['client_data']);
        $this->checkClient($clientJson, 'webauthn.get', (string) $pending['challenge'], $passkey->rp_id);

        $auth = self::decode($data['authenticator_data']);
        $this->checkAuthenticator($auth, $passkey->rp_id, false);

        if (!empty($data['user_handle']) && !hash_equals((string) $user->uuid, self::decode($data['user_handle']))) {
            throw new DisplayException('The passkey could not be verified.');
        }

        $signed = $auth . hash('sha256', $clientJson, true);
        if (!$this->verify($passkey, $signed, self::decode($data['signature']))) {
            throw new DisplayException('The passkey could not be verified.');
        }

        // A device that counts its signatures must give a higher number each time; the same or a lower one means two
        // copies of the key are in use.
        $count = unpack('N', substr($auth, 33, 4))[1] ?? 0;
        if (($count > 0 || $passkey->sign_count > 0) && $count <= $passkey->sign_count) {
            throw new DisplayException('The passkey could not be verified.');
        }

        $passkey->forceFill(['sign_count' => $count, 'last_used_at' => now()])->save();

        return $user;
    }

    /**
     * @return Collection<int, UserPasskey>
     */
    public function of(User $user): Collection
    {
        return UserPasskey::query()->where('user_id', $user->id)->orderBy('id')->get()->toBase();
    }

    /**
     * The name of the site the passkeys belong to: the host the page was opened on. The browser refuses any other
     * one, and every check below compares with the name saved with the passkey, not with this request.
     */
    private function rpId(Request $request): string
    {
        return strtolower($request->getHost());
    }

    /**
     * @throws DisplayException
     */
    private function checkClient(string $json, string $type, string $challenge, string $rp): void
    {
        $client = json_decode($json, true);
        if (!is_array($client) || ($client['type'] ?? null) !== $type || !is_string($client['challenge'] ?? null)
            || !hash_equals($challenge, self::encode(self::decode($client['challenge'])))) {
            throw new DisplayException('The passkey could not be verified.');
        }

        // The page that asked must be this site, in HTTPS (plain HTTP is only allowed by browsers on localhost).
        $origin = parse_url((string) ($client['origin'] ?? ''));
        $host = strtolower((string) ($origin['host'] ?? ''));
        $scheme = (string) ($origin['scheme'] ?? '');
        if ($host !== $rp || !($scheme === 'https' || ($scheme === 'http' && $host === 'localhost')) || !empty($client['crossOrigin'])) {
            throw new DisplayException('The passkey was made for another site.');
        }
    }

    /**
     * @throws DisplayException
     */
    private function checkAuthenticator(string $auth, string $rp, bool $creating): void
    {
        if (strlen($auth) < 37 || !hash_equals(hash('sha256', $rp, true), substr($auth, 0, 32))) {
            throw new DisplayException('The passkey was made for another site.');
        }
        $flags = ord($auth[32]);
        // 0x01: the person touched or looked at the device; 0x04: they unlocked it (face, finger, PIN).
        if (!($flags & 0x01) || !($flags & 0x04)) {
            throw new DisplayException('The device did not confirm it was you. Unlock it with your face, finger or PIN.');
        }
        // 0x40: the new key is attached.
        if ($creating && (!($flags & 0x40) || strlen($auth) < 55)) {
            throw new DisplayException('The passkey could not be verified.');
        }
    }

    /**
     * @throws DisplayException
     */
    private function checkKey(string $der, int $algorithm): void
    {
        if (!in_array($algorithm, self::ALGORITHMS, true)) {
            throw new DisplayException('This kind of passkey is not supported.');
        }
        if ($algorithm === -8) {
            if (strlen($der) !== 44 || bin2hex(substr($der, 0, 12)) !== self::ED25519_PREFIX) {
                throw new DisplayException('The passkey could not be verified.');
            }

            return;
        }

        $key = openssl_pkey_get_public(self::pem($der));
        $details = $key ? openssl_pkey_get_details($key) : false;
        // ES256: a P-256 key. RS256: an RSA key of 2048 bits or more.
        $ok = is_array($details) && ($algorithm === -7
            ? $details['type'] === OPENSSL_KEYTYPE_EC && ($details['ec']['curve_name'] ?? '') === 'prime256v1'
            : $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 2048);
        if (!$ok) {
            throw new DisplayException('The passkey could not be verified.');
        }
    }

    private function verify(UserPasskey $passkey, string $signed, string $signature): bool
    {
        $der = (string) base64_decode($passkey->public_key, true);
        if ($passkey->algorithm === -8) {
            return strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES
                && sodium_crypto_sign_verify_detached($signature, $signed, substr($der, 12));
        }

        // ES256 signatures are DER-encoded by WebAuthn, which is what OpenSSL reads.
        return openssl_verify($signed, $signature, self::pem($der), OPENSSL_ALGO_SHA256) === 1;
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * @throws DisplayException
     */
    public static function decode(string $text): string
    {
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);
        if ($bytes === false) {
            throw new DisplayException('The passkey could not be verified.');
        }

        return $bytes;
    }
}
