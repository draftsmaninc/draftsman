<?php

namespace Draftsman\Draftsman\Http\Controllers\ApiV1;

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller as BaseController;

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

        //rev sort relations_count
        usort($models, function($a, $b) {
            return $b->relations_count - $a->relations_count;
        });

        return response()->json($models);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        dump($request->all());
        if ($request->has('modelname')) {
            $modelname =  Str::ucfirst(Str::camel($request->input('modelname')));
            Artisan::call('make:model', ['name' => $modelname, '-m' => true]);
            Artisan::call('migrate');
        }
        if ($request->has('attributename')) {
            $lastmigration = DB::table('migrations')->whereLike('migration','%_create_%_table')->orderBy('id')->get()->last();
            $attributename =  Str::snake($request->input('attributename'));
            $lastname = Str::afterLast($lastmigration->migration, 'create_');
            if ($lastname === 'jobs_table') {
                $lastname = 'users_table';
            }
            dump($lastname);
            $newname = 'add_'.$attributename.'_to_'.$lastname;
            Artisan::call('make:migration', ['name' => $newname]);
            $newfile = glob(database_path('migrations').'/*_'.$newname.'.php')[0];
            $content = File::get($newfile);
            $coltype = (Str::endsWith($attributename,'_id'))? 'bigInteger' : 'string';
            $upstring = '$table->'.$coltype.'(\''.$attributename.'\')->nullable()->after(\'id\');';
            $downstring = '$table->dropColumn(\''.$attributename.'\')->nullable();';
            $content = Str::replaceFirst('//', $upstring, $content);
            $content = Str::replaceLast('//', $downstring, $content);
            File::put($newfile, $content);
            Artisan::call('migrate');
        }
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
