<?php

namespace Pterodactyl\Extensions\Themes;

class Theme
{
    public function js($path): string
    {
        return sprintf('<script src="%s"></script>' . PHP_EOL, $this->getUrl($path));
    }

    public function css($path): string
    {
        return sprintf('<link media="all" type="text/css" rel="stylesheet" href="%s"/>' . PHP_EOL, $this->getUrl($path));
    }

    /**
     * The address of a file of the theme, with the date of the file: when the file changes (an update), the address
     * changes, and a browser cannot go on using the old copy it kept.
     */
    protected function getUrl($path): string
    {
        $clean = explode('?', ltrim((string) $path, '/'))[0];
        $file = public_path('themes/pterodactyl/' . $clean);

        return '/themes/pterodactyl/' . $clean . '?t=' . (is_file($file) ? filemtime($file) : config('app.version', '1'));
    }
}
