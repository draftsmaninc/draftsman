<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Support\Facades\File;

/**
 * Contract: generic pivot tables (a BelongsToMany/MorphToMany with no
 * ->using() model — pivot_class "<base class>.<table>") emit as PSEUDO-MODELS
 * so the graph can draw them as real junction nodes:
 *
 *   - class    = the dotted pivot_class (alphabetically first variant when
 *                several base classes name the same table)
 *   - one entry per TABLE, however many relations traverse it
 *   - attributes introspected from the schema
 *   - relations = the two underlying halves of each traversal, emitted as
 *     BelongsTo records (pivot column -> endpoint key), deduped by
 *     relationship_key across mirror declarations — plus a targetless MorphTo
 *     for a morph pivot's column pair, exactly like a morph child model
 *   - tables owned by a scanned model (memberships -> Membership) are SKIPPED:
 *     the model already represents them
 */
beforeEach(function () {
    installFixtureModels();
});

afterEach(function () {
    File::deleteDirectory(app_path('Models'));
});

function pivotShows(): array
{
    return collect((new ApiController)->getModels())
        ->filter(fn ($m) => str_contains($m->class, '.'))
        ->keyBy('class')
        ->all();
}

it('emits one pseudo-model per generic pivot table, keyed by the dotted class', function () {
    $pivots = pivotShows();

    // taggables is referenced as Pivot.taggables (plain morphToMany) AND
    // MorphPivot.taggables (->using(MorphPivot::class)) — ONE entry, under the
    // alphabetically first dotted id.
    expect($pivots)->toHaveCount(1)
        ->and($pivots)->toHaveKey('Illuminate\\Database\\Eloquent\\Relations\\MorphPivot.taggables')
        ->and($pivots['Illuminate\\Database\\Eloquent\\Relations\\MorphPivot.taggables']->table)->toBe('taggables');
});

it('skips tables owned by a scanned model', function () {
    // User.teams / Team.members traverse the memberships table generically,
    // but Membership IS a model — no pseudo-model may shadow it.
    expect(collect(pivotShows())->keys()->filter(fn ($c) => str_contains($c, 'memberships')))->toBeEmpty();
});

it('introspects the pivot columns from the schema', function () {
    $pivot = pivotShows()['Illuminate\\Database\\Eloquent\\Relations\\MorphPivot.taggables'];

    $names = collect($pivot->attributes)->pluck('name');
    expect($names)->toContain('tag_id')
        ->and($names)->toContain('taggable_id')
        ->and($names)->toContain('taggable_type');
});

it('emits the underlying halves as BelongsTo records, deduped across mirrors', function () {
    $pivot = pivotShows()['Illuminate\\Database\\Eloquent\\Relations\\MorphPivot.taggables'];
    $halves = collect($pivot->relations)->where('framework_type', 'BelongsTo');

    // tag_id -> Tag.id (one, though Team.tags, Tag.teams and Tag.relatedTags
    // all traverse it) and taggable_id -> each declared owner (Team, Tag via
    // relatedTags) — keyed to the pivot as the FK holder.
    $pairs = $halves->map(fn ($r) => $r->from_attribute.' -> '.$r->to)->sort()->values()->all();
    expect($pairs)->toBe([
        'tag_id -> App\\Models\\Tag',
        'taggable_id -> App\\Models\\Tag',
        'taggable_id -> App\\Models\\Team',
    ]);
    foreach ($halves as $half) {
        expect($half->from)->toBe($pivot->class)
            ->and($half->connection)->toBe('direct')
            ->and($half->type)->toBe('one')
            ->and($half->multiplicity)->toBe('many');
    }
});

it('emits a targetless MorphTo for a morph pivot, like a morph child model', function () {
    $pivot = pivotShows()['Illuminate\\Database\\Eloquent\\Relations\\MorphPivot.taggables'];
    $morph = collect($pivot->relations)->firstWhere('framework_type', 'MorphTo');

    expect($morph)->not->toBeNull()
        ->and($morph->to)->toBeNull()
        ->and($morph->from_attribute)->toBe('taggable_id')
        ->and($morph->morph_attribute)->toBe('taggable_type')
        ->and($morph->morph_key)->toBe($pivot->class.'.taggable_id.taggable_type')
        ->and($morph->relationship_key)->toBe('morph:'.$pivot->class.'.taggable_id.taggable_type');
});

it('lists the endpoints as related models and counts its fields and relations', function () {
    $pivot = pivotShows()['Illuminate\\Database\\Eloquent\\Relations\\MorphPivot.taggables'];

    expect($pivot->related_models)->toContain('App\\Models\\Tag')
        ->and($pivot->related_models)->toContain('App\\Models\\Team')
        ->and($pivot->attributes_count)->toBe(count($pivot->attributes))
        ->and($pivot->relations_count)->toBe(count($pivot->relations));
});
