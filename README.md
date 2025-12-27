# Draftsman for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/draftsmaninc/draftsman.svg?style=flat-square)](https://packagist.org/packages/draftsmaninc/draftsman)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/draftsmaninc/draftsman/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/draftsmaninc/draftsman/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/draftsmaninc/draftsman/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/draftsmaninc/draftsman/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/draftsmaninc/draftsman.svg?style=flat-square)](https://packagist.org/packages/draftsmaninc/draftsman)

A graphical tool that diagrams and edits Laravel eloquent models.

## Installation

Because this package hasn't been published yet on Packagist you'll 
need to start by adding it to your repositories block in composer.json.
If your composer.json file doesn't have a repositories block add it to the end.

```json
"repositories" : {
    ...

    "draftsman" : {
        "type": "vcs",
        "url": "https://github.com/draftsmaninc/draftsman.git"
    }
}
```

You can now install the package via composer:

```bash
composer require --dev draftsmaninc/draftsman:dev-main
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="draftsman-config"
```

This is the contents of the published config file:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Draftsman Package
    |--------------------------------------------------------------------------
    |
    | These options configure the global environment for your
    | Draftsman installation. You may specify the path to
    | your models, choose a preferred code editor, and
    | customize the storage location for snapshots.
    |
    */
    'config' => [
        // Preferred editor executable; falls back to env or sensible defaults
        'editor' => env('DRAFTSMAN_EDITOR', 'php-storm'),
        // Additional flags to pass to the editor
        'editor_flags' => env('DRAFTSMAN_EDITOR_FLAGS', ['flag1', 'flag2', 'flag3']),
        // Browser to open links with; 'system' = OS default
        'browser' => env('DRAFTSMAN_BROWSER', 'chrome'),
        // Path (relative to project root) where Draftsman is installed
        'draftsman_path' => env('DRAFTSMAN_PATH', base_path('vendor/draftsmaninc/draftsman')),
        // Base Eloquent model class namespace
        'model_class' => env('DRAFTSMAN_MODEL_CLASS', 'eloquent'),
        // Path (relative to project root) where models live
        'models_path' => env('DRAFTSMAN_MODELS_PATH', app_path('Models')),
        // Path (relative to project storage) where snapshots are saved
        'snapshot_path' => env('DRAFTSMAN_SNAPSHOT_PATH', storage_path('draftsman/snapshots/')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how the Draftsman user interface
    | behaves during your workflow session. You can define
    | the number of undo/redo steps to retain in history
    | and toggle the automatic snapping of elements
    | to the canvas grid for precise alignment.
    |
    */
    'front' => [
        'history_length' => env('DRAFTSMAN_FRONT_HISTORY_LENGTH', 300),
        'snap_to_grid' => env('DRAFTSMAN_FRONT_SNAP_TO_GRID', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph Config
    |--------------------------------------------------------------------------
    |
    | This section manages all visual and layout properties for the
    | generated graph display. You can control the visibility and
    | spacing of the grid, including column and gutter widths
    | to arrange model elements. You also can choose whether
    | to label the relationship edges shown in the graph.
    |
    */
    'graph' => [
        'show_grid' => env('DRAFTSMAN_GRAPH_SHOW_GRID', true),
        'grid_width' => env('DRAFTSMAN_GRAPH_GRID_WIDTH', 50),
        'label_edges' => env('DRAFTSMAN_GRAPH_LABEL_EDGES', true),
        'use_gutters' => env('DRAFTSMAN_GRAPH_USE_GUTTERS', true),
        'column_width' => env('DRAFTSMAN_GRAPH_COLUMN_WIDTH', 50),
        'column_min_width' => env('DRAFTSMAN_GRAPH_COLUMN_MIN_WIDTH', 50),
        'column_max_width' => env('DRAFTSMAN_GRAPH_COLUMN_MAX_WIDTH', 50),
        'gutter_width' => env('DRAFTSMAN_GRAPH_GUTTER_WIDTH', 50),
        'gutter_min_width' => env('DRAFTSMAN_GRAPH_GUTTER_MIN_WIDTH', 50),
        'gutter_max_width' => env('DRAFTSMAN_GRAPH_GUTTER_TO_MAX', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Presentation
    |--------------------------------------------------------------------------
    |
    | This section manages how specific Eloquent models are displayed
    | within Draftsman's graph interface. Customize the options of
    | each model, defining its unique icon, and setting the text
    | colors to distinguish it from others, enhancing clarity.
    |
    */
    'presentation' => [
        // Examples:
        App\Models\User::class => [
            'icon' => 'heroicon-o-user',
            'bg_color' => 'bg-sky-400',
            'text_color' => 'text-slate-100',
        ],
    ],
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="draftsman-views"
```

## Usage

```bash
php artisan draftsman:launch
```

## Local Dev

Details coming

## Testing

```bash
composer test
```

## Built With

* [Spatie's Package Skeleton](https://github.com/spatie/package-skeleton-laravel)
* [Spatie's Package Tools](https://github.com/spatie/laravel-package-tools)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ron Northrip](https://github.com/ronnorthrip)
- [All Contributors](../../contributors)

## License

The Draftsman Package is Copyright (c) 2025 by Draftsman Incorporated.
Please see the [License File](LICENSE.md) for more information.
