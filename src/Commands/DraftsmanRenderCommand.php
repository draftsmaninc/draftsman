<?php

namespace Draftsman\Draftsman\Commands;

use Draftsman\Draftsman\Actions\RenderGraphImage;
use Draftsman\Draftsman\Actions\RenderGraphPage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DraftsmanRenderCommand extends Command
{
    public $signature = 'draftsman:render '
        .'{slug : The saved graph to render (its file name without .json)} '
        .'{--format=html : html (built in) · png, jpeg, pdf (require spatie/browsershot)} '
        .'{--path= : Output file. Defaults to <graphs_path>/<slug>.<format>}';

    public $description = 'Renders a saved graph document to a self-contained file.';

    public function handle(RenderGraphPage $renderer, RenderGraphImage $imageRenderer): int
    {
        $slug = (string) $this->argument('slug');
        $format = strtolower((string) $this->option('format'));
        if ($format === 'jpg') {
            $format = 'jpeg';
        }

        if ($format === 'svg') {
            $this->error('SVG output is not available yet — it needs a true vector emitter, not a screenshot. '
                .'Available now: html (built in) and png, jpeg, pdf (with spatie/browsershot).');

            return self::FAILURE;
        }

        if ($format !== 'html' && ! in_array($format, RenderGraphImage::FORMATS, true)) {
            $this->error("Unknown format \"{$format}\". Available: html (built in) and png, jpeg, pdf (with spatie/browsershot).");

            return self::FAILURE;
        }

        if ($format !== 'html' && ! $imageRenderer->available()) {
            $this->error("Format \"{$format}\" needs Browsershot (headless Chrome). Install it in this app:");
            $this->line('  composer require spatie/browsershot');
            $this->line('  npm install puppeteer');
            $this->line('  npx puppeteer browsers install chrome-headless-shell');
            $this->line('html needs no extra dependencies: draftsman:render '.$slug);

            return self::FAILURE;
        }

        $html = $renderer->handle($slug);

        if ($html === null) {
            $this->error("No saved graph \"{$slug}\" — save it from the Draftsman UI first.");

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: $renderer->directory().DIRECTORY_SEPARATOR.$slug.'.'.$format);
        File::ensureDirectoryExists(dirname($path));

        if ($format === 'html') {
            File::put($path, $html);
        } else {
            $imageRenderer->handle($html, $format, $path, $renderer->dimensions($slug));
        }

        $this->info("Rendered \"{$slug}\" to {$path}");

        return self::SUCCESS;
    }
}
