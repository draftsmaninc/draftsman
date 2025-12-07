<?php

namespace Draftsman\Draftsman\Commands;

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;

class DraftsmanSnapshotCommand extends Command
{
    public $signature = 'draftsman:snapshot {--exclude=* : Sections to exclude (repeatable or comma-separated)}';

    public $description = 'Creates a snapshot of relevant Draftsman data for support/debugging.';

    private const array SECTIONS = [
        'config'   => 'getConfigData',
        'composer' => 'getComposerData',
        'about'    => 'getAboutData',
    ];

    public $snapshot = [];

    protected function configure(): void
    {
        parent::configure();
        $available = implode(', ', array_keys(self::SECTIONS));

        $this->setHelp("Exclude one or more sections. Available: $available\n" .
            "Examples:\n" .
            "  php artisan draftsman:snapshot --exclude=config --exclude=about\n" .
            "  php artisan draftsman:snapshot --exclude=config,about");
    }

    public function handle(): int
    {
        // Map snapshot sections to their loader methods
        $sections = [
            'config' => 'getConfigData',
            'composer' => 'getComposerData',
            'about' => 'getAboutData',
        ];

        $exclude = $this->normalizeExcludeOption();

        // Always include model data; this section cannot be excluded
        $this->getModelData();

        foreach ($sections as $name => $method) {
            if (in_array($name, $exclude, true)) {
                continue;
            }
            $this->{$method}();
        }

        // Persist snapshot to storage/app/private as JSON
        try {
            $savedPath = $this->saveSnapshot($this->snapshot);
            $this->info("Draftsman snapshot saved to: {$savedPath}");
        } catch (\Throwable $e) {
            $this->error('Failed to save Draftsman snapshot: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }

    /**
     * Normalize the --exclude option into a lowercase array of section names.
     * Supports multiple usages:
     *  - --exclude=config --exclude=about
     *  - --exclude=config,about
     * @return array
     */
    protected function normalizeExcludeOption(): array
    {
        $raw = $this->option('exclude');

        $items = [];

        if (is_array($raw)) {
            // Support: --exclude=a --exclude=b and also --exclude=a,b
            foreach ($raw as $entry) {
                if (!is_string($entry)) {
                    continue;
                }
                $parts = array_map('trim', explode(',', $entry));
                foreach ($parts as $p) {
                    $p = trim($p, "\"' ");
                    if ($p !== '') {
                        $items[] = $p;
                    }
                }
            }
        } elseif (is_string($raw)) {
            // Single string case: --exclude=a,b
            $trimmed = trim($raw);
            $parts = array_map('trim', explode(',', $trimmed));
            foreach ($parts as $p) {
                $p = trim($p, "\"' ");
                if ($p !== '') {
                    $items[] = $p;
                }
            }
        }

        // Normalize to lowercase unique values
        $items = array_values(array_unique(array_filter(array_map(function ($v) {
            return strtolower((string) $v);
        }, $items))));

        return $items;
    }

    protected function getModelData(): void
    {
        try {
            $api = new ApiController();
            $models = $api->getModels();
            // Store under a dedicated key in the snapshot
            $this->snapshot['models'] = $models;
        } catch (\Throwable $e) {
            $this->snapshot['models_error'] = 'Error collecting model data: ' . $e->getMessage();
        }
    }

    /**
     * Retrieves the Draftsman config file and adds it to the snapshot.
     * @return void
     */
    protected function getConfigData(): void
    {
        $configFilePath = base_path('config/draftsman.php');

        if (File::exists($configFilePath)) {
            try {
                // Read the file content
                $configFileContents = config('draftsman');
                $this->snapshot['config'] = $configFileContents;

            } catch (\Exception $e) {
                $this->snapshot['config_error'] = 'Error reading Draftsman config: ' . $e->getMessage();
            }
        } else {
            $this->snapshot['config_path'] = 'Draftsman config not found at ' . $configFilePath;
        }
    }

    /**
     * Retrieves the composer.json file and adds it to the snapshot.
     * @return void
     */
    protected function getComposerData(): void
    {
        $composerFilePath = base_path('composer.json');

        if (File::exists($composerFilePath)) {
            try {
                // Read the file content
                $composerFileContents = File::get($composerFilePath);

                // Decode the JSON into a PHP array
                $composerData = json_decode($composerFileContents, true);

                // Check for decoding errors
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Include the entire structured data
                    $this->snapshot['composer'] = $composerData;

                    // Optional: You could include only specific, high-value keys:
                    // $snapshot['composer_dependencies'] = Arr::only($composerData, ['require', 'require-dev']);
                } else {
                    // Handle JSON decoding failure
                    $this->snapshot['composer_error'] = 'Could not decode composer.json: ' . json_last_error_msg();
                    $this->snapshot['composer_raw'] = $composerFileContents;
                }

            } catch (\Exception $e) {
                $this->snapshot['composer_error'] = 'Error reading composer.json: ' . $e->getMessage();
            }
        } else {
            $this->snapshot['composer_path'] = 'composer.json not found at ' . $composerFilePath;
        }
    }

    /**
     * Retrieves the output from 'php artisan about' as JSON and adds it to the snapshot.
     * @return void
     */
    protected function getAboutData(): void
    {
        Artisan::call('about', ['--json' => true]);
        $aboutJSON = Artisan::output();
        $this->snapshot['about'] = json_decode($aboutJSON, true);
    }

    /**
     * Saves the provided snapshot array as a JSON file inside the app private storage directory.
     * Filename pattern: draftsman_snapshot_<YYYY-MM-DD_HH-mm-ss>.json
     *
     * @param array $snapshot
     * @return string Full path to the saved file
     */
    protected function saveSnapshot(array $snapshot): string
    {
        $dir = storage_path('app/private');

        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "draftsman_snapshot_{$timestamp}.json";
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Fallback: encode errors are unlikely with arrays from our sources, but guard anyway
            throw new \RuntimeException('Unable to encode snapshot to JSON: ' . json_last_error_msg());
        }

        File::put($path, $json);

        return $path;
    }
}
