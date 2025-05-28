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

    protected $relationsFromAttribute = [
        'BelongsTo' => 'getForeignKeyName',
        'BelongsToMany' => 'getParentKeyName',
        'HasMany' => 'getLocalKeyName',
        'HasManyThrough' => 'getLocalKeyName',
        'HasOne' => 'getLocalKeyName',
//        'HasOneOrMany' => 'getForeignKeyName',
//        'HasOneThrough' => 'getForeignKeyName',
//        'MorphMany' => 'getForeignKeyName',
//        'MorphOne' => 'getLocalKeyName',
//        'MorphOneOrMany' => 'getForeignKeyName',
//        'MorphPivot' => 'getForeignKeyName',
//        'MorphTo' => 'getForeignKeyName',
//        'MorphToMany' => 'getForeignKeyName',
//        'Pivot' => 'getForeignKeyName',
    ];

    protected $relationsToAttribute = [
        'BelongsTo' => 'getOwnerKeyName',
        'BelongsToMany' => 'getRelatedKeyName',
        'HasMany' => 'getForeignKeyName',
        'HasManyThrough' => 'getForeignKeyName',
        'HasOne' => 'getForeignKeyName',
//        'HasOneOrMany' => 'getForeignKeyName',
//        'HasOneThrough' => 'getForeignKeyName',
//        'MorphMany' => 'getForeignKeyName',
//        'MorphOne' => 'getForeignKeyName',
//        'MorphOneOrMany' => 'getForeignKeyName',
//        'MorphPivot' => 'getForeignKeyName',
//        'MorphTo' => 'getForeignKeyName',
//        'MorphToMany' => 'getForeignKeyName',
//        'Pivot' => 'getForeignKeyName',
    ];

    protected $relationsPivotsAttributes = [
        'BelongsToMany' => [
            'class' => 'getPivotClass',
            'from' => 'getForeignPivotKeyName',
            'to' => 'getRelatedPivotKeyName'
        ],
//        'MorphPivot' => 'getForeignKeyName',
//        'Pivot' => 'getForeignKeyName',
    ];

    protected $relationsThroughAttributes = [
        'HasManyThrough' => [
            // 'class' => 'getThroughParentClass', // throughParent is private
            'from' => 'getFirstKeyName',
            'to' => 'getSecondLocalKeyName'
        ],
//        'MorphPivot' => 'getForeignKeyName',
//        'Pivot' => 'getForeignKeyName',
    ];

    public function getPrivateProperty($object, $property) {
        // hack the private property
        $array = (array) $object;
        $propertyLength = strlen($property);
        foreach ($array as $key => $value) {
            $propertyNameParts = explode("\0", $key);
            $propertyName = end($propertyNameParts);
            if ($propertyName === $property) {
                return $value;
            }
        }
        return null;
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
                $function = $relation->name;
                $related = $relation->related;
                unset($relation->related);
                $rel = $mod->$function();
                $relation->key = $model . '.' . $function;
                $from_attribute = null;
                $to_attribute = null;
                $pivot_attributes = [];
                $through_attributes = [];
                if (array_key_exists($relation->type, $this->relationsFromAttribute)) {
                    $from_attribute = $this->relationsFromAttribute[$relation->type];
                    $from_attribute = $rel->$from_attribute();
                }
                if (array_key_exists($relation->type, $this->relationsToAttribute)) {
                    $to_attribute = $this->relationsToAttribute[$relation->type];
                    $to_attribute = $rel->$to_attribute();
                }
                if (array_key_exists($relation->type, $this->relationsPivotsAttributes)) {
                    $pivot_attributes = $this->relationsPivotsAttributes[$relation->type];
                    foreach ($pivot_attributes as $pivot_key => $pivot_attribute) {
                        $pivot_attributes[$pivot_key] = $rel->$pivot_attribute();
                    }
                    if ($pivot_attributes['class'] === Pivot::class) {
                        $pivot_attributes['class'].= '.'.$rel->getTable();
                    }
                }
                if (array_key_exists($relation->type, $this->relationsThroughAttributes)) {
                    $through_attributes = $this->relationsThroughAttributes[$relation->type];
                    foreach ($through_attributes as $through_key => $through_attribute) {
                        $through_attributes[$through_key] = $rel->$through_attribute();
                    }
                    $through_attributes['class'] = $this->getPrivatePropertyClass($rel, 'throughParent');
                    // seems to through an error if put BEFORE the foreach
                }
                $relation->from = $model;
                $relation->from_attribute = $from_attribute;
                $relation->to = $related;
                $relation->to_attribute = $to_attribute;
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
            }
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
