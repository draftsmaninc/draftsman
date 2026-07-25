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

it('renders a saved graph to an html file beside its document', function () {
    File::ensureDirectoryExists($this->graphsDir);
    File::put($this->graphsDir.DIRECTORY_SEPARATOR.'teams.json', json_encode(renderableGraphDocument()));

    $this->artisan('draftsman:render teams')
        ->expectsOutputToContain('teams.html')
        ->assertSuccessful();

    $html = File::get($this->graphsDir.DIRECTORY_SEPARATOR.'teams.html');
    expect($html)->toContain('flow-schema-node')
        ->toContain('User')
        ->toContain('M476,42 L560,42 L560,70 L644,70')
        ->not->toContain('<script');
});

it('honours an explicit output path', function () {
    File::ensureDirectoryExists($this->graphsDir);
    File::put($this->graphsDir.DIRECTORY_SEPARATOR.'teams.json', json_encode(renderableGraphDocument()));
    $out = $this->graphsDir.DIRECTORY_SEPARATOR.'custom'.DIRECTORY_SEPARATOR.'diagram.html';

    $this->artisan('draftsman:render', ['slug' => 'teams', '--path' => $out])->assertSuccessful();

    expect($out)->toBeFile();
});

it('fails clearly for a graph that has not been saved', function () {
    $this->artisan('draftsman:render nope')
        ->expectsOutputToContain('No saved graph')
        ->assertFailed();
});

it('fails clearly for formats that are not available yet', function () {
    File::ensureDirectoryExists($this->graphsDir);
    File::put($this->graphsDir.DIRECTORY_SEPARATOR.'teams.json', json_encode(renderableGraphDocument()));

    $this->artisan('draftsman:render teams --format=png')
        ->expectsOutputToContain('html')
        ->assertFailed();
});
