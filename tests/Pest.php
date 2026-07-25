<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Draftsman\Draftsman\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class)->in(__DIR__);

/*
 * Shared fixture zoo: the models in tests/Fixtures/Models copied into the
 * Testbench skeleton's app path so getModelsList()/getModelShow() discover
 * them exactly as they would in a host app. model:show reads the schema, so
 * each fixture gets a real sqlite table. Used by the relation-contract tests
 * (RelationshipKeyTest, ConnectionTest).
 */
const FIXTURE_MODELS = [
    'App\\Models\\Membership',
    'App\\Models\\Note',
    'App\\Models\\Profile',
    'App\\Models\\Tag',
    'App\\Models\\Team',
    'App\\Models\\User',
];

function installFixtureModels(): void
{
    $target = app_path('Models');
    File::deleteDirectory($target);
    File::copyDirectory(__DIR__.'/Fixtures/Models', $target);
    foreach (File::allFiles($target) as $file) {
        require_once $file->getRealPath();
    }

    Schema::create('teams', function ($table) {
        $table->id();
        $table->timestamps();
    });
    Schema::create('users', function ($table) {
        $table->id();
        $table->foreignId('current_team_id')->nullable();
        $table->timestamps();
    });
    Schema::create('memberships', function ($table) {
        $table->id();
        $table->foreignId('user_id');
        $table->foreignId('team_id');
        $table->timestamps();
    });
    Schema::create('profiles', function ($table) {
        $table->id();
        $table->foreignId('user_id');
        $table->timestamps();
    });
    Schema::create('notes', function ($table) {
        $table->id();
        $table->morphs('notable');
        $table->timestamps();
    });
    Schema::create('tags', function ($table) {
        $table->id();
        $table->timestamps();
    });
    Schema::create('taggables', function ($table) {
        $table->id();
        $table->foreignId('tag_id');
        $table->morphs('taggable');
        $table->timestamps();
    });
}

/** @return array<string, object> relations keyed by "Model.relationName" */
function fixtureRelations(): array
{
    $api = new ApiController;
    $relations = [];
    foreach (FIXTURE_MODELS as $model) {
        $show = $api->getModelShow($model);
        expect($show)->not->toBeNull();
        foreach ($show->relations as $relation) {
            $relations[$model.'.'.$relation->name] = $relation;
        }
    }

    return $relations;
}
