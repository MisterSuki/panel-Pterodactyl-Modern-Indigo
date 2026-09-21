<?php

namespace Pterodactyl\Services\Tickets;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Pterodactyl\Exceptions\DisplayException;

/**
 * The life of a support ticket: opening it, answering, closing and reopening it, and what each person may see.
 *
 * A ticket is "open" while it waits for the staff and "answered" while it waits for the person who opened it. A
 * message written between staff members (an internal note) is never shown to that person, and does not change anything
 * about the ticket.
 */
class TicketService
{
    public const MAX_OPEN_TICKETS = 5;

    public const MAX_BODY = 5000;

    /**
     * Opens a ticket with its first message.
     *
     * @throws DisplayException
     */
    public function open(User $user, string $subject, string $category, ?int $serverId, string $body, string $priority = 'normal'): Ticket
    {
        $open = Ticket::query()->where('user_id', $user->id)->where('status', '!=', Ticket::CLOSED)->count();
        if ($open >= self::MAX_OPEN_TICKETS && !$user->isStaff()) {
            throw new DisplayException('You already have ' . self::MAX_OPEN_TICKETS . ' tickets open. Wait for an answer or close one before opening another.');
        }
        if ($serverId !== null && !$this->canReferenceServer($user, $serverId)) {
            throw new DisplayException('You cannot link a ticket to that server.');
        }

        return DB::transaction(function () use ($user, $subject, $category, $serverId, $body, $priority) {
            $ticket = Ticket::query()->create([
                'user_id' => $user->id,
                'server_id' => $serverId,
                'subject' => trim($subject),
                'category' => in_array($category, Ticket::CATEGORIES, true) ? $category : 'general',
                'priority' => in_array($priority, Ticket::PRIORITIES, true) ? $priority : 'normal',
                'status' => Ticket::OPEN,
                'last_message_at' => now(),
            ]);

            $message = $this->write($ticket, $user, $body, false, false);
            // The person wrote it: they have seen it.
            $ticket->update(['user_read_id' => $message->id]);

            return $ticket;
        });
    }

    /**
     * Adds a message. A staff member's answer makes the ticket "answered" (and gives it to them if nobody had it),
     * an answer of the person makes it "open" again.
     *
     * @throws DisplayException
     */
    public function reply(Ticket $ticket, User $author, string $body, bool $asStaff, bool $internal = false): TicketMessage
    {
        if ($ticket->isClosed()) {
            throw new DisplayException('This ticket is closed. Reopen it to write in it.');
        }
        if ($internal && !$asStaff) {
            throw new DisplayException('Only the staff can write an internal note.');
        }

        return DB::transaction(function () use ($ticket, $author, $body, $asStaff, $internal) {
            $message = $this->write($ticket, $author, $body, $asStaff, $internal);

            if (!$internal) {
                $changes = ['status' => $asStaff ? Ticket::ANSWERED : Ticket::OPEN, 'last_message_at' => now()];
                if ($asStaff && $ticket->assigned_to === null) {
                    $changes['assigned_to'] = $author->id;
                }
                if (!$asStaff) {
                    $changes['user_read_id'] = $message->id;
                }
                $ticket->update($changes);
            } else {
                $ticket->touch();
            }

            return $message;
        });
    }

    public function close(Ticket $ticket, User $by): Ticket
    {
        if (!$ticket->isClosed()) {
            $ticket->update(['status' => Ticket::CLOSED, 'closed_at' => now(), 'closed_by' => $by->id]);
        }

        return $ticket;
    }

    /**
     * A closed ticket goes back to waiting for the staff.
     */
    public function reopen(Ticket $ticket): Ticket
    {
        if ($ticket->isClosed()) {
            $ticket->update(['status' => Ticket::OPEN, 'closed_at' => null, 'closed_by' => null]);
        }

        return $ticket;
    }

    /**
     * What the staff can change: the priority, who takes care of it, and the status.
     *
     * @param array{priority?: string, assigned_to?: int|null, status?: string} $data
     *
     * @throws DisplayException
     */
    public function update(Ticket $ticket, User $by, array $data): Ticket
    {
        $changes = [];

        if (isset($data['priority'])) {
            if (!in_array($data['priority'], Ticket::PRIORITIES, true)) {
                throw new DisplayException('That priority does not exist.');
            }
            $changes['priority'] = $data['priority'];
        }

        if (array_key_exists('assigned_to', $data)) {
            if ($data['assigned_to'] !== null) {
                $assignee = User::query()->find($data['assigned_to']);
                if (!$assignee || !$assignee->isStaff()) {
                    throw new DisplayException('A ticket can only be given to a member of the staff.');
                }
            }
            $changes['assigned_to'] = $data['assigned_to'];
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], Ticket::STATUSES, true)) {
                throw new DisplayException('That status does not exist.');
            }
            if ($data['status'] === Ticket::CLOSED) {
                $changes += ['status' => Ticket::CLOSED, 'closed_at' => $ticket->closed_at ?? now(), 'closed_by' => $ticket->closed_by ?? $by->id];
            } else {
                $changes += ['status' => $data['status'], 'closed_at' => null, 'closed_by' => null];
            }
        }

        if ($changes !== []) {
            $ticket->update($changes);
        }

        return $ticket;
    }

    /**
     * The messages after the given one, oldest first. The person who opened the ticket does not get the internal notes.
     *
     * @return Collection<int, TicketMessage>
     */
    public function messagesAfter(Ticket $ticket, int $after, bool $asStaff): Collection
    {
        return $ticket->messages()
            ->with('author:id,username')
            ->where('id', '>', $after)
            ->when(!$asStaff, fn ($query) => $query->where('is_internal', false))
            ->get();
    }

    /**
     * Notes that the person has read the ticket up to a message.
     */
    public function markRead(Ticket $ticket, int $upTo): void
    {
        if ($upTo > $ticket->user_read_id) {
            $ticket->forceFill(['user_read_id' => $upTo])->saveQuietly();
        }
    }

    /**
     * How many tickets have an answer from the staff that the person has not read yet.
     */
    public function unreadCount(User $user): int
    {
        return Ticket::query()
            ->where('user_id', $user->id)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('ticket_messages')
                    ->whereColumn('ticket_messages.ticket_id', 'tickets.id')
                    ->where('ticket_messages.is_staff', true)
                    ->where('ticket_messages.is_internal', false)
                    ->whereColumn('ticket_messages.id', '>', 'tickets.user_read_id');
            })
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentMessage(TicketMessage $message, int $viewerId): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'staff' => $message->is_staff,
            'internal' => $message->is_internal,
            'author' => $message->author->username ?? '?',
            'mine' => $message->user_id === $viewerId,
            'at' => $message->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentTicket(Ticket $ticket, bool $unread = false): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'server' => $ticket->server_id ? ['id' => $ticket->server_id, 'name' => $ticket->server->name ?? null] : null,
            'unread' => $unread,
            'created_at' => $ticket->created_at->toIso8601String(),
            'last_message_at' => ($ticket->last_message_at ?? $ticket->created_at)->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
        ];
    }

    /**
     * A person can only link a ticket to a server they can open: their own, one shared with them, or any for an
     * administrator.
     */
    protected function canReferenceServer(User $user, int $serverId): bool
    {
        return $user->root_admin || $user->accessibleServers()->where('servers.id', $serverId)->exists();
    }

    private function write(Ticket $ticket, User $author, string $body, bool $staff, bool $internal): TicketMessage
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", $body));
        if ($body === '') {
            throw new DisplayException('The message is empty.');
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            throw new DisplayException('The message is too long (' . self::MAX_BODY . ' characters at most).');
        }

        return TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'body' => $body,
            'is_staff' => $staff,
            'is_internal' => $internal,
        ]);
    }
}
