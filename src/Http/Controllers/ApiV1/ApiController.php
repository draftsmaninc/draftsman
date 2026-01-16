<?php

namespace Draftsman\Draftsman\Http\Controllers\ApiV1;

use Draftsman\Draftsman\Actions\GetDraftsmanConfig;
use Draftsman\Draftsman\Actions\UpdateDraftsmanConfig;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

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
        'BelongsToMany' => 'many',
        'HasMany' => 'many',
        'HasManyThrough' => 'many',
        'HasOne' => 'one',
        'HasOneThrough' => 'one',
        'MorphMany' => 'many',
        'MorphOne' => 'one',
        'MorphTo' => 'one',
        'MorphToMany' => 'many',
    ];

    protected $relationshipKeyPieces = [
        'BelongsTo' => ['to', 'to_attribute', 'from', 'from_attribute'],
        'BelongsToMany' => ['to', 'to_attribute', 'from', 'from_attribute'], // todo
        'HasMany' => ['from', 'from_attribute', 'to', 'to_attribute'],
        'HasManyThrough' => ['from', 'from_attribute', 'to', 'to_attribute'],  // todo
        'HasOne' => ['from', 'from_attribute', 'to', 'to_attribute'],
        'HasOneThrough' => ['from', 'from_attribute', 'to', 'to_attribute'],  // todo
        'MorphMany' => ['from', 'from_attribute', 'to', 'to_attribute'],  // todo
        'MorphOne' => ['from', 'from_attribute', 'to', 'to_attribute'],  // todo
        'MorphTo' => ['to', 'to_attribute', 'from', 'from_attribute'],  // todo
        'MorphToMany' => ['to', 'to_attribute', 'from', 'from_attribute'],  // todo
    ];

    // multiplicity details https://www.red-gate.com/blog/crow-s-foot-notation
    protected $relationsMultiplicityMap = [
        'BelongsTo' => 'many',
        'BelongsToMany' => 'many',
        'HasMany' => 'one',
        'HasManyThrough' => 'one',
        'HasOne' => 'many',
        'HasOneThrough' => 'many',
        'MorphMany' => 'one',
        'MorphOne' => 'many',
        'MorphTo' => 'many',
        'MorphToMany' => 'one',
    ];

    // mandatory details https://www.red-gate.com/blog/crow-s-foot-notation
    protected $relationsMandatoryMap = [
        'BelongsTo' => 'from_attribute',
        'BelongsToMany' => false,
        'HasMany' => true,
        'HasManyThrough' => true,
        'HasOne' => false,
        'HasOneThrough' => false,
        'MorphMany' => true,
        'MorphOne' => false,
        'MorphTo' => false,
        'MorphToMany' => true,
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
    public function getConfig(GetDraftsmanConfig $action)
    {
        try {
            $data = $action->handle();

            return response()->json($data);
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
            $keyed_attributes = collect($data->attributes)->keyBy('name');

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
                $relation->framework_type = $framework_type;
                $relation->type = (array_key_exists($framework_type, $this->relationsTypeMap)) ? $this->relationsTypeMap[$framework_type] : null;
                $relation->connection = (array_key_exists($framework_type, $this->relationsConnectionMap)) ? $this->relationsConnectionMap[$framework_type] : null;
                $relation->multiplicity = (array_key_exists($framework_type, $this->relationsMultiplicityMap)) ? $this->relationsMultiplicityMap[$framework_type] : null;
                $relation->mandatory = (array_key_exists($framework_type, $this->relationsMandatoryMap)) ? $this->relationsMandatoryMap[$framework_type] : false;
                $relation->key = $model.'.'.$function;
                $rel = $mod->$function();
                $relref = $ref->getMethod($function);
                $relation->file = $relref?->getFileName() ?? null;
                $relation->line = $relref?->getStartLine() ?? null;
                $from_attribute = null;
                $to_attribute = null;
                $pivot_attributes = [];
                $through_attributes = [];
                $morph_attributes = [];
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
                if (is_string($relation->mandatory)) {
                    $nullable_col = 'nullable';
                    $check_attr = $relation->{$relation->mandatory} ?? null;
                    $keyed_attr = $keyed_attributes[$check_attr] ?? null;
                    if ($check_attr && $keyed_attr) {
                        $mandatory_attr = collect($keyed_attributes[$check_attr])->toArray();
                        $relation->mandatory = (array_key_exists($nullable_col, $mandatory_attr)) ? $mandatory_attr[$nullable_col] : false;
                    } else {
                        $relation->mandatory = false;
                    }
                }
                $key_parts = [];
                if (array_key_exists($framework_type, $this->relationshipKeyPieces)) {
                    $key_parts = array_merge($key_parts, $this->relationshipKeyPieces[$framework_type]);
                }
                foreach ($key_parts as &$part) {
                    $part = $relation->{$part};
                }
                $relation->relationship_key = implode('.', $key_parts);
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
    public function updateConfig(Request $request, UpdateDraftsmanConfig $action)
    {
        try {
            $payload = $request->json()->all()['config'];
            if (! is_array($payload)) {
                return response()->json([
                    'message' => 'Invalid JSON body. Expecting an object matching draftsman.php structure.',
                ], 422);
            }

            $result = $action->handle($payload);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update Draftsman configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
