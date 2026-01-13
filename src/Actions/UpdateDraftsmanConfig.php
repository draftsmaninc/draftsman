<?php

namespace Draftsman\Draftsman\Actions;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class UpdateDraftsmanConfig
{
    /**
     * Handle updating the Draftsman config and, conditionally, the .env file.
     *
     * Rules:
     * - Write the 'presentation' section to storage/draftsman/config.php.
     * - If update_env (from merged config) is true, update DRAFTSMAN_* keys in .env.
     * - If add_env is true, also append new DRAFTSMAN_* keys to .env.
     * - Always run config:clear after writing config and any env updates.
     *
     * @param  array  $payload  JSON body decoded to array
     * @return array{message:string, config:array, env_updated:bool}
     */
    public function handle(array $payload): array
    {
        $storagePath = storage_path('draftsman/config.php');

        // Extract the presentation section
        $presentation = Arr::get($payload, 'presentation', []);

        // Get models_path for class detection
        $modelsPath = Arr::get($payload, 'package.models_path', config('draftsman.package.models_path', app_path('Models')));

        // Write the presentation section to storage/draftsman/config.php as a PHP array
        $this->writeStorageConfig($storagePath, $presentation, $modelsPath);

        $didWriteEnv = false;
        $updateEnvFlag = (bool) Arr::get($payload, 'package.update_env', Arr::get($payload, 'update_env', false));
        $addEnvFlag = (bool) Arr::get($payload, 'package.add_env', Arr::get($payload, 'add_env', false));

        if ($updateEnvFlag === true) {
            $envUpdates = $this->buildEnvMapFromConfig($payload);
            if (count($envUpdates)) {
                $this->updateEnvFile($envUpdates, $addEnvFlag);
                $didWriteEnv = true;
            }
        }

        // Always clear config when writing
        Artisan::call('config:clear');

        return [
            'message' => 'Draftsman configuration updated successfully.',
            'config' => $payload,
            'env_updated' => $didWriteEnv,
        ];
    }

    private function writeStorageConfig(string $path, array $presentation, string $modelsPath): void
    {
        File::ensureDirectoryExists(dirname($path));

        $data = [
            'presentation' => $presentation,
        ];

        $contents = "<?php\n\nreturn " . $this->exportArray($data, 0, $modelsPath) . ";\n";

        File::put($path, $contents);
    }

    private function exportArray(array $array, int $level = 0, ?string $modelsPath = null): string
    {
        $indent = str_repeat('    ', $level);
        $nextIndent = str_repeat('    ', $level + 1);
        $output = "[\n";

        foreach ($array as $key => $value) {
            $output .= $nextIndent;
            if (is_string($key)) {
                if ($this->isModelClass($key, $modelsPath)) {
                    $output .= $key . '::class';
                } else {
                    $output .= "'" . $key . "'";
                }
                $output .= ' => ';
            }

            if (is_array($value)) {
                $output .= $this->exportArray($value, $level + 1, $modelsPath);
            } elseif (is_string($value)) {
                $output .= "'" . addslashes($value) . "'";
            } elseif (is_bool($value)) {
                $output .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $output .= 'null';
            } else {
                $output .= $value;
            }

            $output .= ",\n";
        }

        $output .= $indent . "]";

        return $output;
    }

    private function isModelClass(string $key, ?string $modelsPath = null): bool
    {
        if (! $modelsPath) {
            $modelsPath = config('draftsman.package.models_path', app_path('Models'));
        }

        $appPath = app_path();
        $normalizedModelsPath = str_replace('\\', '/', $modelsPath);
        $normalizedAppPath = str_replace('\\', '/', $appPath);

        if (str_starts_with(strtolower($normalizedModelsPath), strtolower($normalizedAppPath))) {
            $relative = ltrim(substr($normalizedModelsPath, strlen($normalizedAppPath)), '/');
            $parts = explode('/', $relative);
            $namespace = 'App';
            foreach ($parts as $part) {
                if ($part) {
                    $namespace .= '\\' . ucfirst($part);
                }
            }

            if (str_starts_with(strtolower($key), strtolower($namespace) . '\\')) {
                return true;
            }
        }

        // Fallback or additional check: if it looks like a class and starts with App\Models
        if (str_starts_with($key, 'App\\Models\\')) {
            return true;
        }

        return false;
    }

    private function buildEnvMapFromConfig(array $config): array
    {
        $map = [];

        $addSection = function (string $section, array $values) use (&$map) {
            $iterator = function ($arr, $prefix = '') use (&$map, $section, &$iterator) {
                foreach ($arr as $k => $v) {
                    $keyPart = strtoupper(is_int($k) ? (string) $k : str_replace(['-', '.'], '_', $k));
                    $currentPrefix = $prefix === '' ? $keyPart : $prefix.'_'.$keyPart;
                    if (is_array($v)) {
                        $iterator($v, $currentPrefix);
                    } else {
                        if ($section === 'package') {
                            $envKey = 'DRAFTSMAN_'.$currentPrefix;
                        } else {
                            $envKey = 'DRAFTSMAN_'.strtoupper($section).'_'.$currentPrefix;
                        }
                        $map[$envKey] = $this->scalarToEnv($v);
                    }
                }
            };
            $iterator($values);
        };

        foreach ($config as $section => $values) {
            if ($section === 'presentation') {
                continue;
            }
            if (! is_array($values)) {
                $map['DRAFTSMAN_'.strtoupper($section)] = $this->scalarToEnv($values);

                continue;
            }
            $addSection($section, $values);
        }

        return $map;
    }

    private function scalarToEnv($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    private function updateEnvFile(array $updates, bool $addNew = false): void
    {
        $envPath = base_path('.env');
        $lines = File::exists($envPath) ? preg_split("/\r?\n/", File::get($envPath)) : [];
        $existingKeys = [];
        foreach ($lines as $idx => $line) {
            if (preg_match('/^([A-Z0-9_]+)\s*=.*/', $line, $m)) {
                $existingKeys[$m[1]] = $idx;
            }
        }

        foreach ($updates as $k => $v) {
            // Only update keys that start with DRAFTSMAN_
            if (strpos($k, 'DRAFTSMAN_') !== 0) {
                continue;
            }
            // Update only if key already exists; do not append missing keys
            if (array_key_exists($k, $existingKeys)) {
                $entry = $k.'='.$this->quoteEnvValue($v);
                $lines[$existingKeys[$k]] = $entry;
            } elseif ($addNew) {
                $lines[] = $k.'='.$this->quoteEnvValue($v);
            }
        }

        File::put($envPath, implode(PHP_EOL, $lines).PHP_EOL);
    }

    private function quoteEnvValue(string $value): string
    {
        if (preg_match('/\s|#|\\"|\\'."'".'/u', $value)) {
            $escaped = str_replace('"', '\\"', $value);

            return '"'.$escaped.'"';
        }

        return $value;
    }
}
