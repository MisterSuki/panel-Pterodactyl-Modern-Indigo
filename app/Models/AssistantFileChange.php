<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A change of a file proposed by the AI assistant of a server, applied by the person (or not), and put back when they
 * want.
 *
 * @property int $id
 * @property int $server_id
 * @property int $user_id
 * @property string $path
 * @property string $summary
 * @property string|null $before null when the change creates the file
 * @property string $after
 * @property string $hunks JSON: [{"removed": "...", "added": "..."}]
 * @property string $state proposed, applied, reverted or dismissed
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property \Illuminate\Support\Carbon|null $reverted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AssistantFileChange extends Model
{
    public const PROPOSED = 'proposed';
    public const APPLIED = 'applied';
    public const REVERTED = 'reverted';
    public const DISMISSED = 'dismissed';

    protected $table = 'assistant_file_changes';

    protected $fillable = ['server_id', 'user_id', 'path', 'summary', 'before', 'after', 'hunks', 'state', 'applied_at', 'reverted_at'];

    protected $casts = ['server_id' => 'integer', 'user_id' => 'integer', 'applied_at' => 'datetime', 'reverted_at' => 'datetime'];
}
