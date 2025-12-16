<?php

namespace Draftsman\Draftsman\Http\Controllers\ApiV1;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ApiController extends BaseController
{
    /*
    * Eloquent models:
    * BelongsTo
    * BelongsToMany
    * HasMany
    * HasManyThrough
    * HasOne
    * HasOneOrMany * model that can be used in place where both are needed
    * HasOneThrough
    * MorphMany
    * MorphOne
    * MorphOneOrMany * model that can be used in place where both are needed
    * MorphTo
    * MorphToMany
    * MorphPivot * custom many-to-many pivot models should extend
    * Pivot * custom polymorphic many-to-many pivot models should extend
    * https://laravel.com/docs/10.x/eloquent-relationships#defining-custom-intermediate-table-models
    */

    protected $relationsOmitList = [];

    protected $relationsRestrictToList = [];

    protected $relationsConnectionMap = [
        'BelongsTo' => 'direct',
        'BelongsToMany' => 'direct',
        'HasMany' => 'direct',
        'HasManyThrough' => 'through',
        'HasOne' => 'direct',
        'HasOneThrough' => 'through',
        'MorphMany' => 'direct',
        'MorphOne' => 'direct',
        'MorphTo' => 'direct',
        'MorphToMany' => 'direct',
    ];

    protected $relationsTypeMap = [
        'BelongsTo' => 'one',
        'BelongsToMany' => 'one',
        'HasMany' => 'many',
        'HasManyThrough' => 'many',
        'HasOne' => 'one',
        'HasOneThrough' => 'one',
        'MorphMany' => 'many',
        'MorphOne' => 'one',
        'MorphTo' => 'one',
        'MorphToMany' => 'many',
    ];

    protected $relationsFromAttribute = [
        'BelongsTo' => 'getForeignKeyName',
        'BelongsToMany' => 'getParentKeyName',
        'HasMany' => 'getLocalKeyName',
        'HasManyThrough' => 'getLocalKeyName',
        'HasOne' => 'getLocalKeyName',
        'HasOneThrough' => 'getLocalKeyName',
        'MorphMany' => 'getLocalKeyName',
        'MorphOne' => 'getLocalKeyName',
        'MorphTo' => 'getForeignKeyName',
        'MorphToMany' => 'getParentKeyName',
    ];

    protected $relationsToAttribute = [
        'BelongsTo' => 'getOwnerKeyName',
        'BelongsToMany' => 'getRelatedKeyName',
        'HasMany' => 'getForeignKeyName',
        'HasManyThrough' => 'getForeignKeyName',
        'HasOne' => 'getForeignKeyName',
        'HasOneThrough' => 'getForeignKeyName',
        'MorphMany' => 'getForeignKeyName',
        'MorphOne' => 'getForeignKeyName',
        'MorphTo' => 'getForeignKeyName',
        'MorphToMany' => 'getRelatedPivotKeyName',
    ];

    protected $relationsPivotsAttributes = [
        'BelongsToMany' => [
            'class' => 'getPivotClass',
            'from' => 'getForeignPivotKeyName',
            'to' => 'getRelatedPivotKeyName',
        ],
        'MorphToMany' => [
            'class' => 'getPivotClass',
            'from' => 'getForeignPivotKeyName',
            'to' => 'getRelatedPivotKeyName',
        ],
    ];

    protected $relationsThroughAttributes = [
        'HasManyThrough' => [
            // 'class' => 'getThroughParentClass',
            // throughParent is a private attribute
            'from' => 'getFirstKeyName',
            'to' => 'getSecondLocalKeyName',
        ],
        'HasOneThrough' => [
            // 'class' => 'getThroughParentClass',
            // throughParent is a private attribute
            'from' => 'getFirstKeyName',
            'to' => 'getSecondLocalKeyName',
        ],
    ];

    protected $relationsMorphAttributes = [
        'MorphMany' => [
            'attribute' => 'getMorphType',
        ],
        'MorphOne' => [
            'attribute' => 'getMorphType',
        ],
        'MorphTo' => [
            'attribute' => 'getMorphType',
        ],
        'MorphToMany' => [
            'attribute' => 'getMorphType',
        ],
    ];

    protected $relationsMorphSkipDefintions = [
        'MorphTo',
        'MorphToMany',
    ];

    public function getPrivateProperty($object, $property)
    {
        // hack the private property
        return (fn () => $this->{$property})->call($object);
    }

    /**
     * Return the current Draftsman config (config/draftsman.php) as JSON.
     * If the published config file does not exist, attempt to publish it,
     * then fall back to the vendor default if still unavailable.
     */
    public function getConfig()
    {
        try {
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

            return response()->json([
                'config' => $config,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load Draftsman configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPrivatePropertyClass($object, $property)
    {
        $value = $this->getPrivateProperty($object, $property);
        if (! $value) {
            return null;
        }

        return get_class($value);
    }

    public function getModelShow($model): ?\stdClass
    {
        if (Artisan::call('model:show', ['model' => $model, '--json' => true]) === 0) {
            $data = json_decode(Artisan::output());
            $mod = new $model;
            $ref = new \ReflectionClass($model);
            $data->namespace = substr($data->class, 0, strrpos($data->class, '\\'));
            $data->file = $ref?->getFileName() ?? null;
            $data->attributes_count = count($data->attributes) ?? 0;
            $data->relations_count = count($data->relations) ?? 0;

            foreach ($data->relations as &$relation) {
                if (in_array($relation->type, $this->relationsOmitList)) {
                    $relation = null;

                    continue;
                }
                if (isset($this->relationsRestrictToList) && count($this->relationsRestrictToList) && ! in_array($relation->type, $this->relationsRestrictToList)) {
                    $relation = null;

                    continue;
                }
                $function = $relation->name;
                $related = $relation->related;
                $framework_type = $relation->type;
                unset($relation->related);
                $relation->type = (array_key_exists($framework_type, $this->relationsTypeMap)) ? $this->relationsTypeMap[$framework_type] : null;
                $relation->framework_type = $framework_type;
                $rel = $mod->$function();
                $relref = $ref->getMethod($function);
                $relation->file = $relref?->getFileName() ?? null;
                $relation->line = $relref?->getStartLine() ?? null;
                $relation->key = $model.'.'.$function;
                $connection = null;
                $from_attribute = null;
                $to_attribute = null;
                $pivot_attributes = [];
                $through_attributes = [];
                $morph_attributes = [];
                if (array_key_exists($framework_type, $this->relationsConnectionMap)) {
                    $connection = $this->relationsConnectionMap[$framework_type];
                }
                if (array_key_exists($framework_type, $this->relationsFromAttribute)) {
                    $from_attribute = $this->relationsFromAttribute[$framework_type];
                    $from_attribute = $rel->$from_attribute();
                }
                if (array_key_exists($framework_type, $this->relationsToAttribute)) {
                    $to_attribute = $this->relationsToAttribute[$framework_type];
                    $to_attribute = $rel->$to_attribute();
                }
                if (array_key_exists($framework_type, $this->relationsPivotsAttributes)) {
                    $pivot_attributes = $this->relationsPivotsAttributes[$framework_type];
                    foreach ($pivot_attributes as $pivot_key => $pivot_attribute) {
                        $pivot_attributes[$pivot_key] = $rel->$pivot_attribute();
                    }
                    if ($pivot_attributes['class'] === Pivot::class) {
                        $pivot_attributes['class'] .= '.'.$rel->getTable();
                    }
                }
                if (array_key_exists($framework_type, $this->relationsThroughAttributes)) {
                    $through_attributes = $this->relationsThroughAttributes[$framework_type];
                    foreach ($through_attributes as $through_key => $through_attribute) {
                        $through_attributes[$through_key] = $rel->$through_attribute();
                    }
                    $through_attributes['class'] = $this->getPrivatePropertyClass($rel, 'throughParent');
                    // seems to throw an error if put BEFORE the foreach
                }
                if (array_key_exists($framework_type, $this->relationsMorphAttributes)) {
                    $morph_attributes = $this->relationsMorphAttributes[$framework_type];
                    foreach ($morph_attributes as $morph_key => $morph_attribute) {
                        $morph_attributes[$morph_key] = $rel->$morph_attribute();
                    }
                }
                $relation->connection = $connection;
                $relation->from = $model;
                $relation->from_attribute = $from_attribute;
                $relation->to = $related;
                $relation->to_attribute = $to_attribute;
                if (in_array($framework_type, $this->relationsMorphSkipDefintions)) {
                    if (($relation->from === $relation->to) && ($relation->from_attribute === $relation->to_attribute)) {
                        $relation = null;

                        continue;
                    }
                }
                if ($pivot_attributes) {
                    foreach ($pivot_attributes as $pivot_key => $pivot_attribute) {
                        $relation->{'pivot_'.$pivot_key} = $pivot_attribute;
                    }
                }
                if ($through_attributes) {
                    foreach ($through_attributes as $through_key => $through_attribute) {
                        $relation->{'through_'.$through_key} = $through_attribute;
                    }
                }
                if ($morph_attributes) {
                    foreach ($morph_attributes as $morph_key => $morph_attribute) {
                        $relation->{'morph_'.$morph_key} = $morph_attribute;
                    }
                    $relation->{'morph_key'} = $related.'.'.$to_attribute.'.'.$relation->{'morph_attribute'};
                }
            }
            $data->relations = array_values(array_filter($data->relations)) ?? [];
            $data->relations_count = count($data->relations) ?? 0;

            return $data;
        }

        return null;
    }

    public function getModel($model)
    {
        return $this->getModelShow($model);
    }

    public function getModels(): array
    {
        $data = [];
        foreach ($this->getModelsList() as $model) {
            $show = $this->getModelShow($model);
            if (! $show) {
                continue;
            }
            $data[] = $show;
        }

        // rev sort attributes_count
        /*
        usort($data, function($a, $b) {
            return $b->attributes_count - $a->attributes_count;
        });
        */
        return $data;
    }

    public function getModelsList(): array
    {
        $models = collect(File::allFiles(app_path()))
            ->map(function ($item) {
                $path = $item->getRelativePathName();
                $class = sprintf('%s%s',
                    Container::getInstance()->getNamespace(),
                    strtr(substr($path, 0, strrpos($path, '.')), '/', '\\'));

                return $class;
            })
            ->filter(function ($class) {
                $valid = false;
                if (class_exists($class)) {
                    $reflection = new \ReflectionClass($class);
                    $valid = $reflection->isSubclassOf(EloquentModel::class) &&
                        ! $reflection->isAbstract();
                }

                return $valid;
            });

        return $models->values()->sort()->toArray();
    }

    /**
     * Update Draftsman configuration and optionally ENV values.
     */
    public function updateConfig(Request $request)
    {
        try {
            $payload = $request->json()->all();
            if (! is_array($payload)) {
                return response()->json([
                    'message' => 'Invalid JSON body. Expecting an object matching draftsman.php structure.',
                ], 422);
            }

            $publishedConfigPath = base_path('config/draftsman.php');
            $vendorConfigPath = base_path('vendor/draftsmaninc/draftsman/config/draftsman.php');

            // Ensure published config exists
            if (! File::exists($publishedConfigPath)) {
                Artisan::call('vendor:publish', ['--tag' => 'draftsman-config']);
            }

            // Load defaults from vendor (source of truth for structure)
            $defaults = [];
            if (File::exists($vendorConfigPath)) {
                $defaults = include $vendorConfigPath;
                if (! is_array($defaults)) {
                    $defaults = [];
                }
            }

            // Merge: defaults overwritten by incoming payload
            $merged = $this->arrayMergeRecursiveDistinct($defaults, $payload);

            // Write merged config to app config path
            $this->writePhpConfig($publishedConfigPath, $merged);

            $didWriteEnv = false;
            // Honor the update_env setting from the merged config (defaults to true in config file).
            // New semantics per latest requirement: when update_env is TRUE -> update existing DRAFTSMAN_ keys in .env.
            // When FALSE -> do NOT update .env (config-only mode).
            $updateEnvFlag = (bool) Arr::get($merged, 'config.update_env', Arr::get($merged, 'update_env', false));

            if ($updateEnvFlag === true) {
                $envUpdates = $this->buildEnvMapFromConfig($merged);
                if (count($envUpdates)) {
                    $this->updateEnvFile($envUpdates);
                    $didWriteEnv = true;
                }
            }

            // Clear config cache if we wrote config or env
            if ($didWriteEnv || true) {
                // Always clear when writing config
                Artisan::call('config:clear');
            }

            return response()->json([
                'message' => 'Draftsman configuration updated successfully.',
                'config' => $merged,
                'env_updated' => $didWriteEnv,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update Draftsman configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function arrayMergeRecursiveDistinct(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->arrayMergeRecursiveDistinct($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function writePhpConfig(string $path, array $config): void
    {
        // Convert array to PHP file contents
        $export = var_export($config, true);
        $contents = <<<PHP
<?php

// This file is auto-generated by Draftsman API. Do not edit manually.

return {$export};

PHP;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    private function buildEnvMapFromConfig(array $config): array
    {
        $map = [];

        // Helper to flatten paths
        $addSection = function (string $section, array $values) use (&$map) {
            $iterator = function ($arr, $prefix = '') use (&$map, $section, & $iterator) {
                foreach ($arr as $k => $v) {
                    $keyPart = strtoupper(is_int($k) ? (string) $k : str_replace(['-', '.'], '_', $k));
                    $currentPrefix = $prefix === '' ? $keyPart : $prefix . '_' . $keyPart;
                    if (is_array($v)) {
                        $iterator($v, $currentPrefix);
                    } else {
                        if ($section === 'config') {
                            $envKey = 'DRAFTSMAN_' . $currentPrefix;
                        } else {
                            $envKey = 'DRAFTSMAN_' . strtoupper($section) . '_' . $currentPrefix;
                        }
                        $map[$envKey] = $this->scalarToEnv($v);
                    }
                }
            };
            $iterator($values);
        };

        foreach ($config as $section => $values) {
            if (! is_array($values)) {
                // top-level scalar
                $map['DRAFTSMAN_' . strtoupper($section)] = $this->scalarToEnv($values);
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

    private function updateEnvFile(array $updates): void
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
                $entry = $k . '=' . $this->quoteEnvValue($v);
                $lines[$existingKeys[$k]] = $entry;
            }
        }

        File::put($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    private function quoteEnvValue(string $value): string
    {
        // Quote only when needed
        if (preg_match('/\s|#|\\"|\\' . "'" . '/u', $value)) {
            // wrap in double quotes and escape existing ones
            $escaped = str_replace('"', '\\"', $value);
            return '"' . $escaped . '"';
        }
        return $value;
    }
}
