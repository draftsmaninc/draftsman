<?php

use Draftsman\Draftsman\Actions\RenderGraphImage;
use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Draftsman\Draftsman\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class)->in(__DIR__);

/**
 * Records what callers asked for instead of driving a real headless Chrome —
 * Browsershot isn't installed in the package (it's a `suggest`), so the real
 * action's available() is false here and its handle() could never run.
 * Binding this into the container IS the "Browsershot installed" scenario.
 * Shared by RenderCommandTest, ApiControllerTest and GraphsApiTest.
 */
class FakeRenderGraphImage extends RenderGraphImage
{
    /** @var array{html: string, format: string, path: string, size: array|null}|null */
    public ?array $rendered = null;

    public function available(): bool
    {
        return true;
    }

    public function handle(string $html, string $format, string $path, ?array $size = null): void
    {
        $this->rendered = compact('html', 'format', 'path', 'size');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "fake-{$format}");
    }
}

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

/** A graph document the way frontv saves one: node geometry + data, and each
 *  edge carrying its rendered SVG path (data.path) alongside the gutter route. */
function renderableGraphDocument(): array
{
    return [
        'version' => 1,
        'name' => 'Teams',
        'layout' => ['columnWidth' => 308, 'gutterWidth' => 168],
        'nodes' => [
            [
                'id' => 'App\Models\User',
                'position' => ['x' => 168, 'y' => 0],
                'dimensions' => ['width' => 308, 'height' => 420],
                'data' => [
                    'label' => 'User',
                    'namespace' => 'App\Models',
                    'fields' => [
                        ['name' => 'id', 'type' => 'bigint unsigned', 'key' => 'primary', 'required' => true],
                        ['name' => 'current_team_id', 'type' => 'bigint unsigned', 'key' => 'foreign'],
                        ['name' => 'email_verified_at', 'type' => 'timestamp'],
                        ['name' => 'name', 'type' => 'varchar(255)', 'required' => true],
                    ],
                ],
            ],
            [
                'id' => 'App\Models\Team',
                'position' => ['x' => 644, 'y' => 0],
                'dimensions' => ['width' => 308, 'height' => 280],
                'data' => [
                    'label' => 'Team',
                    'namespace' => 'App\Models',
                    'fields' => [
                        ['name' => 'id', 'type' => 'bigint unsigned', 'key' => 'primary', 'required' => true],
                    ],
                ],
            ],
        ],
        'edges' => [
            [
                'id' => 'direct:App\Models\Team.id.App\Models\User.current_team_id',
                'source' => 'App\Models\User',
                'target' => 'App\Models\Team',
                'type' => 'gutter',
                'data' => [
                    'gutter' => ['srcOffset' => 0, 'dstOffset' => 0, 'hwyY' => null, 'corner' => 'smoothstep'],
                    'path' => 'M476,42 L560,42 L560,70 L644,70',
                ],
            ],
            [
                'id' => 'pathless-edge',
                'source' => 'App\Models\User',
                'target' => 'App\Models\Team',
                'type' => 'gutter',
                'data' => ['gutter' => ['srcOffset' => 0, 'dstOffset' => 0, 'hwyY' => null, 'corner' => 'smoothstep']],
            ],
        ],
    ];
}
