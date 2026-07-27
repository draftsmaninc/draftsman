<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Support\Facades\File;

/**
 * Contract: MorphTo relations emit as TARGETLESS morph-child records.
 *
 * model:show can never resolve a MorphTo's real target — it's runtime data in
 * the *_type column — so the record carries `to`/`to_attribute` as null rather
 * than a fabricated self-reference (and rather than being dropped, which
 * erased the child's knowledge of its own morph columns: renderers had no way
 * to badge notable_id as a key without scanning other models' relations).
 *
 * The join contract: the child's morph_key equals its owners' morph_key
 * (both name the child's column pair), so consumers can connect a MorphTo to
 * the MorphOne/MorphMany/MorphToMany edges that target it.
 */
beforeEach(function () {
    installFixtureModels();
});

afterEach(function () {
    File::deleteDirectory(app_path('Models'));
});

it('emits MorphTo as a targetless morph-child record', function () {
    $rel = fixtureRelations()['App\\Models\\Note.notable'] ?? null;

    expect($rel)->not->toBeNull();
    expect($rel->framework_type)->toBe('MorphTo')
        ->and($rel->from)->toBe('App\\Models\\Note')
        ->and($rel->from_attribute)->toBe('notable_id')
        ->and($rel->morph_attribute)->toBe('notable_type')
        ->and($rel->to)->toBeNull()
        ->and($rel->to_attribute)->toBeNull();
});

it('carries the crow\'s-foot and topology fields like any direct relation', function () {
    $rel = fixtureRelations()['App\\Models\\Note.notable'];

    expect($rel->connection)->toBe('direct')
        ->and($rel->type)->toBe('one')
        ->and($rel->multiplicity)->toBe('many')
        // notes.notable_id comes from morphs(), which is non-nullable
        ->and($rel->mandatory)->toBeTrue();
});

it('gives the morph child the same morph_key as its owners', function () {
    $relations = fixtureRelations();

    $child = $relations['App\\Models\\Note.notable'];
    $ownerOne = $relations['App\\Models\\Team.note'];    // MorphOne  -> Note
    $ownerMany = $relations['App\\Models\\User.notes'];  // MorphMany -> Note

    expect($child->morph_key)->toBe('App\\Models\\Note.notable_id.notable_type')
        ->and($ownerOne->morph_key)->toBe($child->morph_key)
        ->and($ownerMany->morph_key)->toBe($child->morph_key);
});

it('scopes the morph-child relationship_key to its own column pair', function () {
    $rel = fixtureRelations()['App\\Models\\Note.notable'];

    // 'morph:' scope — never collides with the owner edges' keys, and stays
    // stable per column pair (a second MorphTo on the model gets its own).
    expect($rel->relationship_key)->toBe('morph:App\\Models\\Note.notable_id.notable_type');
});

it('does not list the declaring model in related_models via its own MorphTo', function () {
    $api = new ApiController;
    $show = $api->getModelShow('App\\Models\\Note');

    // The fabricated self-target must not register as a phantom relative.
    expect($show->related_models)->not->toContain('App\\Models\\Note');
});
