<?php

namespace Draftsman\Draftsman\Commands;

use Draftsman\Draftsman\Actions\RenderGraphPage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DraftsmanRenderCommand extends Command
{
    public $signature = 'draftsman:render '
        .'{slug : The saved graph to render (its file name without .json)} '
        .'{--format=html : Output format (html for now; svg and png are planned)} '
        .'{--path= : Output file. Defaults to <graphs_path>/<slug>.<format>}';

    public $description = 'Renders a saved graph document to a self-contained file.';

    public function handle(RenderGraphPage $renderer): int
    {
        $slug = (string) $this->argument('slug');
        $format = strtolower((string) $this->option('format'));

        if ($format !== 'html') {
            $this->error("Format \"{$format}\" is not available yet — only html is. svg and png are planned.");

            return self::FAILURE;
        }

        $html = $renderer->handle($slug);

        if ($html === null) {
            $this->error("No saved graph \"{$slug}\" — save it from the Draftsman UI first.");

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: $renderer->directory().DIRECTORY_SEPARATOR.$slug.'.html');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $html);

        $this->info("Rendered \"{$slug}\" to {$path}");

        return self::SUCCESS;
    }
}
