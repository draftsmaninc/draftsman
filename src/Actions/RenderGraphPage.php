<?php

namespace Draftsman\Draftsman\Actions;

use Illuminate\Support\Facades\File;

/**
 * Render a saved graph document as the static, JS-free HTML page.
 *
 * Shared by the `/draftsman/render/{slug}` route and the `draftsman:render`
 * command. Mirrors the DOM the live x-flow-schema canvas produces, styled by
 * the compiled frontend CSS, with edges drawn from the SVG paths frontv
 * captured at save time (edge.data.path). All geometry comes from the
 * document — no layout or routing math happens here.
 */
class RenderGraphPage
{
    /**
     * Compact type labels for the schema row pills, ported from frontv's
     * type-labels.ts — keep the two maps in sync. Unknown types fall back to
     * the base type with params stripped, untruncated: the row-type CSS clips
     * with an ellipsis, matching the live canvas.
     */
    protected const SHORT_TYPE_LABELS = [
        // varchar is 7 chars raw, so it needs a mapping to dodge the ellipsis;
        // the text sizes get the same letter-prefix shape as the int family.
        'varchar' => 'vchar',
        'text' => 'text',
        'tinytext' => 't text',
        'mediumtext' => 'm text',
        'longtext' => 'l text',
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
     * The rendered page for a saved graph, or null when the slug is invalid
     * or no document exists — callers decide whether that's a 404 or a
     * console error.
     */
    public function handle(string $slug): ?string
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) !== 1) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$slug.'.json';

        if (! File::exists($path)) {
            return null;
        }

        $document = json_decode(File::get($path), true);

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

        $frame = $this->frame($document);

        return view('draftsman::graph-render', [
            'name' => $document['name'] ?? $slug,
            'nodes' => $nodes,
            'edges' => $edges,
            'width' => $frame['width'],
            'height' => $frame['height'],
            'offsetX' => $frame['offsetX'],
            'offsetY' => $frame['offsetY'],
            'css' => File::get(dirname(__DIR__, 2).'/resources/render/draftsman-render.css'),
        ])->render();
    }

    /**
     * The rendered page's pixel dimensions for a saved graph, or null when it
     * doesn't exist. Callers that rasterize the page (pdf paper sizing) need
     * these WITHOUT parsing them back out of the HTML.
     *
     * @return array{width: int, height: int}|null
     */
    public function dimensions(string $slug): ?array
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) !== 1) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$slug.'.json';

        if (! File::exists($path)) {
            return null;
        }

        $frame = $this->frame(json_decode(File::get($path), true));

        return ['width' => $frame['width'], 'height' => $frame['height']];
    }

    /**
     * Canvas frame for a graph document: the nodes' bounding box plus padding,
     * and the offset that shifts content into it. Single source of truth for
     * both the blade's container size and dimensions().
     *
     * @return array{width: int, height: int, offsetX: int, offsetY: int}
     */
    protected function frame(array $document): array
    {
        $pad = 56;
        $nodes = collect($document['nodes'] ?? [])->map(fn (array $node) => [
            'x' => $node['position']['x'] ?? 0,
            'y' => $node['position']['y'] ?? 0,
            'width' => $node['dimensions']['width'] ?? ($document['layout']['columnWidth'] ?? 308),
            'height' => $node['dimensions']['height'] ?? 168,
        ]);

        $minX = (int) floor(min([0, ...$nodes->pluck('x')]));
        $minY = (int) floor(min([0, ...$nodes->pluck('y')]));
        $maxX = (int) ceil(max([0, ...$nodes->map(fn ($n) => $n['x'] + $n['width'])]));
        $maxY = (int) ceil(max([0, ...$nodes->map(fn ($n) => $n['y'] + $n['height'])]));

        return [
            'width' => $maxX - $minX + 2 * $pad,
            'height' => $maxY - $minY + 2 * $pad,
            'offsetX' => $pad - $minX,
            'offsetY' => $pad - $minY,
        ];
    }

    public function directory(): string
    {
        return (string) config('draftsman.package.graphs_path', base_path('draftsman'));
    }

    protected function shortTypeLabel(?string $type): string
    {
        if ($type === null || trim($type) === '') {
            return '';
        }

        $base = trim(strtolower((string) preg_replace('/\(.*\)\s*$/', '', $type)));

        return self::SHORT_TYPE_LABELS[$base] ?? $base;
    }
}
