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
        'package' => 'getPackageJsonData',
        'about' => 'getAboutData',
    ];

    public $snapshot = [];

    protected function configure(): void
    {
        parent::configure();
        $available = implode(', ', array_keys(self::SECTIONS));

        $defaultPath = (string) (config('draftsman.snapshot_path') ?? 'storage/draftsman/snapshots');
        $this->setHelp("Exclude one or more sections. Available: $available\n".
            "Options:\n".
            "  --exclude=SECTION   Repeatable or comma-separated list to exclude sections.\n".
            "  --path=PATH        Output file or directory for the snapshot (default: $defaultPath).\n".
            "Examples:\n".
            "  php artisan draftsman:snapshot --exclude=config --exclude=about\n".
            "  php artisan draftsman:snapshot --exclude=config,about\n".
            "  php artisan draftsman:snapshot --path=storage/draftsman/snapshots\n".
            '  php artisan draftsman:snapshot --path=storage/draftsman/snapshots/custom_snapshot.json');
    }

    public function handle(): int
    {
        // Map snapshot sections to their loader methods
        $sections = [
            'config' => 'getConfigData',
            'composer' => 'getComposerData',
            'package' => 'getPackageJsonData',
            'about' => 'getAboutData',
        ];

        $exclude = $this->normalizeExcludeOption();

        // Always include model list, model data, env, and ui config; these sections cannot be excluded
        $this->getModelList();
        $this->getModelData();
        $this->getEnvData();

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

    protected function getModelList(): void
    {
        try {
            $api = new ApiController;
            $modelsList = $api->getModelsList();
            $this->snapshot['models_list'] = $modelsList;
        } catch (\Throwable $e) {
            $this->snapshot['models_list_error'] = 'Error collecting models list: '.$e->getMessage();
        }
    }

    protected function getModelData(): void
    {
        try {
            $api = new ApiController;
            $modelsData = $api->getModels();
            $this->snapshot['models_data'] = $modelsData;
        } catch (\Throwable $e) {
            $this->snapshot['models_error'] = 'Error collecting models data: '.$e->getMessage();
        }
    }

    /**
     * Retrieves the app's Draftsman config file and adds it to the snapshot.
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
            // When the file is missing, surface this as an error to align with schema expectations
            $this->snapshot['composer_error'] = 'composer.json not found at '.$composerFilePath;
        }
    }

    /**
     * Retrieves the package.json file and adds it to the snapshot.
     */
    protected function getPackageJsonData(): void
    {
        $packageFilePath = base_path('package.json');

        if (File::exists($packageFilePath)) {
            try {
                // Read the file content
                $packageFileContents = File::get($packageFilePath);

                // Decode the JSON into a PHP array
                $packageData = json_decode($packageFileContents, true);

                // Check for decoding errors
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Include the entire structured data
                    $this->snapshot['package'] = $packageData;
                } else {
                    // Handle JSON decoding failure
                    $this->snapshot['package_error'] = 'Could not decode package.json: '.json_last_error_msg();
                    $this->snapshot['package_raw'] = $packageFileContents;
                }

            } catch (\Exception $e) {
                $this->snapshot['package_error'] = 'Error reading package.json: '.$e->getMessage();
            }
        } else {
            // When the file is missing, surface this as an error to align with schema expectations
            $this->snapshot['package_error'] = 'package.json not found at '.$packageFilePath;
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
     * Reads the .env file and collects variables beginning with DRAFTSMAN_,
     * excluding any keys that contain _KEY, _PASS, or _PASSWORD (case-insensitive).
     */
    protected function getEnvData(): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->snapshot['env_path'] = '.env file not found at '.$envPath;

            return;
        }

        try {
            $contents = File::get($envPath);
            $lines = preg_split("/\r\n|\r|\n/", (string) $contents) ?: [];

            $result = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                // Support optional leading 'export '
                if (stripos($line, 'export ') === 0) {
                    $line = trim(substr($line, 7));
                }

                // Split on the first '=' only
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                [$key, $value] = $parts;
                $key = trim($key);
                $value = trim($value);

                // Remove surrounding quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                // Only consider keys beginning with DRAFTSMAN_
                if (stripos($key, 'DRAFTSMAN_') !== 0) {
                    continue;
                }

                // Mask sensitive keys that end with _KEY, _PASS, or _PASSWORD
                $upperKey = strtoupper($key);
                $isSensitive = str_ends_with($upperKey, '_KEY')
                    || str_ends_with($upperKey, '_SECRET')
                    || str_ends_with($upperKey, '_PASS')
                    || str_ends_with($upperKey, '_PASSWORD');

                $result[$key] = $isSensitive ? '...' : $value;
            }

            $this->snapshot['env'] = $result;
        } catch (\Throwable $e) {
            $this->snapshot['env_error'] = 'Error reading .env: '.$e->getMessage();
        }
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
            $outputPath = (string) (config('draftsman.snapshot_path') ?? storage_path('draftsman/snapshots'));
        }

        $outputPath = rtrim($outputPath, '\\/');

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

        // Ensure a .gitignore exists in the draftsman directory to ignore everything inside it
        // We only create the file if it doesn't already exist.
        try {
            $draftsmanDir = storage_path('draftsman');
            if (! File::exists($draftsmanDir)) {
                File::makeDirectory($draftsmanDir, 0755, true);
            }

            $gitignorePath = $draftsmanDir.DIRECTORY_SEPARATOR.'.gitignore';
            if (! File::exists($gitignorePath)) {
                // Ignore everything in draftsman, but keep this .gitignore tracked
                File::put($gitignorePath, "*\n!.gitignore\n");
            }
        } catch (\Throwable $e) {
            // Non-fatal: failure to create .gitignore shouldn't block snapshot saving
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
