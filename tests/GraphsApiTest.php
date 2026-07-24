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

function sampleGraphDocument(): array
{
    return [
        'version' => 1,
        'name' => 'Teams',
        'layout' => [
            'columnWidth' => 308,
            'gutterWidth' => 168,
            'rowpadHeight' => 98,
            'highwayHeight' => 140,
            'highwaysEvery' => 56,
        ],
        'nodes' => [
            [
                'id' => 'App\Models\User',
                'position' => ['x' => 0, 'y' => 0],
                'data' => ['label' => 'User'],
            ],
            [
                'id' => 'App\Models\Team',
                'position' => ['x' => 476, 'y' => 0],
                'data' => ['label' => 'Team'],
            ],
        ],
        'edges' => [
            [
                'id' => 'direct:App\Models\Team.id.App\Models\User.current_team_id',
                'source' => 'App\Models\User',
                'target' => 'App\Models\Team',
                'type' => 'gutter',
                'data' => [
                    'gutter' => ['srcOffset' => 0, 'dstOffset' => 0, 'hwyY' => 392, 'corner' => 8],
                    'path' => 'M 308 56 L 392 56 L 392 392 L 476 392',
                ],
            ],
        ],
    ];
}

it('lists no graphs when none are saved', function () {
    $this->getJson('/draftsman/api/graphs')
        ->assertOk()
        ->assertExactJson(['graphs' => []]);
});

it('saves a graph as a file in the configured graphs directory', function () {
    $this->putJson('/draftsman/api/graphs/teams', sampleGraphDocument())
        ->assertOk()
        ->assertJsonPath('graph.name', 'Teams');

    $file = $this->graphsDir.DIRECTORY_SEPARATOR.'teams.json';
    expect($file)->toBeFile()
        ->and(json_decode(File::get($file), true))->toBe(sampleGraphDocument());
});

it('round-trips a saved graph, preserving unknown future keys', function () {
    $document = sampleGraphDocument() + ['viewport' => ['x' => 1, 'y' => 2, 'zoom' => 0.5]];

    $this->putJson('/draftsman/api/graphs/teams', $document)->assertOk();

    $this->getJson('/draftsman/api/graphs/teams')
        ->assertOk()
        ->assertJsonPath('graph.viewport.zoom', 0.5)
        ->assertJsonPath('graph.nodes.0.id', 'App\Models\User')
        ->assertJsonPath('graph.edges.0.data.path', 'M 308 56 L 392 56 L 392 392 L 476 392');
});

it('lists saved graphs with slug and name', function () {
    $this->putJson('/draftsman/api/graphs/teams', sampleGraphDocument())->assertOk();
    $this->putJson('/draftsman/api/graphs/bhe', array_replace(sampleGraphDocument(), ['name' => 'BHE']))->assertOk();

    $this->getJson('/draftsman/api/graphs')
        ->assertOk()
        ->assertJsonCount(2, 'graphs')
        ->assertJsonPath('graphs.0.slug', 'bhe')
        ->assertJsonPath('graphs.0.name', 'BHE')
        ->assertJsonPath('graphs.1.slug', 'teams')
        ->assertJsonPath('graphs.1.name', 'Teams');
});

it('rejects slugs that could escape the graphs directory', function (string $slug) {
    $this->putJson('/draftsman/api/graphs/'.rawurlencode($slug), sampleGraphDocument())
        ->assertNotFound();

    expect(File::isDirectory($this->graphsDir))->toBeFalse();
})->with(['..evil', 'has space', 'UPPER', 'dot.slash', '.hidden']);

it('rejects documents missing required graph fields', function (string $missing) {
    $document = sampleGraphDocument();
    unset($document[$missing]);

    $this->putJson('/draftsman/api/graphs/teams', $document)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($missing);

    expect(File::isDirectory($this->graphsDir))->toBeFalse();
})->with(['version', 'name', 'nodes', 'edges']);

it('returns 404 for a graph that has not been saved', function () {
    $this->getJson('/draftsman/api/graphs/nope')->assertNotFound();
});

it('deletes a saved graph', function () {
    $this->putJson('/draftsman/api/graphs/teams', sampleGraphDocument())->assertOk();

    $this->deleteJson('/draftsman/api/graphs/teams')->assertNoContent();

    expect($this->graphsDir.DIRECTORY_SEPARATOR.'teams.json')->not->toBeFile();
    $this->getJson('/draftsman/api/graphs/teams')->assertNotFound();
});
