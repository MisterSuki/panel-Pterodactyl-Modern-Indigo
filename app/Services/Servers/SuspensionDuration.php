<?php

namespace Pterodactyl\Services\Servers;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Turns the length chosen in the suspension form into the date the suspension ends.
 */
class SuspensionDuration
{
    /**
     * Returned when the end date of an existing suspension is to be left alone.
     */
    public const KEEP = 'keep';

    public const FOREVER = 'forever';

    public const CUSTOM = 'custom';

    /**
     * The lengths the form offers, with what they mean.
     */
    public const PRESETS = [
        '1h' => '+1 hour',
        '6h' => '+6 hours',
        '24h' => '+24 hours',
        '3d' => '+3 days',
        '7d' => '+7 days',
        '30d' => '+30 days',
    ];

    /**
     * Every value the form may send.
     */
    public const CHOICES = ['forever', 'keep', 'custom', '1h', '6h', '24h', '3d', '7d', '30d'];

    /**
     * The end date for a choice: a date, null for "until an administrator lifts it", or KEEP.
     *
     * @return CarbonInterface|string|null
     */
    public static function resolve(?string $choice, ?string $custom = null, ?CarbonInterface $from = null): CarbonInterface|string|null
    {
        $from ??= Carbon::now();

        if ($choice === self::KEEP) {
            return self::KEEP;
        }

        if ($choice === self::CUSTOM && !empty($custom)) {
            // Typed in the panel's own timezone, like the dates everywhere else in the admin area.
            return Carbon::parse($custom, config('app.timezone'));
        }

        if ($choice !== null && isset(self::PRESETS[$choice])) {
            return $from->copy()->modify(self::PRESETS[$choice]);
        }

        return null;
    }
}
