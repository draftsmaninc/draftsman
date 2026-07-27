<?php

namespace Draftsman\Draftsman\Actions;

use Spatie\Browsershot\Browsershot;

/**
 * Turns a rendered graph page (the self-contained HTML from RenderGraphPage)
 * into an image or PDF by screenshotting it with Browsershot / headless Chrome.
 *
 * Browsershot is deliberately OPTIONAL (composer `suggest`, not `require`):
 * the html format works everywhere with zero extra dependencies, and hosts
 * that want png/jpeg/pdf opt in with:
 *
 *     composer require spatie/browsershot
 *     npm install puppeteer   (Chrome/Chromium for it to drive)
 *
 * The command checks available() before resolving any Browsershot symbol, so
 * nothing here explodes on hosts that never installed it.
 */
class RenderGraphImage
{
    /** Formats this action can produce — all via Browsershot. */
    public const FORMATS = ['png', 'jpeg', 'pdf'];

    public function available(): bool
    {
        return class_exists(Browsershot::class);
    }

    /** @param array{width: int, height: int}|null $size the rendered page's pixel dimensions */
    public function handle(string $html, string $format, string $path, ?array $size = null): void
    {
        $shot = Browsershot::html($html)->showBackground();

        // Web servers often run with a minimal PATH that misses version-managed
        // node (nvm, Herd, volta) — honor explicit binaries when configured.
        if ($node = config('draftsman.package.node_binary')) {
            $shot->setNodeBinary($node);
        }
        if ($npm = config('draftsman.package.npm_binary')) {
            $shot->setNpmBinary($npm);
        }

        if ($format === 'pdf') {
            // One page sized to the canvas — Chrome otherwise prints to default
            // paper and slices a large graph across pages.
            if ($size !== null) {
                $shot->paperSize($size['width'], $size['height'], 'px');
            }
            $shot->savePdf($path);

            return;
        }

        // fullPage sizes the capture to the document, so the whole graph lands
        // in frame regardless of its dimensions.
        $shot->fullPage()
            ->setScreenshotType($format === 'jpeg' ? 'jpeg' : 'png', $format === 'jpeg' ? 90 : null)
            ->save($path);
    }
}
