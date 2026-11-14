<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file a user keeps on the panel (a picture, a video, anything), with a link to share it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $name
 * @property string $folder "" for the top, "Holidays/2026" for a folder in a folder
 * @property string $path relative to storage/app
 * @property string $mime
 * @property int $size
 * @property string $token
 * @property bool $public
 * @property int $views
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User $user
 */
class UserFile extends Model
{
    protected $table = 'user_files';

    protected $fillable = ['uuid', 'user_id', 'name', 'folder', 'path', 'mime', 'size', 'token', 'public', 'views'];

    protected $casts = ['user_id' => 'integer', 'size' => 'integer', 'public' => 'boolean', 'views' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
