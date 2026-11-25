<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A link that connects the Claude app to the panel (an MCP connector), with the rights of its owner.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash sha256 of the secret of the link
 * @property bool $read_only
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User $user
 */
class ClaudeConnection extends Model
{
    protected $table = 'claude_connections';

    protected $fillable = ['user_id', 'name', 'token_hash', 'read_only', 'last_used_at'];

    protected $casts = ['user_id' => 'integer', 'read_only' => 'boolean', 'last_used_at' => 'datetime'];

    protected $hidden = ['token_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
