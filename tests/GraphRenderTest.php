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

it('renders a saved graph as a static page', function () {
    File::ensureDirectoryExists($this->graphsDir);
    File::put($this->graphsDir.DIRECTORY_SEPARATOR.'teams.json', json_encode(renderableGraphDocument()));

    $response = $this->get('/draftsman/api/render/teams');

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
        // varchar maps to vchar (7 chars raw would ellipsis-clip), matching
        // frontv's type-labels.ts — the two maps must stay in sync.
        ->assertSee('vchar')
        ->assertDontSee('varchar')
        // The edge draws from its persisted path; no JS anywhere.
        ->assertSee('M476,42 L560,42 L560,70 L644,70', false)
        ->assertDontSee('<script', false);
});

it('skips edges that never captured a rendered path', function () {
    File::ensureDirectoryExists($this->graphsDir);
    File::put($this->graphsDir.DIRECTORY_SEPARATOR.'teams.json', json_encode(renderableGraphDocument()));

    $this->get('/draftsman/api/render/teams')
        ->assertOk()
        ->assertDontSee('pathless-edge');
});

it('404s for a graph that has not been saved', function () {
    $this->get('/draftsman/api/render/nope')->assertNotFound();
});

it('404s for slugs outside the safe alphabet', function () {
    $this->get('/draftsman/api/render/'.rawurlencode('..evil'))->assertNotFound();
});
