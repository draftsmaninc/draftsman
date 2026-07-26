<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Contract: relations pointing at models OUTSIDE app_path() (package models —
 * Spatie Activity/Media, Cashier Subscription, DatabaseNotification…) don't
 * just dangle. getModels() tracks every relation target it hasn't scanned,
 * introspects it too, and appends it to the payload flagged `vendor: true` —
 * transitively, since vendor models relate onward to more vendor models
 * (Subscription -> SubscriptionItem). A mature Laravel app has dozens of
 * these; without them the graph silently drops real edges.
 *
 * The fixture chain: app-side Gadget -> VendorLib\Widget -> VendorLib\WidgetPart,
 * where the VendorLib classes live in tests/Fixtures/Vendor (outside the
 * skeleton's app path) so only the tracker can find them.
 */
beforeEach(function () {
    installFixtureModels();

    require_once __DIR__.'/Fixtures/Vendor/Widget.php';
    require_once __DIR__.'/Fixtures/Vendor/WidgetPart.php';

    File::put(app_path('Models/Gadget.php'), <<<'PHP'
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\HasMany;
    use VendorLib\Widget;

    class Gadget extends Model
    {
        public function widgets(): HasMany
        {
            return $this->hasMany(Widget::class, 'gadget_id');
        }
    }
    PHP);
    require_once app_path('Models/Gadget.php');

    Schema::create('gadgets', function ($table) {
        $table->id();
        $table->timestamps();
    });
    Schema::create('widgets', function ($table) {
        $table->id();
        $table->foreignId('gadget_id');
        $table->timestamps();
    });
    Schema::create('widget_parts', function ($table) {
        $table->id();
        $table->foreignId('widget_id');
        $table->timestamps();
    });
});

afterEach(function () {
    File::deleteDirectory(app_path('Models'));
});

it('appends vendor relation targets to the models payload, flagged vendor', function () {
    $models = collect((new ApiController)->getModels())->keyBy('class');

    expect($models)->toHaveKey('VendorLib\\Widget')
        ->and($models['VendorLib\\Widget']->vendor)->toBeTrue();
});

it('follows vendor relations transitively', function () {
    // WidgetPart is only reachable THROUGH Widget — one un-followed hop and
    // it's missing.
    $models = collect((new ApiController)->getModels())->keyBy('class');

    expect($models)->toHaveKey('VendorLib\\WidgetPart')
        ->and($models['VendorLib\\WidgetPart']->vendor)->toBeTrue();
});

it('does not flag app models and does not duplicate vendor entries', function () {
    $models = collect((new ApiController)->getModels());

    // Widget is referenced twice (Gadget.widgets and WidgetPart.widget) but
    // must appear once.
    expect($models->where('class', 'VendorLib\\Widget'))->toHaveCount(1)
        ->and($models->keyBy('class')['App\\Models\\Gadget'] ?? null)->not->toBeNull()
        ->and(property_exists($models->keyBy('class')['App\\Models\\Gadget'], 'vendor'))->toBeFalse();
});

it('introspects vendor models like any other — relations included', function () {
    $models = collect((new ApiController)->getModels())->keyBy('class');
    $widget = $models['VendorLib\\Widget'];

    $parts = collect($widget->relations)->firstWhere('name', 'parts');
    expect($parts)->not->toBeNull()
        ->and($parts->framework_type)->toBe('HasMany')
        ->and($parts->to)->toBe('VendorLib\\WidgetPart');
});

it('survives a relation method that throws, keeping the rest of the model', function () {
    // Widget::proParts() throws on invocation (the medialibrary-pro pattern).
    // The model must still emit with its healthy relations; the throwing one
    // is dropped rather than 500ing the whole payload.
    $models = collect((new ApiController)->getModels())->keyBy('class');

    expect($models)->toHaveKey('VendorLib\\Widget');
    $names = collect($models['VendorLib\\Widget']->relations)->pluck('name');
    expect($names)->toContain('parts')
        ->and($names)->not->toContain('proParts');
});
