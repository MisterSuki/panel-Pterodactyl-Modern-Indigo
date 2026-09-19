<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Auth\AuthManager;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Events\Auth\DirectLogin;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Auth\AuthFeatures;
use Pterodactyl\Services\Users\UserRegistrationService;

/**
 * Handles signing in, signing up and linking accounts with Discord (OAuth2).
 *
 * The result of every attempt is reported to the React app through a `discord` query
 * parameter containing one of a fixed list of codes, never a free-form message.
 */
class DiscordController extends Controller
{
    private const AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';
    private const TOKEN_URL = 'https://discord.com/api/oauth2/token';
    private const PROFILE_URL = 'https://discord.com/api/users/@me';

    public function __construct(private AuthManager $auth, private UserRegistrationService $registration)
    {
    }

    /**
     * Send the visitor to Discord. If they are already signed in this links their
     * account instead of signing them in.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $intent = $request->user() ? 'link' : 'login';

        if (!AuthFeatures::discordEnabled()) {
            return $this->failed($intent, 'disabled');
        }

        $state = Str::random(40);
        $request->session()->put('discord_oauth', ['state' => $state, 'intent' => $intent]);

        return redirect()->away(self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => AuthFeatures::discordClientId(),
            'redirect_uri' => route('auth.discord.callback'),
            'response_type' => 'code',
            'scope' => 'identify email',
            'state' => $state,
        ]));
    }

    /**
     * Handle the visitor coming back from Discord.
     */
    public function callback(Request $request): RedirectResponse
    {
        $stored = $request->session()->pull('discord_oauth');
        $intent = ($stored['intent'] ?? 'login') === 'link' && $request->user() ? 'link' : 'login';

        if (!AuthFeatures::discordEnabled()) {
            return $this->failed($intent, 'disabled');
        }

        if ($request->filled('error')) {
            return $this->failed($intent, 'cancelled');
        }

        $state = (string) $request->query('state', '');
        if (empty($stored['state']) || !hash_equals($stored['state'], $state) || !$request->filled('code')) {
            return $this->failed($intent, 'failed');
        }

        try {
            $profile = $this->fetchProfile((string) $request->query('code'));
        } catch (\Throwable $exception) {
            Log::warning('Unable to complete the Discord login: ' . $exception->getMessage());

            return $this->failed($intent, 'failed');
        }

        return $intent === 'link' ? $this->link($request->user(), $profile) : $this->login($request, $profile);
    }

    /**
     * Link the Discord account to the user who is currently signed in.
     */
    private function link(User $user, array $profile): RedirectResponse
    {
        $discordId = (string) $profile['id'];

        $existing = User::query()->where('discord_id', $discordId)->first();
        if ($existing && $existing->id !== $user->id) {
            return $this->failed('link', 'taken');
        }

        $this->storeLink($user, $profile);
        Activity::event('user:account.discord-linked')->subject($user)->property(['discord' => $profile['username'] ?? null])->log();

        return redirect('/account?discord=linked');
    }

    /**
     * Sign the visitor in, linking or creating an account when needed.
     */
    private function login(Request $request, array $profile): RedirectResponse
    {
        $discordId = (string) $profile['id'];

        // Only trust the email address when Discord says the owner has verified it.
        $email = !empty($profile['verified']) && !empty($profile['email']) ? mb_strtolower($profile['email']) : null;

        $linked = User::query()->where('discord_id', $discordId)->first();
        if ($linked) {
            return $this->finish($request, $linked);
        }

        // Sync with an account that already exists under the same verified email address.
        if ($email && ($existing = User::query()->where('email', $email)->first())) {
            // Administrators and staff have to link Discord from their account page while
            // signed in, so that a matching email address alone can never open an account
            // with access to the admin area.
            if ($existing->isStaff()) {
                return $this->failed('login', 'admin_link');
            }

            if ($existing->discord_id) {
                return $this->failed('login', 'other_discord');
            }

            $this->storeLink($existing, $profile);

            return $this->finish($request, $existing->fresh());
        }

        if (!AuthFeatures::registrationEnabled()) {
            return $this->failed('login', 'no_account');
        }

        if (!$email) {
            return $this->failed('login', 'no_email');
        }

        try {
            $user = $this->registration->handle([
                'username' => $this->uniqueUsername((string) ($profile['username'] ?? '')),
                'email' => $email,
                'name_first' => Str::limit((string) ($profile['global_name'] ?? $profile['username'] ?? 'Discord'), 191, ''),
                'name_last' => 'Discord',
                'password' => Str::random(48),
                'discord_id' => $discordId,
                'discord_username' => Str::limit((string) ($profile['username'] ?? ''), 191, ''),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Unable to create an account from Discord: ' . $exception->getMessage());

            return $this->failed('login', 'failed');
        }

        return $this->finish($request, $user);
    }

    /**
     * Complete the sign in. Accounts protected by two-factor authentication still have
     * to provide their code, so Discord never bypasses it.
     */
    private function finish(Request $request, User $user): RedirectResponse
    {
        if ($user->use_totp) {
            Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)->log();

            $request->session()->put('auth_confirmation_token', [
                'user_id' => $user->id,
                'token_value' => $token = Str::random(64),
                'expires_at' => CarbonImmutable::now()->addMinutes(5),
            ]);

            return redirect('/auth/login/checkpoint?token=' . $token);
        }

        $request->session()->remove('auth_confirmation_token');
        $request->session()->regenerate();

        $this->auth->guard()->login($user, true);

        Event::dispatch(new DirectLogin($user, true));

        return redirect('/');
    }

    /**
     * Save the Discord details without going through the model validation, so that an
     * older account with an unusual value in another field can still be linked.
     */
    private function storeLink(User $user, array $profile): void
    {
        User::query()->whereKey($user->id)->update([
            'discord_id' => (string) $profile['id'],
            'discord_username' => Str::limit((string) ($profile['username'] ?? ''), 191, ''),
        ]);
    }

    /**
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \RuntimeException
     */
    private function fetchProfile(string $code): array
    {
        $accessToken = Http::asForm()->acceptJson()->timeout(10)->post(self::TOKEN_URL, [
            'client_id' => AuthFeatures::discordClientId(),
            'client_secret' => AuthFeatures::discordClientSecret(),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => route('auth.discord.callback'),
        ])->throw()->json('access_token');

        if (empty($accessToken)) {
            throw new \RuntimeException('Discord did not return an access token.');
        }

        $profile = Http::withToken($accessToken)->acceptJson()->timeout(10)->get(self::PROFILE_URL)->throw()->json();
        if (!is_array($profile) || empty($profile['id'])) {
            throw new \RuntimeException('Discord did not return a user profile.');
        }

        return $profile;
    }

    /**
     * Turn a Discord handle into a username that is valid and not used yet.
     */
    private function uniqueUsername(string $handle): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9_.-]/', '', mb_strtolower($handle)), '_.-');
        $base = substr($base, 0, 32);
        if (strlen($base) < 3) {
            $base = 'user' . $base;
        }

        $candidate = $base;
        for ($attempt = 0; User::query()->where('username', $candidate)->exists(); ++$attempt) {
            $candidate = $attempt < 20 ? $base . random_int(100, 99999) : 'user' . Str::lower(Str::random(12));
        }

        return $candidate;
    }

    private function failed(string $intent, string $code): RedirectResponse
    {
        return redirect(($intent === 'link' ? '/account' : '/auth/login') . '?discord=' . $code);
    }
}
