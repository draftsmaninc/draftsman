<?php

use Draftsman\Draftsman\Actions\ChangedFiles;
use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Contract: models whose FILE has uncommitted git changes (vs HEAD — modified,
 * added, renamed or untracked) emit flagged `changed: true`, so the graph can
 * indicate work-in-progress. Rides the normal presorted payload, so changed
 * models are already ordered most-related-first for any future apply pipeline.
 *
 * Capability-gated: no git repo (or git not runnable) means no flags and
 * capabilities.git.available === false — the render-formats pattern.
 */
beforeEach(function () {
    installFixtureModels();
});

afterEach(function () {
    File::deleteDirectory(app_path('Models'));
});

/** Fake a git repo whose toplevel is the skeleton base_path, with the given
 *  porcelain output. Wildcard patterns — array commands serialize with shell
 *  escaping, so exact-string keys silently miss and the REAL git answers
 *  (and this package's own repo says "true"). */
function fakeGit(string $porcelain): void
{
    Process::fake([
        '*is-inside-work-tree*' => Process::result("true\n"),
        '*show-toplevel*' => Process::result(base_path()."\n"),
        '*status*porcelain*' => Process::result($porcelain),
    ]);
}

it('flags a model whose file has uncommitted changes', function () {
    fakeGit(" M app/Models/User.php\n");

    $models = collect((new ApiController)->getModels())->keyBy('class');

    expect($models['App\\Models\\User']->changed)->toBeTrue()
        ->and(property_exists($models['App\\Models\\Team'], 'changed'))->toBeFalse();
});

it('flags untracked and renamed model files by their live path', function () {
    fakeGit("?? app/Models/Note.php\nR  app/Models/Old.php -> app/Models/Profile.php\n");

    $models = collect((new ApiController)->getModels())->keyBy('class');

    expect($models['App\\Models\\Note']->changed)->toBeTrue()
        ->and($models['App\\Models\\Profile']->changed)->toBeTrue()
        ->and(property_exists($models['App\\Models\\User'], 'changed'))->toBeFalse();
});

it('emits no flags when the host has no git repo', function () {
    Process::fake([
        '*is-inside-work-tree*' => Process::result(errorOutput: 'fatal: not a git repository', exitCode: 128),
    ]);

    $models = collect((new ApiController)->getModels());

    expect($models->filter(fn ($m) => property_exists($m, 'changed')))->toBeEmpty();
});

it('reports git availability through the config capabilities', function () {
    fakeGit('');

    $response = $this->getJson('draftsman/api/config');

    $response->assertOk()
        ->assertJsonPath('capabilities.git.available', true);
});

it('reports git unavailable when there is no repo', function () {
    Process::fake([
        '*is-inside-work-tree*' => Process::result(errorOutput: 'fatal: not a git repository', exitCode: 128),
    ]);

    $response = $this->getJson('draftsman/api/config');

    $response->assertOk()
        ->assertJsonPath('capabilities.git.available', false);
});

it('distinguishes modified, added and deleted statuses per file', function () {
    fakeGit(" M app/Models/User.php\n?? app/Models/Note.php\nD  app/Models/Gone.php\n");

    $changed = (new ChangedFiles)->handle();

    // Deleted files can't realpath (they're gone) — the path is still emitted,
    // absolutized against the repo toplevel.
    expect($changed[realpath(app_path('Models/User.php'))])->toBe('modified')
        ->and($changed[realpath(app_path('Models/Note.php'))])->toBe('added')
        ->and($changed)->toHaveKey(app_path('Models/Gone.php'))
        ->and($changed[app_path('Models/Gone.php')])->toBe('deleted');
});

it('splits a rename into a deleted old path and an added new path', function () {
    fakeGit("R  app/Models/Old.php -> app/Models/Profile.php\n");

    $changed = (new ChangedFiles)->handle();

    expect($changed[realpath(app_path('Models/Profile.php'))])->toBe('added')
        ->and($changed[app_path('Models/Old.php')])->toBe('deleted');
});

it('flags a git-new model file as created on top of changed', function () {
    fakeGit("?? app/Models/Note.php\n M app/Models/User.php\n");

    $models = collect((new ApiController)->getModels())->keyBy('class');

    expect($models['App\\Models\\Note']->created)->toBeTrue()
        ->and($models['App\\Models\\Note']->changed)->toBeTrue()
        ->and(property_exists($models['App\\Models\\User'], 'created'))->toBeFalse()
        ->and($models['App\\Models\\User']->changed)->toBeTrue();
});

it('lists deleted model files with their guessed class names', function () {
    fakeGit("D  app/Models/Gone.php\n M app/Models/User.php\n");

    $response = $this->getJson('draftsman/api/models/changed');

    $response->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('deleted.0.class', 'App\\Models\\Gone')
        ->assertJsonCount(1, 'deleted');
});

it('ignores deleted files that cannot be app classes', function () {
    fakeGit("D  routes/web.php\nD  README.md\nD  app/Models/Gone.php\n");

    $response = $this->getJson('draftsman/api/models/changed');

    $response->assertOk()
        ->assertJsonCount(1, 'deleted')
        ->assertJsonPath('deleted.0.class', 'App\\Models\\Gone');
});

it('reports the changes endpoint unavailable without a git repo', function () {
    Process::fake([
        '*is-inside-work-tree*' => Process::result(errorOutput: 'fatal: not a git repository', exitCode: 128),
    ]);

    $response = $this->getJson('draftsman/api/models/changed');

    $response->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('deleted', []);
});
