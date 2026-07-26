<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Support\Facades\File;

/**
 * Contract: schema drift is SURFACED, not hidden. A model whose table doesn't
 * exist (BHE's Sponsor — code shipped, migration never ran) still emits, with
 * `missing_table: true` so renderers can flag it; same for a generic pivot
 * table that relations traverse but the database lacks (BHE's riderables).
 * Their edges can't draw (no columns to attach to), so the flag is the only
 * way the graph can explain the ghost.
 */
beforeEach(function () {
    installFixtureModels();

    File::put(app_path('Models/Phantom.php'), <<<'PHP'
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class Phantom extends Model
    {
    }
    PHP);
    require_once app_path('Models/Phantom.php');
    // deliberately NO phantoms table
});

afterEach(function () {
    File::deleteDirectory(app_path('Models'));
});

it('emits a model whose table is missing, flagged missing_table', function () {
    $models = collect((new ApiController)->getModels())->keyBy('class');

    expect($models)->toHaveKey('App\\Models\\Phantom')
        ->and($models['App\\Models\\Phantom']->missing_table)->toBeTrue()
        ->and($models['App\\Models\\Phantom']->attributes_count)->toBe(0);
});

it('does not flag models whose tables exist', function () {
    $models = collect((new ApiController)->getModels())->keyBy('class');

    expect(property_exists($models['App\\Models\\User'], 'missing_table'))->toBeFalse();
});

it('emits a generic pivot whose table is missing, flagged, instead of skipping it', function () {
    // Mirror BHE's riderables: a morphToMany traversing a table that was
    // never migrated. The pseudo-model still emits (flagged, no columns) so
    // the graph can show the drift instead of silently dropping the junction.
    File::put(app_path('Models/Ghostable.php'), <<<'PHP'
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\MorphToMany;

    class Ghostable extends Model
    {
        protected $table = 'users';

        public function ghosts(): MorphToMany
        {
            return $this->morphToMany(Team::class, 'ghostable');
        }
    }
    PHP);
    require_once app_path('Models/Ghostable.php');
    // deliberately NO ghostables table

    $models = collect((new ApiController)->getModels());
    $pivot = $models->first(fn ($m) => str_contains($m->class, '.ghostables'));

    expect($pivot)->not->toBeNull()
        ->and($pivot->missing_table)->toBeTrue()
        ->and($pivot->attributes_count)->toBe(0);
});
