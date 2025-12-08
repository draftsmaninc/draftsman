<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('saves snapshot to default config path when no --path is provided', function () {
    // Arrange: point default snapshot path to a temp test directory
    $defaultDir = storage_path('app/draftsman/pest-default');
    if (File::exists($defaultDir)) {
        File::deleteDirectory($defaultDir);
    }
    config(['draftsman.snapshot_path' => $defaultDir]);

    // Act
    $exit = Artisan::call('draftsman:snapshot');
    expect($exit)->toBe(0);

    // Assert: a file with the expected prefix exists in the directory
    expect(File::exists($defaultDir))->toBeTrue();
    $files = collect(File::files($defaultDir))
        ->filter(fn ($f) => str_starts_with($f->getFilename(), 'draftsman_snapshot_') && str_ends_with($f->getFilename(), '.json'))
        ->values();
    expect($files->count())->toBeGreaterThan(0);

    $latest = $files->sortByDesc(fn ($f) => $f->getCTime())->first();
    $json = json_decode(File::get($latest->getPathname()), true);

    // Should be valid JSON object/array and always contain models key (may be empty array)
    expect($json)->toBeArray()
        ->and($json)->toHaveKey('models_data');
});

it('respects --exclude option and still includes models', function () {
    // Arrange: write to a deterministic temp file
    $dir = storage_path('app/draftsman/pest-exclude');
    if (File::exists($dir)) {
        File::deleteDirectory($dir);
    }
    File::makeDirectory($dir, 0755, true);
    $target = $dir.DIRECTORY_SEPARATOR.'exclude_test.json';
    if (File::exists($target)) {
        File::delete($target);
    }

    // Act: exclude config and about, composer left in
    $exit = Artisan::call('draftsman:snapshot', [
        '--exclude' => ['config', 'about'],
        '--path' => $target,
    ]);

    expect($exit)->toBe(0);
    expect(File::exists($target))->toBeTrue();

    $json = json_decode(File::get($target), true);

    // Assert: models present, excluded sections absent
    expect($json)->toBeArray()
        ->and($json)->toHaveKey('models_data')
        ->and($json)->not()->toHaveKey('config')
        ->and($json)->not()->toHaveKey('about');
});

it('supports --path as directory and as full filename', function () {
    // Directory case
    $dir = storage_path('app/draftsman/pest-path-dir');
    if (File::exists($dir)) {
        File::deleteDirectory($dir);
    }

    $exitDir = Artisan::call('draftsman:snapshot', [
        '--path' => $dir,
    ]);
    expect($exitDir)->toBe(0);
    expect(File::exists($dir))->toBeTrue();
    $dirFiles = collect(File::files($dir))
        ->filter(fn ($f) => str_starts_with($f->getFilename(), 'draftsman_snapshot_') && str_ends_with($f->getFilename(), '.json'))
        ->values();
    expect($dirFiles->count())->toBeGreaterThan(0);

    // Filename case
    $dir2 = storage_path('app/draftsman/pest-path-file');
    if (File::exists($dir2)) {
        File::deleteDirectory($dir2);
    }
    File::makeDirectory($dir2, 0755, true);
    $filePath = $dir2.DIRECTORY_SEPARATOR.'custom_snapshot.json';
    $exitFile = Artisan::call('draftsman:snapshot', [
        '--path' => $filePath,
    ]);
    expect($exitFile)->toBe(0);
    expect(File::exists($filePath))->toBeTrue();

    $json = json_decode(File::get($filePath), true);
    expect($json)->toBeArray()->and($json)->toHaveKey('models_list');
});

it('gets expected models_data schema', function () {
    // Arrange: write to a deterministic temp file
    $dir = storage_path('app/draftsman/pest-models-data');
    if (File::exists($dir)) {
        File::deleteDirectory($dir);
    }
    File::makeDirectory($dir, 0755, true);
    $filePath = $dir.DIRECTORY_SEPARATOR.'schema_models_data.json';
    if (File::exists($filePath)) {
        File::delete($filePath);
    }

    // Act
    $exit = Artisan::call('draftsman:snapshot', [
        '--path' => $filePath,
        // Include all optional sections by default; models_data is always included
    ]);

    expect($exit)->toBe(0);
    expect(File::exists($filePath))->toBeTrue();

    $json = json_decode(File::get($filePath), true);

    // Assert: models_data exists and is an array
    expect($json)->toBeArray()->and($json)->toHaveKey('models_data');
    expect($json['models_data'])->toBeArray();

    // If there are models, validate minimal structure of the first one
    if (count($json['models_data']) > 0) {
        $first = $json['models_data'][0];
        expect($first)->toBeArray()
            ->and($first)->toHaveKey('class')
            ->and($first['class'])->toBeString()
            ->and($first)->toHaveKey('attributes')
            ->and($first['attributes'])->toBeArray()
            ->and($first)->toHaveKey('relations')
            ->and($first['relations'])->toBeArray();

        // If attributes exist, check minimal attribute schema
        if (count($first['attributes']) > 0) {
            $attr = $first['attributes'][0];
            expect($attr)->toBeArray()
                ->and($attr)->toHaveKey('name')
                ->and($attr['name'])->toBeString();
            // type is commonly provided but keep it minimal
            if (array_key_exists('type', $attr)) {
                expect($attr['type'])->toBeString();
            }
        }
    }
});

it('gets expected models_list schema', function () {
    // Arrange
    $dir = storage_path('app/draftsman/pest-models-list');
    if (File::exists($dir)) {
        File::deleteDirectory($dir);
    }
    File::makeDirectory($dir, 0755, true);
    $filePath = $dir.DIRECTORY_SEPARATOR.'schema_models_list.json';
    if (File::exists($filePath)) {
        File::delete($filePath);
    }

    // Act
    $exit = Artisan::call('draftsman:snapshot', [
        '--path' => $filePath,
    ]);

    expect($exit)->toBe(0);
    expect(File::exists($filePath))->toBeTrue();

    $json = json_decode(File::get($filePath), true);

    // Assert minimal schema
    expect($json)->toBeArray()->and($json)->toHaveKey('models_list');
    expect($json['models_list'])->toBeArray();
    // If any models are present, they should be strings
    foreach ($json['models_list'] as $modelClass) {
        expect($modelClass)->toBeString();
    }
});

it('gets expected artisan about schema', function () {
    // Arrange
    $dir = storage_path('app/draftsman/pest-about');
    if (File::exists($dir)) {
        File::deleteDirectory($dir);
    }
    File::makeDirectory($dir, 0755, true);
    $filePath = $dir.DIRECTORY_SEPARATOR.'schema_about.json';
    if (File::exists($filePath)) {
        File::delete($filePath);
    }

    // Act
    $exit = Artisan::call('draftsman:snapshot', [
        '--path' => $filePath,
    ]);

    expect($exit)->toBe(0);
    expect(File::exists($filePath))->toBeTrue();

    $json = json_decode(File::get($filePath), true);

    // Assert minimal schema
    expect($json)->toBeArray()->and($json)->toHaveKey('about');
    expect($json['about'])->toBeArray();
    // Should be either empty array or contain at least one section (key => values)
    if (count($json['about']) > 0) {
        $firstSection = reset($json['about']);
        // Each section is typically an array/object of details
        expect($firstSection)->toBeArray();
    }
});

it('gets expected composer.json schema', function () {
    // Arrange
    $dir = storage_path('app/draftsman/pest-composer');
    if (File::exists($dir)) {
        File::deleteDirectory($dir);
    }
    File::makeDirectory($dir, 0755, true);
    $filePath = $dir.DIRECTORY_SEPARATOR.'schema_composer.json';
    if (File::exists($filePath)) {
        File::delete($filePath);
    }

    // Act
    $exit = Artisan::call('draftsman:snapshot', [
        '--path' => $filePath,
    ]);

    expect($exit)->toBe(0);
    expect(File::exists($filePath))->toBeTrue();

    $json = json_decode(File::get($filePath), true);

    // Assert minimal schema
    expect($json)->toBeArray()->and($json)->toHaveKey('composer');
    expect($json['composer'])->toBeArray();
    // Common, high‑value keys
    if (array_key_exists('name', $json['composer'])) {
        expect($json['composer']['name'])->toBeString();
    }
    if (array_key_exists('require', $json['composer'])) {
        expect($json['composer']['require'])->toBeArray();
    }
});
