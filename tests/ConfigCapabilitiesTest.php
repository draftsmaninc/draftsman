<?php

use Draftsman\Draftsman\Actions\RenderGraphImage;

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

/**
 * Contract for the shipped config file: only sections and keys with a consumer
 * or a committed purpose. The pre-graph-era front/graph sections and the dead
 * package keys (draftsman_path, model_class) are gone; editor/editor_flags
 * stay for the planned open-in-IDE inspector buttons.
 */
it('ships a lean config: live keys only', function () {
    $config = include dirname(__DIR__).'/config/draftsman.php';

    expect(array_keys($config))->toBe(['package'])
        ->and(array_keys($config['package']))->toBe([
            'editor',
            'editor_flags',
            'browser',
            'models_path',
            'snapshot_path',
            'graphs_path',
            'node_binary',
            'npm_binary',
            'update_env',
        ]);
});
