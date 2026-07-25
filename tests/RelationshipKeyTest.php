<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Support\Facades\File;

/**
 * Contract: relationship_key is direction-indifferent — the two endpoints
 * ("Model.attribute" for each side) joined by '.', with the endpoints in
 * alphabetical order. Mirrored declarations (User.teams / Team.members)
 * must therefore emit the identical key, which is what lets consumers
 * de-duplicate edges.
 *
 * Fixture zoo + helpers (FIXTURE_MODELS, installFixtureModels,
 * fixtureRelations) live in Pest.php, shared with ConnectionTest.
 */

/**
 * Types migrated to the alpha-sorted contract so far, with the scope each
 * prefixes onto its key (ApiController::$relationshipKeyAlphaSorted /
 * $relationshipKeyScopeMap). The scope keeps semantically different
 * connection categories between the same endpoints from colliding — a
 * through shortcut never dedups against the direct pair it parallels.
 */
const MIGRATED_TYPE_SCOPES = [
    'BelongsTo' => 'direct',
    'BelongsToMany' => 'direct',
    'HasMany' => 'direct',
    'HasManyThrough' => 'through',
    'HasOne' => 'direct',
    'HasOneThrough' => 'through',
    'MorphMany' => 'direct',
    'MorphOne' => 'direct',
    'MorphToMany' => 'direct',
];

function alphaSortedKey(object $relation): string
{
    $endpoints = [
        $relation->from.'.'.$relation->from_attribute,
        $relation->to.'.'.$relation->to_attribute,
    ];
    sort($endpoints, SORT_STRING);

    return MIGRATED_TYPE_SCOPES[$relation->framework_type].':'.implode('.', $endpoints);
}

beforeEach(function () {
    installFixtureModels();
});

afterEach(function () {
    File::deleteDirectory(app_path('Models'));
});

it('discovers the fixture models in the skeleton app path', function () {
    $models = (new ApiController)->getModelsList();

    expect($models)->toBe(FIXTURE_MODELS);
});

it('emits a scoped, alpha-sorted endpoint pair as relationship_key for every migrated type', function () {
    $migrated = collect(fixtureRelations())
        ->filter(fn ($relation) => array_key_exists($relation->framework_type, MIGRATED_TYPE_SCOPES));

    // one guard per type so a fixture rename can't silently hollow out the property
    foreach (array_keys(MIGRATED_TYPE_SCOPES) as $type) {
        expect($migrated->where('framework_type', $type))->not->toBeEmpty();
    }

    foreach ($migrated as $name => $relation) {
        expect($relation->relationship_key)
            ->toBe(alphaSortedKey($relation), "relation {$name}");
    }
});

it('emits the identical key for both sides of a mirrored relation pair', function () {
    $relations = fixtureRelations();
    $mirrors = [
        ['App\\Models\\Membership.team', 'App\\Models\\Team.memberships'],
        ['App\\Models\\Membership.user', 'App\\Models\\User.memberships'],
        ['App\\Models\\Profile.user', 'App\\Models\\User.profile'],
        ['App\\Models\\Team.members', 'App\\Models\\User.teams'],
        ['App\\Models\\Tag.teams', 'App\\Models\\Team.tags'],
    ];

    foreach ($mirrors as [$a, $b]) {
        expect($relations[$a]->relationship_key)
            ->toBe($relations[$b]->relationship_key, "mirror {$a} / {$b}");
    }
});

it('keys User.currentTeam as the sorted Team.id / User.current_team_id pair', function () {
    $relations = fixtureRelations();

    expect($relations['App\\Models\\User.currentTeam']->relationship_key)
        ->toBe('direct:App\\Models\\Team.id.App\\Models\\User.current_team_id');
});

it('keys Membership.user with Membership first even though User is the parent', function () {
    $relations = fixtureRelations();

    expect($relations['App\\Models\\Membership.user']->relationship_key)
        ->toBe('direct:App\\Models\\Membership.user_id.App\\Models\\User.id');
});

it('keys the HasManyThrough shortcut by its flattened endpoints, sorted', function () {
    $relations = fixtureRelations();

    expect($relations['App\\Models\\User.ownedTeams']->relationship_key)
        ->toBe('through:App\\Models\\Team.id.App\\Models\\User.id');
});

it('keys MorphMany by the morph id column on the related side, sorted', function () {
    $relations = fixtureRelations();

    expect($relations['App\\Models\\User.notes']->relationship_key)
        ->toBe('direct:App\\Models\\Note.notable_id.App\\Models\\User.id');
});

it('keys the HasOneThrough shortcut by its FK-chain endpoints, sorted and through-scoped', function () {
    $relations = fixtureRelations();

    expect($relations['App\\Models\\Membership.userProfile']->relationship_key)
        ->toBe('through:App\\Models\\Membership.user_id.App\\Models\\Profile.user_id');
});

it('keys MorphOne by the morph id column on the related side, sorted', function () {
    $relations = fixtureRelations();

    expect($relations['App\\Models\\Team.note']->relationship_key)
        ->toBe('direct:App\\Models\\Note.notable_id.App\\Models\\Team.id');
});

it('keys MorphToMany by the parent keys on both sides, like BelongsToMany', function () {
    $relations = fixtureRelations();

    expect($relations['App\\Models\\Team.tags']->relationship_key)
        ->toBe('direct:App\\Models\\Tag.id.App\\Models\\Team.id');
});

it('drops MorphTo relations entirely — the target is runtime data, not schema', function () {
    $api = new ApiController;
    $note = $api->getModelShow('App\\Models\\Note');

    expect($note->relations)->toBe([])
        ->and($note->relations_count)->toBe(0)
        ->and($note->related_models)->toBe([]);
});

it('keeps a self-referential MorphToMany — same endpoints is not the same as degenerate', function () {
    $relations = fixtureRelations();

    expect($relations)->toHaveKey('App\\Models\\Tag.relatedTags')
        ->and($relations['App\\Models\\Tag.relatedTags']->relationship_key)
        ->toBe('direct:App\\Models\\Tag.id.App\\Models\\Tag.id');
});

it('scopes a through shortcut apart from the direct pair over the same endpoints', function () {
    $relations = fixtureRelations();
    $through = $relations['App\\Models\\User.ownedTeams'];
    $direct = $relations['App\\Models\\User.teams'];

    // identical endpoints (User.id / Team.id both ways) — only the scope separates them
    expect($through->relationship_key)->not->toBe($direct->relationship_key);
});
