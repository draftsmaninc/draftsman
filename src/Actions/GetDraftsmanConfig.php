<?php

namespace Draftsman\Draftsman\Actions;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class GetDraftsmanConfig
{
    /**
     * Load the Draftsman config array.
     * Ensures published config exists; falls back to vendor default.
     *
     * @return array{config: array}
     */
    public function handle(): array
    {
        $publishedConfigPath = base_path('config/draftsman.php');
        $vendorConfigPath = base_path('vendor/draftsmaninc/draftsman/config/draftsman.php');

        // Ensure published config exists; if missing, attempt to publish
        if (! File::exists($publishedConfigPath)) {
            try {
                Artisan::call('vendor:publish', ['--tag' => 'draftsman-config']);
            } catch (\Throwable $e) {
                // ignore publish failures; we'll fall back to vendor
            }
        }

        $config = [];
        if (File::exists($publishedConfigPath)) {
            $loaded = include $publishedConfigPath;
            $config = is_array($loaded) ? $loaded : [];
        } elseif (File::exists($vendorConfigPath)) {
            $loaded = include $vendorConfigPath;
            $config = is_array($loaded) ? $loaded : [];
        }

        return [
            'config' => $config,
        ];
    }
}
