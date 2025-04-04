<?php

namespace DraftsmanInc\Draftsman\Http\Controllers;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class Controller extends BaseController
{
    protected $all_models = [];

    protected $models = [];

    protected $relations = [];

    protected $shows = [];

    protected $relationsTypeMap = [
        'BelongsTo' => 'one_to_many',
        'BelongsToMany' => 'many_to_many',
        'HasMany' => 'one_to_many',
        'HasManyThrough' => 'one_to_many_through',
        'HasOne' => 'one_to_many',
        'HasOneOrMany' => 'one_to_many_through',
        'HasOneThrough' => 'one_to_many_through',
        'MorphMany' => 'morph_many_to_many',
        'MorphOne' => 'morph_one_to_many',
        'MorphOneOrMany' => 'morph_many_to_many',
        'MorphPivot' => 'morph_many_to_many',
        'MorphTo' => 'morph_one_to_many',
        'MorphToMany' => 'morph_many_to_many',
        'Pivot' => 'pivot',
    ];

    // https://laravel.com/docs/11.x/eloquent-relationships#has-one-of-many
    // HAS ONE OR MANY ??
    protected $relationsInverseMap = [
        'BelongsTo' => ['HasOne', 'HasMany'],
        'BelongsToMany' => '',
        'HasMany' => 'BelongsTo',
        'HasManyThrough' => '',
        'HasOne' => 'BelongsTo',
        'HasOneOrMany' => '',
        'HasOneThrough' => '',
        'MorphMany' => '',
        'MorphOne' => '',
        'MorphOneOrMany' => '',
        'MorphPivot' => '',
        'MorphTo' => '',
        'MorphToMany' => '',
        'Pivot' => 'pivot',
    ];

    public function __construct()
    {
        $this->all_models = $this->getAllModels();
    }

    protected function getModelShow($model): ?\stdClass
    {
        if (! in_array($model, $this->all_models)) {
            return null;
        }
        if (array_key_exists($model, $this->shows)) {
            return $this->shows[$model];
        }
        if (Artisan::call('model:show', ['model' => $model, '--json' => true]) === 0) {
            $this->shows[$model] = json_decode(Artisan::output());

            return $this->shows[$model];
        }

        return null;
    }

    protected function prepareModel($model, $follow = true): null
    {
        $info = $this->getModelShow($model);
        if (! $info) {
            return null;
        }
        $this->models[$model] = $info;
        $more_models = [];

        foreach ($info->relations as $relation) {
            $type = $this->relationsTypeMap[$relation->type];
            $related = $relation->related;
            if (in_array($related, $this->all_models)) {
                $more_models[] = $related;
            }
            $function = $relation->name;
            $key = $type.'.'.$model.'.'.$function.'.'.$related;
            $connection = [
                'column' => 'NOT IMPLEMENTED',
                'framework_type' => $relation->type,
                'function' => $function,
                'model' => $model,
            ];
            $this->relations[$key] = [
                'type' => $type,
                'connects' => [
                    $model => $connection,
                ],
            ];
        }

        return null;
    }

    protected function prepareModels(): null
    {
        foreach ($this->all_models as $model) {
            $this->prepareModel($model);
        }

        return null;
    }

    protected function getModel($model)
    {
        $this->prepareModel($model);
        if (! in_array($model, $this->all_models)) {
            return null;
        }
        if (! array_key_exists($model, $this->models)) {
            return null;
        }

        return $this->models[$model];
    }

    protected function getModels(): array
    {
        $this->prepareModels();

        return $this->models;
    }

    protected function getAllModels(): array
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
}
