<?php

namespace Draftsman\Draftsman\Http\Controllers\ApiV1;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
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
            'to' => 'getRelatedPivotKeyName'
        ],
        'MorphToMany' => [
            'class' => 'getPivotClass',
            'from' => 'getForeignPivotKeyName',
            'to' => 'getRelatedPivotKeyName'
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

    public function getPrivateProperty($object, $property) {
        // hack the private property
        return (fn () => $this->{$property})->call($object);
    }

    public function getPrivatePropertyClass($object, $property) {
        $value = $this->getPrivateProperty($object, $property);
        if (!$value) {
            return null;
        }
        return get_class($value);
    }

    public function getModelShow($model): \stdClass | null
    {
        if (Artisan::call('model:show', ['model' => $model, '--json' => true]) === 0) {
            $data = json_decode(Artisan::output());
            $data->attributes_count = count($data->attributes) ?? 0;
            $data->relations_count = count($data->relations) ?? 0;
            $mod = new $model;

            foreach ($data->relations as &$relation) {
                if (in_array($relation->type, $this->relationsOmitList)) {
                    $relation = null;
                    continue;
                }
                if (isset($this->relationsRestrictToList) && count($this->relationsRestrictToList) && !in_array($relation->type, $this->relationsRestrictToList)) {
                    $relation = null;
                    continue;
                }
                $function = $relation->name;
                $related = $relation->related;
                $framework_type = $relation->type;
                unset($relation->related);
                $relation->type = (array_key_exists($framework_type, $this->relationsTypeMap))? $this->relationsTypeMap[$framework_type] : null;
                $relation->framework_type = $framework_type;
                $rel = $mod->$function();
                $relation->key = $model . '.' . $function;
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
                        $pivot_attributes['class'].= '.'.$rel->getTable();
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
            if (!$show) {
                continue;
            }
            $data[] = $show;
        }
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
                        !$reflection->isAbstract();
                }
                return $valid;
            });

        return $models->values()->sort()->toArray();
    }
}
