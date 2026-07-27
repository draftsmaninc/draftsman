<?php

namespace Draftsman\Draftsman\Http\Controllers\ApiV1;

use Illuminate\Http\Request;

class ModelsController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json($this->getModels());
    }

    /**
     * Display a presorted listing of the resource.
     */
    public function presorted()
    {
        $models = $this->getModels();

        // rev sort relations_count
        usort($models, function ($a, $b) {
            return $b->relations_count - $a->relations_count;
        });

        return response()->json($models);
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
        return response()->json($this->getModel($id));
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
