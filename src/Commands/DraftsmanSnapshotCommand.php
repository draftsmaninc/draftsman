<?php

namespace Draftsman\Draftsman\Commands;

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class DraftsmanSnapshotCommand extends Command
{
    public $signature = 'draftsman:snapshot '
        .'{--exclude=* : Sections to exclude (repeatable or comma-separated)} '
        .'{--path= : Output file or directory for the snapshot. Defaults to config(draftsman.snapshot_path)}';

    public $description = 'Creates a snapshot of relevant Draftsman data for support/debugging.';

    private const array SECTIONS = [
        'config' => 'getConfigData',
        'composer' => 'getComposerData',
        'about' => 'getAboutData',
    ];

    public $snapshot = [];

    protected function configure(): void
    {
        parent::configure();
        $available = implode(', ', array_keys(self::SECTIONS));

        $defaultPath = (string) (config('draftsman.snapshot_path') ?? 'storage/app/draftsman/snapshots');
        $this->setHelp("Exclude one or more sections. Available: $available\n".
            "Options:\n".
            "  --exclude=SECTION   Repeatable or comma-separated list to exclude sections.\n".
            "  --path=PATH        Output file or directory for the snapshot (default: $defaultPath).\n".
            "Examples:\n".
            "  php artisan draftsman:snapshot --exclude=config --exclude=about\n".
            "  php artisan draftsman:snapshot --exclude=config,about\n".
            "  php artisan draftsman:snapshot --path=storage/app/draftsman/snapshots\n".
            "  php artisan draftsman:snapshot --path=storage/app/draftsman/snapshots/custom_snapshot.json");
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

        // Persist snapshot as JSON (to --path or default config path)
        try {
            $savedPath = $this->saveSnapshot($this->snapshot, $this->option('path'));
            $this->info("Draftsman snapshot saved to: {$savedPath}");
        } catch (\Throwable $e) {
            $this->error('Failed to save Draftsman snapshot: '.$e->getMessage());
        }

        return self::SUCCESS;
    }

    /**
     * Normalize the --exclude option into a lowercase array of section names.
     * Supports multiple usages:
     *  - --exclude=config --exclude=about
     *  - --exclude=config,about
     */
    protected function normalizeExcludeOption(): array
    {
        $raw = $this->option('exclude');

        $items = [];

        if (is_array($raw)) {
            // Support: --exclude=a --exclude=b and also --exclude=a,b
            foreach ($raw as $entry) {
                if (! is_string($entry)) {
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
            $api = new ApiController;
            $models = $api->getModels();
            // Store under a dedicated key in the snapshot
            $this->snapshot['models'] = $models;
        } catch (\Throwable $e) {
            $this->snapshot['models_error'] = 'Error collecting model data: '.$e->getMessage();
        }
    }

    /**
     * Retrieves the Draftsman config file and adds it to the snapshot.
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
                $this->snapshot['config_error'] = 'Error reading Draftsman config: '.$e->getMessage();
            }
        } else {
            $this->snapshot['config_path'] = 'Draftsman config not found at '.$configFilePath;
        }
    }

    /**
     * Retrieves the composer.json file and adds it to the snapshot.
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
                    $this->snapshot['composer_error'] = 'Could not decode composer.json: '.json_last_error_msg();
                    $this->snapshot['composer_raw'] = $composerFileContents;
                }

            } catch (\Exception $e) {
                $this->snapshot['composer_error'] = 'Error reading composer.json: '.$e->getMessage();
            }
        } else {
            $this->snapshot['composer_path'] = 'composer.json not found at '.$composerFilePath;
        }
    }

    /**
     * Retrieves the output from 'php artisan about' as JSON and adds it to the snapshot.
     */
    protected function getAboutData(): void
    {
        Artisan::call('about', ['--json' => true]);
        $aboutJSON = Artisan::output();
        $this->snapshot['about'] = json_decode($aboutJSON, true);
    }

    /**
     * Saves the provided snapshot array as a JSON file.
     * If $outputPath is a directory, file will be named draftsman_snapshot_<YYYY-MM-DD_HH-mm-ss>.json in that directory.
     * If $outputPath is a file path ending with .json, it will be used directly.
     * If $outputPath is null, uses config('draftsman.snapshot_path') as directory.
     *
     * @return string Full path to the saved file
     */
    protected function saveSnapshot(array $snapshot, ?string $outputPath = null): string
    {
        // Determine base path: option or config default
        if ($outputPath === null || $outputPath === '') {
            $outputPath = (string) (config('draftsman.snapshot_path') ?? storage_path('app/draftsman/snapshots'));
        }

        $outputPath = rtrim($outputPath, "\\/");

        // If ends with .json, treat as file path; otherwise as directory
        $isFile = str_ends_with(strtolower($outputPath), '.json');

        if ($isFile) {
            $dir = dirname($outputPath);
            $targetPath = $outputPath;
        } else {
            $dir = $outputPath;
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $filename = "draftsman_snapshot_{$timestamp}.json";
            $targetPath = $dir.DIRECTORY_SEPARATOR.$filename;
        }

        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Fallback: encode errors are unlikely with arrays from our sources, but guard anyway
            throw new \RuntimeException('Unable to encode snapshot to JSON: '.json_last_error_msg());
        }

        File::put($targetPath, $json);

        return $targetPath;
    }
}
