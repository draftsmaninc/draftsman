# Draftsman for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/draftsmaninc/draftsman.svg?style=flat-square)](https://packagist.org/packages/draftsmaninc/draftsman)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/draftsmaninc/draftsman/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/draftsmaninc/draftsman/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/draftsmaninc/draftsman/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/draftsmaninc/draftsman/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/draftsmaninc/draftsman.svg?style=flat-square)](https://packagist.org/packages/draftsmaninc/draftsman)

A graphical tool that diagrams and edits Laravel eloquent models.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

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

## Rendering graphs

Any graph saved from the Draftsman UI (stored as `<graphs_path>/<slug>.json`,
`draftsman/` by default) can be rendered to a self-contained file:

```bash
php artisan draftsman:render teams                 # draftsman/teams.html
php artisan draftsman:render teams --format=png    # needs Browsershot, see below
php artisan draftsman:render teams --path=docs/erd.html
```

| Format | Needs | Notes |
| ------ | ----- | ----- |
| `html` | nothing | Static, no JavaScript — safe to commit or serve anywhere |
| `png` / `jpeg` / `pdf` | [Browsershot](https://github.com/spatie/browsershot) | Headless-Chrome capture of the same page |
| `svg` | — | Not available yet |

The `html` format works everywhere with zero extra dependencies. For image and
PDF output, opt in to Browsershot in the host app:

```bash
composer require spatie/browsershot
npm install puppeteer
npx puppeteer browsers install chrome-headless-shell   # the browser it drives
```

### Keeping a rendered diagram in CI

Rendering the committed graph document on every push keeps a diagram in your
repo (or build artifacts) that never drifts from the saved graph:

```yaml
# .github/workflows/erd.yml
name: Render ERD
on:
  push:
    paths: ['draftsman/*.json']

jobs:
  render:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist
      - run: php artisan draftsman:render teams --path=docs/erd.html
      - uses: stefanzweifel/git-auto-commit-action@v5
        with: { commit_message: 'Update rendered ERD' }
```

For png/pdf in CI, add Browsershot and Puppeteer before the render step:

```yaml
      - run: composer require spatie/browsershot
      - run: npm install puppeteer
      - run: npx puppeteer browsers install chrome-headless-shell
      - run: php artisan draftsman:render teams --format=png --path=docs/erd.png
```

## Local Dev

Keep both the draftsman repo and the dev site in the same directory.
Then install draftsman normally in the dev site.
Then rename or delete the 'vendor/draftsmaninc/draftsman' directory.
Then use a symlink to replace it with a connection to your local repo.

```bash
cd vendor/draftsmaninc
mv draftsman xxxdraftsman
ln -s ../../../draftsman
```


## Testing

The test suite runs on Pest 4 against Laravel 12 and 13.

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
