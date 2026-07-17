# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Draftsman is a Laravel package (`draftsmaninc/draftsman`) that diagrams and edits Eloquent models in a host Laravel application. It is built on the spatie/laravel-package-tools skeleton and has no runnable app of its own — development and testing happen through Orchestra Testbench, which simulates a host Laravel app.

## Commands

```bash
composer test                 # run the Pest test suite (vendor/bin/pest)
vendor/bin/pest tests/RoutesTest.php          # run one test file
vendor/bin/pest --filter "defaults to the layout"  # run a single test by name
composer test-coverage        # tests with coverage
composer format               # fix code style with Laravel Pint
composer analyse              # PHPStan (vendor/bin/phpstan analyse)
```

Requires PHP ^8.3. CI tests against Laravel 12–13 on Ubuntu and Windows — avoid PHP features newer than 8.3 and keep file paths OS-safe (see `osSafe()` in `DraftsmanController`).

A GitHub Action runs Pint on every push and auto-commits a "Fix styling" commit; run `composer format` before pushing to avoid churn.

## Architecture

**Service provider** (`src/DraftsmanServiceProvider.php`): registers the config file, the `web` route file, and two artisan commands (`draftsman:install`, `draftsman:snapshot`) via laravel-package-tools.

**Routing** (`routes/web.php`): everything lives under the `/draftsman` prefix.
- `GET /draftsman` serves the UI index.
- `/draftsman/api/*` is the JSON API: `models` and `relations` apiResources (controllers in `src/Http/Controllers/ApiV1/`), plus `GET|POST config`.
- A catch-all slug route (`draftsman.front`) serves static frontend assets.

**Frontend**: `resources/front/` is a *prebuilt* Next.js static export committed to the repo (the "Build front" commits). The frontend source is not in this repository — do not hand-edit files under `resources/front/`. `DraftsmanController` streams these files from the package directory and patches MIME types (css/js/json/svg) that `mime_content_type` misreports.

**Model introspection** (`src/Http/Controllers/ApiV1/ApiController.php`) is the core of the package. It:
1. Scans the host app's `app_path()` for concrete `Eloquent\Model` subclasses (`getModelsList()`).
2. Runs `php artisan model:show --json` per model and enriches the output via reflection (`getModelShow()`) — file/line locations, from/to key attributes, pivot/through/morph metadata.
3. Uses a family of `$relations*Map` lookup tables keyed by relation class name (BelongsTo, HasMany, MorphToMany, …) to derive direction, multiplicity, and mandatory-ness for crow's-foot diagram notation. Adding support for a new relation type means adding it to each of these maps.

`ModelsController` and `RelationsController` extend `ApiController` to reuse this introspection. `ModelsController::store()` scaffolds models/migrations in the host app via `Artisan::call`.

**Config handling** (`src/Actions/GetDraftsmanConfig.php`, `UpdateDraftsmanConfig.php`): config is resolved in layers — published `config/draftsman.php`, falling back to the vendor default, with `presentation` overrides merged from `storage/draftsman/config.php`. The API's `POST config` writes through `UpdateDraftsmanConfig`.

## Testing notes

Tests use Pest with Orchestra Testbench (`tests/TestCase.php` registers the service provider; database defaults to the `testing` connection). Route tests in `tests/RoutesTest.php` hit the real `/draftsman` endpoints and depend on the committed `resources/front/` build being present.
