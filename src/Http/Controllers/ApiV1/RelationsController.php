<?php

namespace Draftsman\Draftsman\Http\Controllers\ApiV1;

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class RelationsController extends ApiController
{


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $list = $this->getModels()->toArray();
        $relations = [];
        $models= [];
        foreach ($list as $name) {
            $model = $this->getModelShow($name);
            if ($model) {
                $models[$name] = $model;
            }
        }
        foreach (array_keys($models) as $name) {
            $model = $models[$name];
            if (($model) && (isset($model->relations))) {
                foreach ($model->relations as $relation) {
                    $type = $this->relationsTypeMap[$relation->type];
                    $related = $relation->related;
                    $function = $relation->name;
                    $key = $type . '.' . $name . '.' . $function. '.' . $related . '.NULL';
                    $connection = [
                        'column' => 'NOT IMPLEMENTED',
                        'framework_type' => $relation->type,
                        'function' => $function,
                        'model' => $name,
                    ];
                    return;
                    // dd($connection);
                    $relations[$key] = [
                        'type' => $type,
                        'connects' => [
                            $name => $connection,
                        ],
                    ];

                    // dd($relations);
                }
            }
        }

        return response()->json($relations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json($this->getModelShow($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
