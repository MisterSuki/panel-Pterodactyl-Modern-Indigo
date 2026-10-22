<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A message of the bell of the panel: for one person (user_id) or for everybody (user_id empty, an announcement).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $kind
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property bool $public
 * @property \Carbon\Carbon $created_at
 */
class PanelNotification extends Model
{
    public const KINDS = ['info', 'success', 'warning', 'danger', 'maintenance', 'update'];

    public const UPDATED_AT = null;

    protected $table = 'panel_notifications';

    protected $guarded = ['id'];

    protected $casts = ['public' => 'boolean'];
}
