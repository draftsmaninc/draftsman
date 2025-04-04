<?php

namespace DraftsmanInc\Draftsman\Http\Controllers;

use Illuminate\Http\Request;

class RelationsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $list = $this->getModels()->toArray();
        $relations = [];
        $models = [];
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
                    $key = $type.'.'.$name.'.'.$function.'.'.$related.'.NULL';
                    $connection = [
                        'column' => 'NOT IMPLEMENTED',
                        'framework_type' => $relation->type,
                        'function' => $function,
                        'model' => $name,
                    ];
                    $relations[$key] = [
                        'type' => $type,
                        'connects' => [
                            $name => $connection,
                        ],
                    ];
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
