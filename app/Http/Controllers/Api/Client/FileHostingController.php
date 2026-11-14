<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\UserFile;
use Pterodactyl\Services\Files\FileHosting;

/**
 * The files of the person who is signed in: the list and the space used, sending a file in pieces, renaming it,
 * sharing it or not, deleting it. Nobody sees the files of anyone else here.
 */
class FileHostingController extends ClientApiController
{
    public function __construct(private FileHosting $hosting)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $this->open();
        $user = $request->user();
        $config = $this->hosting->config();

        return new JsonResponse([
            'data' => UserFile::query()->where('user_id', $user->id)->orderByDesc('id')->limit(1000)->get()
                ->map(fn (UserFile $file) => $this->hosting->present($file))->all(),
            'meta' => [
                'used' => $this->hosting->used($user),
                'quota' => $this->hosting->quota($user),
                'max_file' => $this->hosting->maxFile($user),
                'chunk' => FileHosting::CHUNK,
                'media_only' => $config['types'] === 'media',
                'public_allowed' => $config['public'],
            ],
        ]);
    }

    public function begin(Request $request): JsonResponse
    {
        $this->open();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'folder' => ['nullable', 'string', 'max:500'],
        ]);

        return new JsonResponse(['data' => [
            'upload' => $this->hosting->begin($request->user(), $data['name'], (int) $data['size'], (string) ($data['folder'] ?? '')),
            'chunk' => FileHosting::CHUNK,
        ]]);
    }

    /**
     * A file no bigger than one piece, sent in a single request (the name, size and folder in the address, the file in
     * the body): a folder of many small files does not need three requests per file.
     */
    public function direct(Request $request): JsonResponse
    {
        $this->open();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:' . FileHosting::CHUNK],
            'folder' => ['nullable', 'string', 'max:500'],
        ]);
        $user = $request->user();
        $id = $this->hosting->begin($user, $data['name'], (int) $data['size'], (string) ($data['folder'] ?? ''));
        try {
            $this->hosting->chunk($user, $id, 0, $request->getContent(true));

            return new JsonResponse(['data' => $this->hosting->present($this->hosting->complete($user, $id))]);
        } catch (\Throwable $exception) {
            $this->hosting->cancel($user, $id);
            throw $exception;
        }
    }

    public function chunk(Request $request, string $upload): Response
    {
        $this->open();
        $index = (int) $request->query('index', '-1');
        $this->hosting->chunk($request->user(), $upload, $index, $request->getContent(true));

        return $this->returnNoContent();
    }

    public function complete(Request $request, string $upload): JsonResponse
    {
        $this->open();

        return new JsonResponse(['data' => $this->hosting->present($this->hosting->complete($request->user(), $upload))]);
    }

    public function cancel(Request $request, string $upload): Response
    {
        $this->hosting->cancel($request->user(), $upload);

        return $this->returnNoContent();
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $file = $this->find($request, $uuid);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:255'], 'public' => ['sometimes', 'boolean']]);
        if (array_key_exists('name', $data)) {
            $file->name = $this->hosting->cleanName($data['name']);
        }
        if (array_key_exists('public', $data)) {
            if ($data['public'] && !$this->hosting->config()['public']) {
                throw new DisplayException('Public links are turned off by the administration.');
            }
            $file->public = (bool) $data['public'];
        }
        $file->save();

        return new JsonResponse(['data' => $this->hosting->present($file)]);
    }

    /**
     * The same change on several files at once: delete them, share them or not, move them to a folder.
     */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:delete,public,private,move'],
            'files' => ['required', 'array', 'min:1', 'max:1000'],
            'files.*' => ['string', 'uuid'],
            'folder' => ['nullable', 'string', 'max:500'],
        ]);
        $user = $request->user();
        $files = UserFile::query()->where('user_id', $user->id)->whereIn('uuid', $data['files'])->get();

        if ($data['action'] === 'delete') {
            $files->each(fn (UserFile $file) => $this->hosting->delete($file));
        } elseif ($data['action'] === 'move') {
            $this->hosting->move($user, $files->pluck('uuid')->all(), (string) ($data['folder'] ?? ''));
        } else {
            if ($data['action'] === 'public' && !$this->hosting->config()['public']) {
                throw new DisplayException('Public links are turned off by the administration.');
            }
            UserFile::query()->whereIn('id', $files->pluck('id'))->update(['public' => $data['action'] === 'public']);
        }

        return new JsonResponse(['data' => ['count' => $files->count()]]);
    }

    public function delete(Request $request, string $uuid): Response
    {
        $this->hosting->delete($this->find($request, $uuid));

        return $this->returnNoContent();
    }

    private function find(Request $request, string $uuid): UserFile
    {
        /** @var UserFile $file */
        $file = UserFile::query()->where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();

        return $file;
    }

    private function open(): void
    {
        if (!$this->hosting->enabled()) {
            throw new DisplayException('The file hosting is not open.');
        }
    }
}
