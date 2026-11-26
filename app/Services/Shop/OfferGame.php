<?php

namespace Pterodactyl\Services\Shop;

use Pterodactyl\Models\Egg;

/**
 * The game (or kind of server) of an egg, for the shop: its look (icon and colours, chosen by the page) and the
 * formulas the offer generator makes for it — memory, disk and CPU that suit it.
 */
class OfferGame
{
    /**
     * The formulas of each kind: [name, memory MB, disk MB, CPU %, databases, backups, extra ports].
     *
     * @var array<string, array<int, array{0: string, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int}>>
     */
    private const TIERS = [
        'minecraft' => [['Starter', 2048, 10240, 100, 1, 1, 0], ['Standard', 4096, 20480, 150, 2, 2, 1], ['Pro', 8192, 40960, 250, 3, 3, 2], ['Ultra', 12288, 61440, 400, 5, 5, 3]],
        'fivem' => [['Starter', 4096, 20480, 150, 1, 1, 1], ['Standard', 6144, 30720, 200, 2, 2, 1], ['Pro', 8192, 51200, 300, 3, 3, 2], ['Ultra', 12288, 81920, 400, 5, 5, 3]],
        'survival' => [['Starter', 4096, 20480, 150, 0, 1, 1], ['Standard', 8192, 30720, 200, 1, 2, 1], ['Pro', 12288, 51200, 300, 1, 3, 2], ['Ultra', 16384, 81920, 400, 2, 5, 3]],
        'bot' => [['Starter', 512, 2048, 50, 1, 1, 0], ['Standard', 1024, 5120, 100, 1, 2, 0], ['Pro', 2048, 10240, 150, 2, 3, 1], ['Ultra', 4096, 20480, 200, 3, 5, 1]],
        'website' => [['Starter', 1024, 5120, 50, 1, 1, 0], ['Standard', 2048, 10240, 100, 2, 2, 0], ['Pro', 4096, 20480, 200, 3, 3, 0], ['Ultra', 8192, 40960, 300, 5, 5, 0]],
        'linux' => [['Starter', 1024, 10240, 100, 0, 1, 1], ['Standard', 2048, 20480, 150, 0, 2, 2], ['Pro', 4096, 40960, 200, 1, 3, 3], ['Ultra', 8192, 81920, 300, 2, 5, 4]],
        'game' => [['Starter', 2048, 10240, 100, 1, 1, 0], ['Standard', 4096, 20480, 200, 1, 2, 1], ['Pro', 8192, 40960, 300, 2, 3, 2], ['Ultra', 16384, 81920, 400, 3, 5, 3]],
    ];

    /**
     * The kind of an egg, from its features, its name and the name of its nest.
     */
    public static function of(?Egg $egg): string
    {
        if (!$egg) {
            return 'game';
        }
        $features = (array) ($egg->inherit_features ?? $egg->features ?? []);
        if (in_array('web', $features, true)) {
            return 'website';
        }
        if (in_array('mini_vps', $features, true)) {
            return 'linux';
        }
        if (in_array('eula', $features, true) || in_array('java_version', $features, true)) {
            return 'minecraft';
        }
        $name = strtolower($egg->name . ' ' . ($egg->relationLoaded('nest') ? (string) $egg->nest->name : ''));

        return match (true) {
            (bool) preg_match('/minecraft|paper|spigot|forge|fabric|purpur|bungee|velocity|bedrock/', $name) => 'minecraft',
            (bool) preg_match('/fivem|fxserver|redm|gta|nova-?life/', $name) => 'fivem',
            (bool) preg_match('/rust|ark|valheim|palworld|7 ?days|conan|dayz|satisfactory|enshrouded|v rising|project zomboid|sons of the forest/', $name) => 'survival',
            (bool) preg_match('/node|python|discord|bot|java generic|golang|bun/', $name) => 'bot',
            default => 'game',
        };
    }

    /**
     * The formulas the generator offers for a kind (3 or 4 of them).
     *
     * @return array<int, array{name: string, memory: int, disk: int, cpu: int, databases: int, backups: int, ports: int}>
     */
    public static function tiers(string $kind, int $count): array
    {
        $rows = self::TIERS[$kind] ?? self::TIERS['game'];
        $rows = $count <= 3 ? array_slice($rows, 0, 3) : $rows;

        return array_map(fn (array $row) => [
            'name' => $row[0], 'memory' => $row[1], 'disk' => $row[2], 'cpu' => $row[3],
            'databases' => $row[4], 'backups' => $row[5], 'ports' => $row[6],
        ], $rows);
    }

    /**
     * A price for a formula: per GB of memory, never under the minimum, ending in .99 (in cents).
     */
    public static function price(int $memoryMb, int $centsPerGb, int $minimumCents): int
    {
        $cents = max($minimumCents, (int) round($memoryMb / 1024 * $centsPerGb));

        return max(99, (int) ceil($cents / 100) * 100 - 1);
    }
}
