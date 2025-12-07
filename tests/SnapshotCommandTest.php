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
        ->and($json)->toHaveKey('models');
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
        ->and($json)->toHaveKey('models')
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
    expect($json)->toBeArray()->and($json)->toHaveKey('models');
});
