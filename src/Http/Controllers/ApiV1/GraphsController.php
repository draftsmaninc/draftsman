<?php

namespace Draftsman\Draftsman\Http\Controllers\ApiV1;

use Draftsman\Draftsman\Actions\RenderGraphPage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\File;

class GraphsController extends BaseController
{
    /**
     * Graph documents are stored one-per-file in the host app's repo so they
     * can be committed and diffed alongside the models they describe.
     */
    public function index()
    {
        $directory = $this->directory();

        $graphs = collect(File::isDirectory($directory) ? File::files($directory) : [])
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->map(function ($file) {
                $document = json_decode(File::get($file->getPathname()), true);

                return [
                    'slug' => $file->getFilenameWithoutExtension(),
                    'name' => $document['name'] ?? $file->getFilenameWithoutExtension(),
                    'updated_at' => date(DATE_ATOM, $file->getMTime()),
                ];
            })
            ->sortBy('slug')
            ->values();

        return response()->json(['graphs' => $graphs]);
    }

    public function show(string $slug)
    {
        $path = $this->pathFor($slug);

        abort_unless(File::exists($path), 404);

        return response()->json(['graph' => json_decode(File::get($path), true)]);
    }

    public function store(Request $request, string $slug)
    {
        $path = $this->pathFor($slug);

        $request->validate([
            'version' => ['required', 'integer'],
            'name' => ['required', 'string'],
            'layout' => ['sometimes', 'array'],
            'nodes' => ['present', 'array'],
            'edges' => ['present', 'array'],
        ]);

        // Persist the full payload, not just the validated keys, so newer
        // frontends can round-trip fields this package version predates.
        $document = $request->all();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return response()->json(['graph' => $document]);
    }

    /**
     * The static render page for a saved graph — see Actions\RenderGraphPage,
     * which the draftsman:render command shares.
     */
    public function render(string $slug, RenderGraphPage $renderer)
    {
        $html = $renderer->handle($slug);

        abort_if($html === null, 404);

        return response($html);
    }

    public function destroy(string $slug)
    {
        $path = $this->pathFor($slug);

        abort_unless(File::exists($path), 404);

        File::delete($path);

        return response()->noContent();
    }

    protected function directory(): string
    {
        return (string) config('draftsman.package.graphs_path', base_path('draftsman'));
    }

    /**
     * Slugs are the only user-controlled part of the file path, so anything
     * outside the strict lowercase slug alphabet 404s before touching disk.
     */
    protected function pathFor(string $slug): string
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) === 1, 404);

        return $this->directory().DIRECTORY_SEPARATOR.$slug.'.json';
    }
}
