<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\UserFile;
use Pterodactyl\Services\Files\FileHosting;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The link of a hosted file (/f/<token>): anyone with the link when the file is public, only its owner otherwise.
 * Safe pictures, videos and sounds are shown (videos can be sought); every other file is downloaded, always inside a
 * strict sandbox, so nothing in a file can run on the domain of the panel.
 */
class UserFileController extends Controller
{
    public function __construct(private FileHosting $hosting)
    {
    }

    public function show(Request $request, string $token): BinaryFileResponse
    {
        /** @var UserFile|null $file */
        $file = preg_match('/^[A-Za-z0-9]{24}$/', $token) === 1 ? UserFile::query()->where('token', $token)->first() : null;
        if (!$file || !$this->hosting->enabled() || !is_file($this->hosting->path($file))) {
            throw new NotFoundHttpException();
        }
        if (!$file->public && $request->user()?->id !== $file->user_id) {
            throw new NotFoundHttpException();
        }

        // One view per opening, not per piece of a video.
        $range = (string) $request->headers->get('Range', '');
        if ($range === '' || str_starts_with($range, 'bytes=0-')) {
            UserFile::query()->whereKey($file->id)->increment('views');
        }

        $inline = $this->hosting->inline($file) && !$request->boolean('dl');
        $response = new BinaryFileResponse($this->hosting->path($file), 200, [
            'Content-Type' => $inline ? $file->mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => $file->public ? 'public, max-age=86400' : 'private, no-store',
        ], true, null, false, false);
        $fallback = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($file->name)) ?: 'file';
        $response->setContentDisposition($inline ? 'inline' : 'attachment', $file->name, $fallback);

        return $response;
    }
}
