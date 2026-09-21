<?php

namespace Pterodactyl\Services\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\User;

/**
 * Lets a member of the staff look at the screen of a person who asked for help, and only if that person agrees.
 *
 * The staff member asks, the person gets a question on their page and, if they accept, chooses in their browser what to
 * share (a tab, a window or the whole screen): the browser itself shows that it is being shared, and the person can stop
 * at any time. The picture goes straight from one browser to the other (WebRTC); the panel only carries the few lines
 * of text that the two browsers need to find each other, for the time of the session, in its cache. Nothing of the
 * picture goes through the panel or is recorded.
 *
 * There is one session at most per person. It is asked by one staff member, and moves through: requested (waiting for
 * the person), offered (the person accepted), connected (the staff member answered), then declined or stopped.
 */
class ScreenShareService
{
    /**
     * How long a request waits for the person.
     */
    public const REQUEST_SECONDS = 120;

    /**
     * The longest a session can last.
     */
    public const MAX_SECONDS = 3600;

    /**
     * How long a session that ended is still visible to the other side, so that it can say so.
     */
    public const ENDED_SECONDS = 30;

    /**
     * A person whose page did not ask for news for this long is no longer there.
     */
    public const ALIVE_SECONDS = 20;

    /**
     * The biggest description of a connection that is taken in.
     */
    public const MAX_SDP = 65536;

    private const ACTIVE = ['requested', 'offered', 'connected'];

    /**
     * @return array<string, mixed>|null
     */
    public function get(int $userId): ?array
    {
        $session = Cache::get($this->key($userId));
        if (!is_array($session) || ($session['expires_at'] ?? 0) < time()) {
            return null;
        }

        return $session;
    }

    /**
     * A member of the staff asks to see the screen of a person.
     *
     * @return array<string, mixed>
     *
     * @throws DisplayException
     */
    public function request(User $target, User $admin): array
    {
        if ($target->id === $admin->id) {
            throw new DisplayException('You cannot watch your own screen.');
        }

        $current = $this->get($target->id);
        if ($current && in_array($current['state'], self::ACTIVE, true)) {
            if ($current['admin_id'] !== $admin->id) {
                throw new DisplayException($current['admin'] . ' is already watching or asking this person.');
            }

            return $current;
        }

        $session = [
            'id' => Str::random(24),
            'user_id' => $target->id,
            'admin_id' => $admin->id,
            'admin' => $admin->username,
            'state' => 'requested',
            'offer' => null,
            'answer' => null,
            'seen_at' => 0,
            'created_at' => time(),
            'expires_at' => time() + self::REQUEST_SECONDS,
        ];
        $this->save($session);

        return $session;
    }

    /**
     * The person accepted and sends the description of their connection.
     *
     * @throws DisplayException
     */
    public function offer(User $user, string $sdp): void
    {
        $session = $this->expect($user->id, ['requested']);
        $session['offer'] = $this->checked($sdp);
        $session['state'] = 'offered';
        $session['expires_at'] = $session['created_at'] + self::MAX_SECONDS;
        $this->save($session);
    }

    /**
     * The staff member answers the offer with the description of their own connection.
     *
     * @throws DisplayException
     */
    public function answer(int $userId, User $admin, string $id, string $sdp): void
    {
        $session = $this->expect($userId, ['offered']);
        if ($session['id'] !== $id || $session['admin_id'] !== $admin->id) {
            throw new DisplayException('This session is not yours.');
        }
        $session['answer'] = $this->checked($sdp);
        $session['state'] = 'connected';
        $this->save($session);
    }

    /**
     * The person does not want to share.
     */
    public function decline(User $user): void
    {
        $session = $this->get($user->id);
        if ($session && $session['state'] === 'requested') {
            $this->end($session, 'declined');
        }
    }

    /**
     * Either side stops. A staff member can only stop their own session.
     */
    public function stop(int $userId, ?User $admin = null): void
    {
        $session = $this->get($userId);
        if (!$session || !in_array($session['state'], self::ACTIVE, true)) {
            return;
        }
        if ($admin && $session['admin_id'] !== $admin->id) {
            return;
        }
        $this->end($session, 'stopped');
    }

    /**
     * The page of the person asked for news: it is still there.
     */
    public function touch(int $userId): void
    {
        $session = $this->get($userId);
        if ($session && in_array($session['state'], self::ACTIVE, true) && time() - $session['seen_at'] >= 3) {
            $session['seen_at'] = time();
            $this->save($session);
        }
    }

    /**
     * What the person gets to know: who asks, and the answer once the staff member gave it.
     *
     * @param array<string, mixed>|null $session
     *
     * @return array<string, mixed>|null
     */
    public function forUser(?array $session): ?array
    {
        if (!$session) {
            return null;
        }

        return [
            'id' => $session['id'],
            'state' => $session['state'],
            'admin' => $session['admin'],
            'answer' => $session['answer'],
        ];
    }

    /**
     * What the staff member gets to know: where things stand, the offer to answer, and whether the person is still there.
     *
     * @param array<string, mixed>|null $session
     *
     * @return array<string, mixed>|null
     */
    public function forAdmin(?array $session): ?array
    {
        if (!$session) {
            return null;
        }

        return [
            'id' => $session['id'],
            'state' => $session['state'],
            'offer' => $session['state'] === 'offered' ? $session['offer'] : null,
            'alive' => $session['seen_at'] > 0 && time() - $session['seen_at'] <= self::ALIVE_SECONDS,
        ];
    }

    /**
     * @param array<string, mixed> $session
     */
    private function end(array $session, string $state): void
    {
        $session['state'] = $state;
        $session['offer'] = null;
        $session['answer'] = null;
        $session['expires_at'] = time() + self::ENDED_SECONDS;
        $this->save($session);
    }

    /**
     * @param array<int, string> $states
     *
     * @return array<string, mixed>
     *
     * @throws DisplayException
     */
    private function expect(int $userId, array $states): array
    {
        $session = $this->get($userId);
        if (!$session || !in_array($session['state'], $states, true)) {
            throw new DisplayException('There is no such request any more.');
        }

        return $session;
    }

    /**
     * @throws DisplayException
     */
    private function checked(string $sdp): string
    {
        if ($sdp === '' || strlen($sdp) > self::MAX_SDP || !str_starts_with($sdp, 'v=0')) {
            throw new DisplayException('The description of the connection is not valid.');
        }

        return $sdp;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function save(array $session): void
    {
        Cache::put($this->key((int) $session['user_id']), $session, max(1, $session['expires_at'] - time()));
    }

    private function key(int $userId): string
    {
        return 'screen-share:' . $userId;
    }
}
