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
    | Package Settings
    |--------------------------------------------------------------------------
    |
    | Where Draftsman finds and keeps things in the host app. Every key here
    | has a consumer — runtime capabilities (like which render formats work)
    | are reported by the config API endpoint, not configured.
    |
    */
    'package' => [
        // Path (relative to project root) where models live.
        'models_path' => env('DRAFTSMAN_MODELS_PATH', app_path('Models')),
        // Path (relative to project storage) where snapshots are saved.
        'snapshot_path' => env('DRAFTSMAN_SNAPSHOT_PATH', storage_path('draftsman/snapshots/')),
        // Path where graph documents are saved. Lives in the project root
        // (not storage/) so graphs are version-controlled with the code,
        // mirroring Laravel Blueprint's draft-in-repo pattern.
        'graphs_path' => env('DRAFTSMAN_GRAPHS_PATH', base_path('draftsman')),
        // Explicit node/npm binaries for Browsershot renders. Web servers
        // often run with a minimal PATH that misses version-managed node
        // (nvm, Herd, volta) — set these when render/{slug}?format=… works
        // from the CLI but 500s over HTTP. Null = Browsershot's defaults.
        'node_binary' => env('DRAFTSMAN_NODE_BINARY'),
        'npm_binary' => env('DRAFTSMAN_NPM_BINARY'),
    ],
];
```

## Usage

Open `/draftsman` in your app (e.g. `http://your-app.test/draftsman`). The
bundled frontend reads your Eloquent models — including vendor models and
pivot tables your relations reach — and lays them out as an interactive
diagram. Graphs you save land as JSON documents in `graphs_path`
(`draftsman/` by default), so they are version-controlled with your code.

For a support/diagnostic dump of what Draftsman sees (models, config, about
report), there is also:

```bash
php artisan draftsman:snapshot            # writes to snapshot_path
php artisan draftsman:snapshot --path=... # or a specific file/directory
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
cd ../..
```


## Testing

The test suite runs on Pest 4 against Laravel 12 and 13.

```bash
composer test
```

## Built With

* [Spatie's Package Skeleton](https://github.com/spatie/package-skeleton-laravel)
* [Spatie's Package Tools](https://github.com/spatie/laravel-package-tools)
* [AlpineFlow](https://github.com/getartisanflow/alpineflow)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ron Northrip](https://github.com/ronnorthrip)
- [Zac Hiller](https://github.com/zachiler)
- [All Contributors](../../contributors)

## License

The Draftsman Package is Copyright (c) 2025 by Draftsman Incorporated.
Please see the [License File](LICENSE.md) for more information.
