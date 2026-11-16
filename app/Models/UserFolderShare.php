<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A folder of the file hosting shared with a public link: its files, and those of its folders, can be seen by anyone
 * with the link.
 *
 * @property int $id
 * @property int $user_id
 * @property string $folder "Holidays/2026"
 * @property string $token
 * @property int $views
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User $user
 */
class UserFolderShare extends Model
{
    protected $table = 'user_folder_shares';

    protected $fillable = ['user_id', 'folder', 'token', 'views'];

    protected $casts = ['user_id' => 'integer', 'views' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
