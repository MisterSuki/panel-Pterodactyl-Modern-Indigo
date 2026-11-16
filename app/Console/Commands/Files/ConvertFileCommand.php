<?php

namespace Pterodactyl\Console\Commands\Files;

use Illuminate\Console\Command;
use Pterodactyl\Models\UserFileConversion;
use Pterodactyl\Services\Files\FileConverter;

/**
 * Converts one file of the file hosting (started in the background by the panel, see FileConverter).
 */
class ConvertFileCommand extends Command
{
    protected $signature = 'p:files:convert {id : The id of the conversion}';

    protected $description = 'Converts a file of the file hosting with ffmpeg (started by the panel).';

    public function handle(FileConverter $converter): int
    {
        /** @var UserFileConversion|null $conversion */
        $conversion = UserFileConversion::query()->find((int) $this->argument('id'));
        if (!$conversion) {
            $this->error('This conversion does not exist.');

            return self::FAILURE;
        }
        $converter->run($conversion);

        return self::SUCCESS;
    }
}
