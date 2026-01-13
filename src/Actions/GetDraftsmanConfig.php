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
        $storageConfigPath = storage_path('draftsman/config.php');

        $config = [];
        if (File::exists($publishedConfigPath)) {
            $loaded = include $publishedConfigPath;
            $config = is_array($loaded) ? $loaded : [];
        } elseif (File::exists($vendorConfigPath)) {
            $loaded = include $vendorConfigPath;
            $config = is_array($loaded) ? $loaded : [];
        }

        if (File::exists($storageConfigPath)) {
            $storageConfig = include $storageConfigPath;
            if (is_array($storageConfig) && isset($storageConfig['presentation'])) {
                $config['presentation'] = $storageConfig['presentation'];
            }
        }

        return [
            'config' => $config,
        ];
    }
}
