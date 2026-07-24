<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->graphsDir = base_path('draftsman-test-graphs');
    File::deleteDirectory($this->graphsDir);
    config()->set('draftsman.package.graphs_path', $this->graphsDir);
});

afterEach(function () {
    File::deleteDirectory($this->graphsDir);
});

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

it('renders a saved graph as a static page', function () {
    File::ensureDirectoryExists($this->graphsDir);
    File::put($this->graphsDir.DIRECTORY_SEPARATOR.'teams.json', json_encode(renderableGraphDocument()));

    $response = $this->get('/draftsman/render/teams');

    $response->assertOk()
        // Node chrome: positioned wrapper, header title, namespace subtitle.
        ->assertSee('flow-schema-node', false)
        ->assertSee('left: 168px', false)
        ->assertSee('width: 308px', false)
        ->assertSee('User')
        ->assertSee('App\Models')
        // Rows carry the marker classes and compact type labels.
        ->assertSee('flow-schema-row--pk', false)
        ->assertSee('flow-schema-row--fk', false)
        ->assertSee('flow-schema-row--required', false)
        ->assertSee('ub int')
        ->assertSee('tstamp')
        // Unknown types pass through with params stripped but NOT truncated —
        // the row-type CSS clips with an ellipsis, matching the live canvas.
        ->assertSee('varchar')
        // The edge draws from its persisted path; no JS anywhere.
        ->assertSee('M476,42 L560,42 L560,70 L644,70', false)
        ->assertDontSee('<script', false);
});

it('skips edges that never captured a rendered path', function () {
    File::ensureDirectoryExists($this->graphsDir);
    File::put($this->graphsDir.DIRECTORY_SEPARATOR.'teams.json', json_encode(renderableGraphDocument()));

    $this->get('/draftsman/render/teams')
        ->assertOk()
        ->assertDontSee('pathless-edge');
});

it('404s for a graph that has not been saved', function () {
    $this->get('/draftsman/render/nope')->assertNotFound();
});

it('404s for slugs outside the safe alphabet', function () {
    $this->get('/draftsman/render/'.rawurlencode('..evil'))->assertNotFound();
});
