<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A support ticket: a conversation between a person and the staff.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $server_id
 * @property string $subject
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property int|null $assigned_to
 * @property int $user_read_id
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int|null $closed_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Pterodactyl\Models\User $user
 * @property \Pterodactyl\Models\User|null $assignee
 * @property \Pterodactyl\Models\Server|null $server
 * @property \Illuminate\Database\Eloquent\Collection<int, \Pterodactyl\Models\TicketMessage> $messages
 */
class Ticket extends Model
{
    public const OPEN = 'open';

    public const ANSWERED = 'answered';

    public const CLOSED = 'closed';

    public const STATUSES = [self::OPEN, self::ANSWERED, self::CLOSED];

    public const CATEGORIES = ['general', 'technical', 'billing', 'other'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $table = 'tickets';

    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'server_id' => 'integer',
        'assigned_to' => 'integer',
        'user_read_id' => 'integer',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id')->without('allocation');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id')->orderBy('id');
    }
}
