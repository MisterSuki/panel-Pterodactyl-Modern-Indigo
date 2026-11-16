<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A file of the file hosting being converted into another format by ffmpeg (in the background).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $file_id
 * @property string $name
 * @property string $format
 * @property string $state queued, running, done or failed
 * @property int $progress 0 to 100
 * @property string|null $error
 * @property int|null $result_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UserFileConversion extends Model
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const DONE = 'done';
    public const FAILED = 'failed';

    protected $table = 'user_file_conversions';

    protected $fillable = ['user_id', 'file_id', 'name', 'format', 'state', 'progress', 'error', 'result_id'];

    protected $casts = ['user_id' => 'integer', 'file_id' => 'integer', 'progress' => 'integer', 'result_id' => 'integer'];

    public function active(): bool
    {
        return in_array($this->state, [self::QUEUED, self::RUNNING], true);
    }
}
