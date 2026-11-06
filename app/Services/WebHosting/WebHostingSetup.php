<?php

namespace Pterodactyl\Services\WebHosting;

use Illuminate\Http\UploadedFile;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Services\Eggs\Sharing\EggImporterService;
use Pterodactyl\Services\Eggs\Sharing\EggUpdateImporterService;
use Pterodactyl\Services\Nests\NestCreationService;

/**
 * Creates the nest of the websites and its eggs, or brings them up to date. It can run any number of times.
 */
class WebHostingSetup
{
    public const NEST = 'Web Hosting';

    private const AUTHOR = 'mistersuki@proton.me';

    public function __construct(
        private NestCreationService $nests,
        private EggImporterService $importer,
        private EggUpdateImporterService $updater,
    ) {
    }

    /**
     * @return string[] the names of the eggs
     */
    public function run(): array
    {
        $nest = Nest::query()->where('author', self::AUTHOR)->where('name', self::NEST)->first();
        if ($nest === null) {
            $nest = $this->nests->handle([
                'name' => self::NEST,
                'description' => 'Websites: PHP, HTML, Node.js and Python, with their domain and HTTPS.',
            ], self::AUTHOR);
        }

        $names = [];
        foreach (glob(database_path('Seeders/eggs/web-hosting/*.json')) ?: [] as $path) {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $file = new UploadedFile($path, basename($path), 'application/json', null, true);

            $egg = $nest->eggs()->where('author', $decoded['author'])->where('name', $decoded['name'])->first();
            if ($egg instanceof Egg) {
                $this->updater->handle($egg, $file);
            } else {
                $this->importer->handle($file, $nest->id);
            }
            $names[] = (string) $decoded['name'];
        }

        return $names;
    }

    public function installed(): bool
    {
        return Nest::query()->where('author', self::AUTHOR)->where('name', self::NEST)->whereHas('eggs')->exists();
    }
}
