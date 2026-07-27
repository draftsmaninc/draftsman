<?php

use Draftsman\Draftsman\Actions\RenderGraphImage;
use Draftsman\Draftsman\DraftsmanServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * The config endpoint reports what this backend can DO alongside what it's
 * configured to be — `capabilities` is computed at request time, never stored.
 * Frontends read it to shape their UI (e.g. offering pdf in the download menu
 * only when the backend can actually produce one).
 */
it('reports html as the only render format without Browsershot', function () {
    $this->getJson('/draftsman/api/config')
        ->assertOk()
        ->assertJsonPath('capabilities.render.formats', ['html']);
});

it('reports the full render format set when Browsershot is available', function () {
    app()->instance(RenderGraphImage::class, new FakeRenderGraphImage);

    $this->getJson('/draftsman/api/config')
        ->assertOk()
        ->assertJsonPath('capabilities.render.formats', ['html', 'png', 'jpeg', 'pdf']);
});

it('includes the host app about report (artisan about --json)', function () {
    $response = $this->getJson('/draftsman/api/config')->assertOk();

    // `about --json` groups by section; environment is always present and
    // carries the app name + Laravel version — the parts a frontend shows.
    $about = $response->json('about');
    expect($about)->toBeArray()
        ->and($about)->toHaveKey('environment')
        ->and($about['environment'])->toHaveKey('laravel_version');
});

it('does not offer the views for publishing — the render template is internal', function () {
    $tagged = ServiceProvider::pathsToPublish(
        DraftsmanServiceProvider::class,
        'draftsman-views',
    );

    expect($tagged)->toBe([]);
});

it('still resolves the render template through the draftsman:: namespace', function () {
    expect(view()->exists('draftsman::graph-render'))->toBeTrue();
});

/**
 * Contract for the shipped config file: only keys with a live consumer. The
 * pre-graph-era front/graph sections and every dead key (draftsman_path,
 * model_class, editor, editor_flags, browser, update_env) are gone — editor
 * keys can return WITH the open-in-IDE feature, not ahead of it.
 */
it('ships a lean config: live keys only', function () {
    $config = include dirname(__DIR__).'/config/draftsman.php';

    expect(array_keys($config))->toBe(['package'])
        ->and(array_keys($config['package']))->toBe([
            'models_path',
            'snapshot_path',
            'graphs_path',
            'node_binary',
            'npm_binary',
        ]);
});
