<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message of a ticket.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $body
 * @property bool $is_staff
 * @property bool $is_internal
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Pterodactyl\Models\User $author
 * @property \Pterodactyl\Models\Ticket $ticket
 */
class TicketMessage extends Model
{
    protected $table = 'ticket_messages';

    protected $guarded = ['id'];

    protected $casts = [
        'ticket_id' => 'integer',
        'user_id' => 'integer',
        'is_staff' => 'boolean',
        'is_internal' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
