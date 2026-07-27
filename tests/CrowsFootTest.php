<?php

use Illuminate\Support\Facades\File;

/**
 * Contract: `multiplicity` + `mandatory` are the crow's-foot fields
 * (https://www.red-gate.com/blog/crow-s-foot-notation/) and mean:
 *
 *   multiplicity — the cardinality glyph at the FROM model's own end of the
 *                  edge: how many from-rows one related row can have. A
 *                  BelongsTo's declarer is the many side ('many'); a HasMany's
 *                  declarer is the one side ('one'). Note this is the INVERSE
 *                  of the relation's return cardinality (the `type` field).
 *
 *   mandatory    — whether the edge's "one" side is required. Checked against
 *                  the real FK column's nullability when that column lives on
 *                  the declaring model (BelongsTo, MorphTo); assumed true
 *                  (the conventional non-null FK) when it lives on the related
 *                  model, which isn't loaded in this pass; false for the
 *                  *ToMany types, which have no "one" side.
 *
 * A renderer can decorate BOTH ends of an edge from one relation record:
 * the from end via `multiplicity`, the to end via `type`; 'many' ends draw
 * zero-or-many, 'one' ends draw exactly-one / zero-or-one by `mandatory`.
 *
 * MorphTo emits as a targetless morph-child record (see MorphChildTest); its
 * crow's-foot fields behave like BelongsTo's — 'many' from end, FK-checked
 * mandatory — since it IS the FK-holder side of its edges.
 */
const MULTIPLICITY_BY_TYPE = [
    'BelongsTo' => 'many',
    'BelongsToMany' => 'many',
    'HasMany' => 'one',
    'HasManyThrough' => 'one',
    'HasOne' => 'one',
    'HasOneThrough' => 'one',
    'MorphMany' => 'one',
    'MorphOne' => 'one',
    'MorphTo' => 'many',
    'MorphToMany' => 'many',
];

// The types whose mandatory value is fixed by shape alone. BelongsTo and
// MorphTo are absent deliberately: theirs comes from the FK column.
const MANDATORY_BY_TYPE = [
    'BelongsToMany' => false,
    'HasMany' => true,
    'HasManyThrough' => true,
    'HasOne' => true,
    'HasOneThrough' => true,
    'MorphMany' => true,
    'MorphOne' => true,
    'MorphToMany' => false,
];

beforeEach(function () {
    installFixtureModels();
});

afterEach(function () {
    File::deleteDirectory(app_path('Models'));
});

it('reports the from-end multiplicity for every relation type', function () {
    $relations = collect(fixtureRelations());

    // one guard per type so a fixture rename can't silently hollow out the test
    foreach (array_keys(MULTIPLICITY_BY_TYPE) as $type) {
        expect($relations->where('framework_type', $type))->not->toBeEmpty();
    }

    foreach ($relations as $name => $relation) {
        expect($relation->multiplicity)
            ->toBe(MULTIPLICITY_BY_TYPE[$relation->framework_type], "relation {$name}");
    }
});

it('assumes the conventional non-null FK for types whose key lives on the related model', function () {
    foreach (fixtureRelations() as $name => $relation) {
        if (! array_key_exists($relation->framework_type, MANDATORY_BY_TYPE)) {
            continue;
        }
        expect($relation->mandatory)
            ->toBe(MANDATORY_BY_TYPE[$relation->framework_type], "relation {$name}");
    }
});

it('derives mandatory from the FK column nullability when it is checkable', function () {
    $relations = fixtureRelations();

    // users.current_team_id is nullable — the one optional FK in the zoo
    expect($relations['App\\Models\\User.currentTeam']->mandatory)->toBeFalse();

    // non-null FK columns => the parent is required
    expect($relations['App\\Models\\Membership.user']->mandatory)->toBeTrue();
    expect($relations['App\\Models\\Membership.team']->mandatory)->toBeTrue();
    expect($relations['App\\Models\\Profile.user']->mandatory)->toBeTrue();

    // MorphTo's key is on the declarer too — morphs() columns are non-null
    expect($relations['App\\Models\\Note.notable']->mandatory)->toBeTrue();
});
