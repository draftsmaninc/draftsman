<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('saves snapshot to default config path when no --path is provided', function () {
    // Arrange: point default snapshot path to a temp test directory
    $defaultDir = storage_path('draftsman/pest-default');
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
    $dir = storage_path('draftsman/pest-exclude');
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
    $dir = storage_path('draftsman/pest-path-dir');
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
    $dir2 = storage_path('draftsman/pest-path-file');
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
    $dir = storage_path('draftsman/pest-models-data');
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
    $dir = storage_path('draftsman/pest-models-list');
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
    $dir = storage_path('draftsman/pest-about');
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
    $dir = storage_path('draftsman/pest-composer');
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

    // Assert minimal schema depending on whether composer.json exists
    $composerPath = base_path('composer.json');
    expect($json)->toBeArray();
    if (File::exists($composerPath)) {
        expect($json)->toHaveKey('composer');
        expect($json['composer'])->toBeArray();
        // Common, high‑value keys
        if (array_key_exists('name', $json['composer'])) {
            expect($json['composer']['name'])->toBeString();
        }
        if (array_key_exists('require', $json['composer'])) {
            expect($json['composer']['require'])->toBeArray();
        }
    } else {
        expect($json)->toHaveKey('composer_error');
    }
});

it('gets expected package.json schema', function () {
    // Arrange
    $dir = storage_path('draftsman/pest-package');
    if (File::exists($dir)) {
        File::deleteDirectory($dir);
    }
    File::makeDirectory($dir, 0755, true);
    $filePath = $dir.DIRECTORY_SEPARATOR.'schema_package.json';
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

    // Assert minimal schema depending on whether package.json exists
    $packagePath = base_path('package.json');
    expect($json)->toBeArray();
    if (File::exists($packagePath)) {
        expect($json)->toHaveKey('package');
        expect($json['package'])->toBeArray();
        // Common, high‑value keys
        if (array_key_exists('name', $json['package'])) {
            expect($json['package']['name'])->toBeString();
        }
        if (array_key_exists('dependencies', $json['package'])) {
            expect($json['package']['dependencies'])->toBeArray();
        }
    } else {
        expect($json)->toHaveKey('package_error');
    }
});

it('gets expected .env schema and masking behavior', function () {
    // Arrange: backup current .env and write a controlled one
    $envPath = base_path('.env');
    $original = File::exists($envPath) ? File::get($envPath) : null;

    $dir = storage_path('draftsman/pest-env');
    if (File::exists($dir)) {
        File::deleteDirectory($dir);
    }
    File::makeDirectory($dir, 0755, true);
    $filePath = $dir.DIRECTORY_SEPARATOR.'schema_env.json';
    if (File::exists($filePath)) {
        File::delete($filePath);
    }

    try {
        $envContents = <<<'ENV'
        # Comment line
        OTHER_VAR=should_not_be_included
        export DRAFTSMAN_VISIBLE=value123
        DRAFTSMAN_SECRET_KEY=supersecret
        DRAFTSMAN_SECRET=reallysecret
        DRAFTSMAN_PASSWORD=should_mask
        DRAFTSMAN_PASS=also_mask
        DRAFTSMAN_NORMAL=ok

        INVALID_LINE_WITHOUT_EQUALS
        ENV;

        File::put($envPath, $envContents);

        // Act
        $exit = Artisan::call('draftsman:snapshot', [
            '--path' => $filePath,
        ]);

        expect($exit)->toBe(0);
        expect(File::exists($filePath))->toBeTrue();

        $json = json_decode(File::get($filePath), true);

        // Assert minimal schema and masking
        expect($json)->toBeArray()->and($json)->toHaveKey('env');
        expect($json['env'])->toBeArray();
        expect($json['env'])->toHaveKey('DRAFTSMAN_VISIBLE');
        expect($json['env']['DRAFTSMAN_VISIBLE'])->toBe('value123');

        // Sensitive suffixes should be masked to '...'
        expect($json['env'])->toHaveKey('DRAFTSMAN_SECRET_KEY');
        expect($json['env']['DRAFTSMAN_SECRET_KEY'])->toBe('...');
        expect($json['env'])->toHaveKey('DRAFTSMAN_SECRET');
        expect($json['env']['DRAFTSMAN_SECRET'])->toBe('...');
        expect($json['env'])->toHaveKey('DRAFTSMAN_PASSWORD');
        expect($json['env']['DRAFTSMAN_PASSWORD'])->toBe('...');
        expect($json['env'])->toHaveKey('DRAFTSMAN_PASS');
        expect($json['env']['DRAFTSMAN_PASS'])->toBe('...');

        // Non DRAFTSMAN_ vars should not be included
        expect($json['env'])->not()->toHaveKey('OTHER_VAR');

        // Normal key should pass through
        expect($json['env'])->toHaveKey('DRAFTSMAN_NORMAL');
        expect($json['env']['DRAFTSMAN_NORMAL'])->toBe('ok');
    } finally {
        // Restore original .env
        if ($original === null) {
            if (File::exists($envPath)) {
                File::delete($envPath);
            }
        } else {
            File::put($envPath, $original);
        }
    }
});

it('creates .gitignore in storage/draftsman when saving a snapshot if missing', function () {
    $draftsmanDir = storage_path('draftsman');
    $gitignorePath = $draftsmanDir.DIRECTORY_SEPARATOR.'.gitignore';

    // Ensure draftsman directory exists
    if (! File::exists($draftsmanDir)) {
        File::makeDirectory($draftsmanDir, 0755, true);
    }

    // Backup any existing .gitignore then remove it to simulate the "missing" condition
    $original = null;
    if (File::exists($gitignorePath)) {
        $original = File::get($gitignorePath);
        File::delete($gitignorePath);
    }

    // Use a temp snapshots directory to avoid polluting defaults
    $tempSnapshotsDir = storage_path('testing-snapshots');
    if (File::exists($tempSnapshotsDir)) {
        File::deleteDirectory($tempSnapshotsDir);
    }

    try {
        // Run the snapshot command
        $exit = Artisan::call('draftsman:snapshot', [
            '--path' => $tempSnapshotsDir,
        ]);

        expect($exit)->toBe(0);

        // .gitignore should be created with expected contents
        expect(File::exists($gitignorePath))->toBeTrue();
        $contents = File::get($gitignorePath);
        expect($contents)->toBe("*\n!.gitignore\n");

        // Also confirm a snapshot file was written to the provided directory (sanity check)
        expect(File::exists($tempSnapshotsDir))->toBeTrue();
        $files = collect(File::files($tempSnapshotsDir))
            ->map(fn ($f) => $f->getFilename())
            ->filter(fn ($name) => str_ends_with(strtolower($name), '.json'));
        expect($files->isEmpty())->toBeFalse();
    } finally {
        // Restore original .gitignore state
        if ($original !== null) {
            File::put($gitignorePath, $original);
        } else {
            if (File::exists($gitignorePath)) {
                File::delete($gitignorePath);
            }
        }

        // Clean up temporary snapshots directory
        if (File::exists($tempSnapshotsDir)) {
            File::deleteDirectory($tempSnapshotsDir);
        }
    }
});
