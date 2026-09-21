<?php

namespace Pterodactyl\Http\Controllers\Admin\Nests;

use Pterodactyl\Models\Nest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Eggs\CommunityEggCatalog;
use Pterodactyl\Services\Eggs\Sharing\EggImporterService;

/**
 * Adds eggs of https://eggs.pterodactyl.io/ to a nest from the Nests page: search by name, pick a nest, import.
 */
class CommunityEggController extends Controller
{
    public function __construct(private CommunityEggCatalog $catalog, private EggImporterService $importer)
    {
    }

    /**
     * Eggs of the site that match what was typed.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        try {
            $results = $this->catalog->search(mb_substr($query, 0, 80));
        } catch (DisplayException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 502);
        }

        return new JsonResponse(['data' => $results]);
    }

    /**
     * Downloads the egg and imports it into the chosen nest, like the "Import Egg" button does with a file.
     * An egg with the same name in that nest is only added again when the caller insists (force).
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^(games|applications|generic)-[a-z0-9][a-z0-9-]*$/'],
            'nest_id' => ['required', 'integer', 'exists:nests,id'],
            'force' => ['sometimes', 'boolean'],
        ]);

        try {
            $egg = $this->catalog->find($data['slug']);
            if ($egg === null) {
                return new JsonResponse(['error' => 'That egg is not in the list of eggs.pterodactyl.io.'], 404);
            }

            /** @var Nest $nest */
            $nest = Nest::query()->findOrFail($data['nest_id']);
            if (empty($data['force']) && $nest->eggs()->where('name', $egg['name'])->exists()) {
                return new JsonResponse([
                    'error' => sprintf('The nest "%s" already has an egg named "%s".', $nest->name, $egg['name']),
                    'exists' => true,
                ], 409);
            }

            $json = $this->catalog->download($data['slug']);
        } catch (DisplayException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 502);
        }

        $path = tempnam(sys_get_temp_dir(), 'egg');
        try {
            file_put_contents($path, $json);
            $imported = $this->importer->handle(new UploadedFile($path, $data['slug'] . '.json', 'application/json', UPLOAD_ERR_OK, true), $nest->id);
        } catch (\Throwable $exception) {
            report($exception);

            return new JsonResponse(['error' => 'The panel refused this egg: ' . $exception->getMessage()], 422);
        } finally {
            @unlink($path);
        }

        return new JsonResponse([
            'egg' => ['id' => $imported->id, 'name' => $imported->name],
            'nest' => ['id' => $nest->id, 'name' => $nest->name],
            'url' => route('admin.nests.egg.view', ['egg' => $imported->id]),
        ]);
    }
}
