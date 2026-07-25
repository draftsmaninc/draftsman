<?php

use Draftsman\Draftsman\Actions\RenderGraphImage;
use Illuminate\Support\Facades\File;

/**
 * Records what the command asked for instead of driving a real headless
 * Chrome — Browsershot isn't installed in the package (it's a `suggest`),
 * so the real action's available() is false here and its handle() could
 * never run. Binding this fake IS the "Browsershot installed" scenario.
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

beforeEach(function () {
    $this->graphsDir = base_path('draftsman-test-graphs');
    File::deleteDirectory($this->graphsDir);
    config()->set('draftsman.package.graphs_path', $this->graphsDir);
});

afterEach(function () {
    File::deleteDirectory($this->graphsDir);
});

function saveRenderableGraph(string $dir): void
{
    File::ensureDirectoryExists($dir);
    File::put($dir.DIRECTORY_SEPARATOR.'teams.json', json_encode(renderableGraphDocument()));
}

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

it('points image formats at the Browsershot install when it is missing', function () {
    saveRenderableGraph($this->graphsDir);

    // Browsershot is genuinely absent in the package's own dev deps, so the
    // real action's available() is false — no fake needed for this path.
    $this->artisan('draftsman:render teams --format=png')
        ->expectsOutputToContain('spatie/browsershot')
        ->assertFailed();
});

it('renders an image via the Browsershot action when it is available', function () {
    saveRenderableGraph($this->graphsDir);
    $fake = new FakeRenderGraphImage;
    app()->instance(RenderGraphImage::class, $fake);

    $this->artisan('draftsman:render teams --format=png')
        ->expectsOutputToContain('teams.png')
        ->assertSuccessful();

    expect($fake->rendered)->not->toBeNull()
        ->and($fake->rendered['format'])->toBe('png')
        ->and($fake->rendered['path'])->toEndWith('teams.png')
        ->and($fake->rendered['html'])->toContain('flow-schema-node');
});

it('supports jpeg (accepting jpg as an alias) and pdf', function () {
    saveRenderableGraph($this->graphsDir);
    $fake = new FakeRenderGraphImage;
    app()->instance(RenderGraphImage::class, $fake);

    $this->artisan('draftsman:render teams --format=jpg')->assertSuccessful();
    expect($fake->rendered['format'])->toBe('jpeg')
        ->and($fake->rendered['path'])->toEndWith('teams.jpeg');

    $this->artisan('draftsman:render teams --format=pdf')->assertSuccessful();
    expect($fake->rendered['format'])->toBe('pdf')
        ->and($fake->rendered['path'])->toEndWith('teams.pdf');
});

it('passes the page dimensions so pdf paper can match the canvas', function () {
    saveRenderableGraph($this->graphsDir);
    $fake = new FakeRenderGraphImage;
    app()->instance(RenderGraphImage::class, $fake);

    // renderableGraphDocument: nodes span x 0–952, y 0–420; pad 56 per side.
    $this->artisan('draftsman:render teams --format=pdf')->assertSuccessful();
    expect($fake->rendered['size'])->toBe(['width' => 1064, 'height' => 532]);
});

it('says svg is not available yet, even with Browsershot present', function () {
    saveRenderableGraph($this->graphsDir);
    app()->instance(RenderGraphImage::class, new FakeRenderGraphImage);

    $this->artisan('draftsman:render teams --format=svg')
        ->expectsOutputToContain('not available yet')
        ->assertFailed();
});

it('rejects unknown formats with the list of real ones', function () {
    saveRenderableGraph($this->graphsDir);

    $this->artisan('draftsman:render teams --format=webp')
        ->expectsOutputToContain('html (built in) and png, jpeg, pdf')
        ->assertFailed();
});
