<?php

use Illuminate\Support\Facades\File;

/**
 * Contract: `connection` names the topology of a relation — what, if anything,
 * the edge traverses between its two endpoints — and is 1:1 with the extra
 * metadata the relation carries:
 *
 *   direct  — a plain FK link; no intermediate, no pivot/through fields
 *   pivot   — traverses a join *table* (which may or may not be a model,
 *             ->using()); carries pivot_class / pivot_from / pivot_to
 *   through — traverses an intermediate *model*; carries through_class /
 *             through_from / through_to
 *
 * Renderers rely on this to spot shortcut edges that parallel a path through
 * a model already on the graph (e.g. suppress User<->Team belongsToMany when
 * Membership is drawn). Deliberately independent of relationship_key's scope
 * map, which stays 'direct' for pivots so saved-graph keys never move.
 */
const CONNECTION_BY_TYPE = [
    'BelongsTo' => 'direct',
    'BelongsToMany' => 'pivot',
    'HasMany' => 'direct',
    'HasManyThrough' => 'through',
    'HasOne' => 'direct',
    'HasOneThrough' => 'through',
    'MorphMany' => 'direct',
    'MorphOne' => 'direct',
    'MorphTo' => 'direct',
    'MorphToMany' => 'pivot',
];

beforeEach(function () {
    installFixtureModels();
});

afterEach(function () {
    File::deleteDirectory(app_path('Models'));
});

it('reports the connection topology for every emitted relation type', function () {
    $relations = collect(fixtureRelations());

    // one guard per type so a fixture rename can't silently hollow out the test
    foreach (array_keys(CONNECTION_BY_TYPE) as $type) {
        expect($relations->where('framework_type', $type))->not->toBeEmpty();
    }

    foreach ($relations as $name => $relation) {
        expect($relation->connection)
            ->toBe(CONNECTION_BY_TYPE[$relation->framework_type], "relation {$name}");
    }
});

it('pairs each connection value with its metadata shape', function () {
    foreach (fixtureRelations() as $name => $relation) {
        $hasPivot = property_exists($relation, 'pivot_class');
        $hasThrough = property_exists($relation, 'through_class');

        $expected = $hasPivot ? 'pivot' : ($hasThrough ? 'through' : 'direct');
        expect($relation->connection)->toBe($expected, "relation {$name}")
            ->and($hasPivot && $hasThrough)->toBeFalse("relation {$name} carries both pivot and through metadata");
    }
});
