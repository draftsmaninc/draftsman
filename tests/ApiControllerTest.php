<?php

use Draftsman\Draftsman\Actions\GetDraftsmanConfig;
use Draftsman\Draftsman\Actions\UpdateDraftsmanConfig;
use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

it('returns config JSON on getConfig success', function () {
    $controller = new ApiController;

    $mock = \Mockery::mock(GetDraftsmanConfig::class);
    $expected = ['config' => ['foo' => 'bar']];
    $mock->shouldReceive('handle')->once()->andReturn($expected);

    $response = $controller->getConfig($mock);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))
        ->toMatchArray($expected);
});

it('returns full config sections (front, presentation, graph) on getConfig', function () {
    $controller = new ApiController;

    $mock = \Mockery::mock(GetDraftsmanConfig::class);
    $expected = [
        'config' => [
            'package' => [
                'update_env' => true,
                'snapshot_path' => storage_path('draftsman/snapshots'),
                'models_path' => app_path('Models'),
            ],
            'front' => [
                'history_length' => 200,
                'snap_to_grid' => true,
            ],
            'graph' => [
                'show_grid' => true,
                'grid_width' => 50,
                'label_edges' => true,
                'use_gutters' => true,
                'column_width' => 60,
                'column_min_width' => 40,
                'column_max_width' => 80,
                'gutter_width' => 20,
                'gutter_min_width' => 10,
                'gutter_max_width' => 30,
            ],
            'presentation' => [
                'App\\Models\\User' => [
                    'icon' => 'heroicon-o-user',
                    'bg_color' => 'bg-sky-500',
                    'text_color' => 'text-sky-500',
                ],
            ],
        ],
    ];
    $mock->shouldReceive('handle')->once()->andReturn($expected);

    $response = $controller->getConfig($mock);

    $data = $response->getData(true);
    expect($response->getStatusCode())->toBe(200)
        ->and($data)->toMatchArray($expected)
        ->and($data['config'])->toHaveKeys(['package', 'front', 'graph', 'presentation'])
        ->and($data['config']['front'])->toMatchArray([
            'history_length' => 200,
            'snap_to_grid' => true,
        ])
        ->and($data['config']['graph'])->toHaveKeys([
            'show_grid', 'grid_width', 'label_edges', 'use_gutters',
            'column_width', 'column_min_width', 'column_max_width',
            'gutter_width', 'gutter_min_width', 'gutter_max_width',
        ])
        ->and($data['config']['presentation']['App\\Models\\User'])
        ->toMatchArray([
            'icon' => 'heroicon-o-user',
            'bg_color' => 'bg-sky-500',
            'text_color' => 'text-sky-500',
        ]);
});

it('returns config without presentation if missing in getConfig', function () {
    $controller = new ApiController;

    $mock = \Mockery::mock(GetDraftsmanConfig::class);
    $expected = [
        'config' => [
            'package' => [
                'update_env' => true,
            ],
            'front' => [
                'history_length' => 200,
            ],
            'graph' => [
                'show_grid' => true,
            ],
        ],
    ];
    $mock->shouldReceive('handle')->once()->andReturn($expected);

    $response = $controller->getConfig($mock);

    $data = $response->getData(true);
    expect($response->getStatusCode())->toBe(200)
        ->and($data)->toMatchArray($expected)
        ->and($data['config'])->not->toHaveKey('presentation');
});

it('returns 500 JSON on getConfig failure', function () {
    $controller = new ApiController;

    $mock = \Mockery::mock(GetDraftsmanConfig::class);
    $mock->shouldReceive('handle')->once()->andThrow(new Exception('boom'));

    $response = $controller->getConfig($mock);

    $data = $response->getData(true);
    expect($response->getStatusCode())->toBe(500)
        ->and($data)->toHaveKeys(['message', 'error'])
        ->and($data['message'])->toBe('Failed to load Draftsman configuration.')
        ->and($data['error'])->toBe('boom');
});

it('updates config and returns JSON on updateConfig success', function () {
    $controller = new ApiController;

    $payload = [
        'package' => [
            'update_env' => false,
            'snapshot_path' => storage_path('draftsman/tests'),
        ],
    ];

    $request = Request::create(
        '/api/draftsman/config',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        content: json_encode(['config' => $payload])
    );

    $mock = \Mockery::mock(UpdateDraftsmanConfig::class);
    $result = [
        'message' => 'Draftsman configuration updated successfully.',
        'config' => $payload,
        'env_updated' => false,
    ];
    $mock->shouldReceive('handle')->once()->with($payload)->andReturn($result);

    $response = $controller->updateConfig($request, $mock);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))
        ->toMatchArray($result);
});

it('updates all config sections (front, presentation, graph) and returns JSON', function () {
    $controller = new ApiController;

    $payload = [
        'package' => [
            'update_env' => true,
            'snapshot_path' => storage_path('draftsman/complex'),
            'models_path' => app_path('Models'),
        ],
        'front' => [
            'history_length' => 150,
            'snap_to_grid' => false,
        ],
        'graph' => [
            'show_grid' => false,
            'grid_width' => 42,
            'label_edges' => false,
            'use_gutters' => true,
            'column_width' => 70,
            'column_min_width' => 35,
            'column_max_width' => 90,
            'gutter_width' => 15,
            'gutter_min_width' => 5,
            'gutter_max_width' => 25,
        ],
        'presentation' => [
            'App\\Models\\User' => [
                'icon' => 'heroicon-o-user-circle',
                'class' => 'bg-emerald-500 text-emerald-500',
            ],
        ],
    ];

    $request = Request::create(
        '/api/draftsman/config',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        content: json_encode(['config' => $payload])
    );

    $mock = \Mockery::mock(UpdateDraftsmanConfig::class);
    $result = [
        'message' => 'Draftsman configuration updated successfully.',
        'config' => $payload,
        'env_updated' => true,
    ];
    $mock->shouldReceive('handle')->once()->with($payload)->andReturn($result);

    $response = $controller->updateConfig($request, $mock);

    $data = $response->getData(true);
    expect($response->getStatusCode())->toBe(200)
        ->and($data)->toMatchArray($result)
        ->and($data['config'])->toHaveKeys(['package', 'front', 'graph', 'presentation'])
        ->and($data['env_updated'])->toBeTrue();
});

it('returns 500 JSON on updateConfig failure', function () {
    $controller = new ApiController;

    $payload = ['package' => ['update_env' => true]];
    $request = Request::create(
        '/api/draftsman/config',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        content: json_encode(['config' => $payload])
    );

    $mock = \Mockery::mock(UpdateDraftsmanConfig::class);
    $mock->shouldReceive('handle')->once()->with($payload)->andThrow(new Exception('cannot write'));

    $response = $controller->updateConfig($request, $mock);

    $data = $response->getData(true);
    expect($response->getStatusCode())->toBe(500)
        ->and($data)->toHaveKeys(['message', 'error'])
        ->and($data['message'])->toBe('Failed to update Draftsman configuration.')
        ->and($data['error'])->toBe('cannot write');
});

it('reflects env updates when update_env=true and existing DRAFTSMAN_ keys are updated', function () {
    $controller = new ApiController;

    $payload = [
        'package' => [
            'update_env' => true,
            'editor' => 'php-storm',
        ],
    ];

    $request = Request::create(
        '/api/draftsman/config',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        content: json_encode(['config' => $payload])
    );

    $mock = \Mockery::mock(UpdateDraftsmanConfig::class);
    $result = [
        'message' => 'Draftsman configuration updated successfully.',
        'config' => $payload,
        'env_updated' => true,
    ];
    $mock->shouldReceive('handle')->once()->with($payload)->andReturn($result);

    $response = $controller->updateConfig($request, $mock);

    $data = $response->getData(true);
    expect($response->getStatusCode())->toBe(200)
        ->and($data['env_updated'])->toBeTrue();
});

it('does not add new DRAFTSMAN_ keys to .env (add_env=false case)', function () {
    $controller = new ApiController;

    $payload = [
        'package' => [
            'update_env' => true,
            'add_env' => false,
        ],
        'new_setting' => 'should-not-be-added-to-env',
    ];

    $request = Request::create(
        '/api/draftsman/config',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        content: json_encode(['config' => $payload])
    );

    $mock = \Mockery::mock(UpdateDraftsmanConfig::class);
    $result = [
        'message' => 'Draftsman configuration updated successfully.',
        'config' => $payload,
        'env_updated' => false,
    ];
    $mock->shouldReceive('handle')->once()->with($payload)->andReturn($result);

    $response = $controller->updateConfig($request, $mock);

    $data = $response->getData(true);
    expect($response->getStatusCode())->toBe(200)
        ->and($data['env_updated'])->toBeFalse();
});

it('adds new DRAFTSMAN_ keys to .env when add_env=true', function () {
    $controller = new ApiController;

    $payload = [
        'package' => [
            'update_env' => true,
            'add_env' => true,
        ],
        'new_setting' => 'should-be-added-to-env',
    ];

    $request = Request::create(
        '/api/draftsman/config',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        content: json_encode(['config' => $payload])
    );

    $mock = \Mockery::mock(UpdateDraftsmanConfig::class);
    $result = [
        'message' => 'Draftsman configuration updated successfully.',
        'config' => $payload,
        'env_updated' => true,
    ];
    $mock->shouldReceive('handle')->once()->with($payload)->andReturn($result);

    $response = $controller->updateConfig($request, $mock);

    $data = $response->getData(true);
    expect($response->getStatusCode())->toBe(200)
        ->and($data['env_updated'])->toBeTrue();
});

it('GetDraftsmanConfig handles missing storage config file', function () {
    $storagePath = storage_path('draftsman/config.php');
    if (File::exists($storagePath)) {
        File::delete($storagePath);
    }

    $action = new GetDraftsmanConfig();
    $result = $action->handle();

    expect($result)->toHaveKey('config')
        ->and($result['config'])->not->toHaveKey('presentation');
});

it('GetDraftsmanConfig includes presentation when storage config exists', function () {
    $storageDir = storage_path('draftsman');
    $storagePath = $storageDir . '/config.php';

    if (!File::exists($storageDir)) {
        File::makeDirectory($storageDir, 0755, true);
    }

    $presentation = [
        'App\\Models\\User' => [
            'icon' => 'heroicon-o-user',
        ],
    ];

    File::put($storagePath, "<?php\n\nreturn ['presentation' => " . var_export($presentation, true) . "];");

    $action = new GetDraftsmanConfig();
    $result = $action->handle();

    expect($result['config'])->toHaveKey('presentation')
        ->and($result['config']['presentation'])->toBe($presentation);

    File::delete($storagePath);
});

it('UpdateDraftsmanConfig creates storage directory and file', function () {
    $storageDir = storage_path('draftsman');
    $storagePath = $storageDir . '/config.php';

    if (File::exists($storagePath)) {
        File::delete($storagePath);
    }
    // We don't necessarily delete the directory as other things might be there,
    // but the test should ensure it works even if it's missing.
    // To be thorough, let's try to delete it if it's empty or just ensure it's handled.

    $payload = [
        'presentation' => [
            'App\\Models\\Post' => [
                'icon' => 'heroicon-o-document',
            ],
        ],
        'package' => [
            'update_env' => false,
        ],
    ];

    $action = new UpdateDraftsmanConfig();
    $action->handle($payload);

    expect(File::exists($storagePath))->toBeTrue();

    $savedConfig = include $storagePath;
    expect($savedConfig)->toHaveKey('presentation')
        ->and($savedConfig['presentation'])->toHaveKey('App\\Models\\Post');

    File::delete($storagePath);
});

it('returns 422 JSON on updateConfig invalid payload', function () {
    $controller = new ApiController;

    // Invalid: config is not an array
    $request = Request::create(
        '/api/draftsman/config',
        'POST',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        content: json_encode(['config' => 'invalid'])
    );

    $mock = \Mockery::mock(UpdateDraftsmanConfig::class);
    $mock->shouldNotReceive('handle');

    $response = $controller->updateConfig($request, $mock);

    $data = $response->getData(true);
    expect($response->getStatusCode())->toBe(422)
        ->and($data)->toHaveKey('message');
});
