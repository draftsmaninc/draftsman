<?php

namespace Draftsman\Draftsman\Http\Controllers\ApiV1;

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
     * Compact type labels for the schema row pills, ported from frontv's
     * type-labels.ts — keep the two maps in sync. Unknown types fall back to
     * the base type with params stripped, untruncated: the row-type CSS clips
     * with an ellipsis, matching the live canvas.
     */
    protected const SHORT_TYPE_LABELS = [
        'text' => 'text',
        'integer' => 'int',
        'integer unsigned' => 'u int',
        'int' => 'int',
        'int unsigned' => 'u int',
        'smallint' => 's int',
        'smallint unsigned' => 'us int',
        'tinyint' => 't int',
        'tinyint unsigned' => 'ut int',
        'mediumint' => 'm int',
        'mediumint unsigned' => 'um int',
        'bigint' => 'b int',
        'bigint unsigned' => 'ub int',
        'boolean' => 'bool',
        'bool' => 'bool',
        'decimal' => 'dec',
        'numeric' => 'dec',
        'float' => 'float',
        'double' => 'dbl',
        'real' => 'real',
        'timestamp' => 'tstamp',
        'datetime' => 'dtime',
        'date' => 'date',
        'time' => 'time',
        'year' => 'year',
        'json' => 'json',
        'jsonb' => 'json',
        'uuid' => 'uuid',
        'binary' => 'bin',
        'blob' => 'blob',
        'enum' => 'enum',
    ];

    /**
     * Render a saved graph as a static, JS-free HTML page: the same DOM +
     * classes the live x-flow-schema canvas produces, styled by the compiled
     * frontend CSS, with edges drawn from the SVG paths frontv captured at
     * save time (edge.data.path). All geometry comes from the document — no
     * layout or routing math happens here.
     */
    public function render(string $slug)
    {
        $path = $this->pathFor($slug);

        abort_unless(File::exists($path), 404);

        $document = json_decode(File::get($path), true);
        $pad = 56;

        $nodes = collect($document['nodes'] ?? [])->map(function (array $node) use ($document) {
            $fields = collect($node['data']['fields'] ?? [])->map(fn (array $field) => [
                'name' => $field['name'] ?? '',
                'type' => $this->shortTypeLabel($field['type'] ?? null),
                'classes' => implode(' ', array_filter([
                    'flow-schema-row',
                    ($field['key'] ?? null) === 'primary' ? 'flow-schema-row--pk' : null,
                    ($field['key'] ?? null) === 'foreign' ? 'flow-schema-row--fk' : null,
                    ($field['required'] ?? false) ? 'flow-schema-row--required' : null,
                ])),
            ]);

            return [
                'label' => $node['data']['label'] ?? $node['id'],
                'namespace' => $node['data']['namespace'] ?? '',
                'x' => $node['position']['x'] ?? 0,
                'y' => $node['position']['y'] ?? 0,
                'width' => $node['dimensions']['width'] ?? ($document['layout']['columnWidth'] ?? 308),
                'height' => $node['dimensions']['height'] ?? 168,
                'fields' => $fields,
            ];
        });

        $edges = collect($document['edges'] ?? [])
            ->filter(fn (array $edge) => ! empty($edge['data']['path']))
            ->map(fn (array $edge) => ['d' => $edge['data']['path']])
            ->values();

        $minX = (int) floor(min([0, ...$nodes->pluck('x')]));
        $minY = (int) floor(min([0, ...$nodes->pluck('y')]));
        $maxX = (int) ceil(max([0, ...$nodes->map(fn ($n) => $n['x'] + $n['width'])]));
        $maxY = (int) ceil(max([0, ...$nodes->map(fn ($n) => $n['y'] + $n['height'])]));

        return view('draftsman::graph-render', [
            'name' => $document['name'] ?? $slug,
            'nodes' => $nodes,
            'edges' => $edges,
            'width' => $maxX - $minX + 2 * $pad,
            'height' => $maxY - $minY + 2 * $pad,
            'offsetX' => $pad - $minX,
            'offsetY' => $pad - $minY,
            'css' => File::get(dirname(__DIR__, 4).'/resources/render/draftsman-render.css'),
        ]);
    }

    protected function shortTypeLabel(?string $type): string
    {
        if ($type === null || trim($type) === '') {
            return '';
        }

        $base = trim(strtolower((string) preg_replace('/\(.*\)\s*$/', '', $type)));

        return self::SHORT_TYPE_LABELS[$base] ?? $base;
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
