# Deleted config keys — candidate future features

Every key that has been removed from `config/draftsman.php`, kept here so the
ideas aren't lost with the keys. The original full structure is in git at
`9ebbb6a` ("added initial config structure"); the first trim was `c657ba0`
("Graph and backend render", 2026-07-25), the second was the 2026-07-27
lean-config pass (only keys with live consumers survive — enforced by the
"ships a lean config" test in `tests/ConfigCapabilitiesTest.php`).

A key should return WITH its feature, not ahead of it.

## Open-on-the-user's-machine (removed 2026-07-27)

Intended for open-this-model-in-the-IDE buttons. The models API already
reports `file` and `line` per model and relation, so the data side is done.
Consider URL-scheme links in the frontend (`phpstorm://open?file=…&line=…`,
`vscode://file/…`) before building a backend endpoint — that would make the
editor choice a frontend presentation concern and these keys unnecessary.

| Key | Env | Default |
| --- | --- | --- |
| `package.editor` | `DRAFTSMAN_EDITOR` | `phpstorm` |
| `package.editor_flags` | `DRAFTSMAN_EDITOR_FLAGS` | `''` |
| `package.browser` | `DRAFTSMAN_BROWSER` | `chrome` |

## Env write-back default (removed 2026-07-27)

| Key | Env | Default |
| --- | --- | --- |
| `package.update_env` | `DRAFTSMAN_UPDATE_ENV` | `true` |

The `POST /draftsman/api/config` env-update flow is real but reads its
`update_env` flag from the request payload (default false). This config key
was meant to be the default for that flag and was never consulted. If a
config-level default is wanted, wire `UpdateDraftsmanConfig::handle()` to
fall back to it.

## Package location keys (removed in c657ba0)

| Key | Env | Default |
| --- | --- | --- |
| `config.draftsman_path` | `DRAFTSMAN_PATH` | `base_path('vendor/draftsmaninc/draftsman')` |
| `config.model_class` | `DRAFTSMAN_MODEL_CLASS` | `eloquent` |

Never consumed. `draftsman_path` is derivable at runtime; `model_class` was a
placeholder for supporting non-Eloquent base classes.

## `front` section — UI workflow settings (removed in c657ba0)

| Key | Env | Default |
| --- | --- | --- |
| `front.history_length` | `DRAFTSMAN_FRONT_HISTORY_LENGTH` | `300` |
| `front.snap_to_grid` | `DRAFTSMAN_FRONT_SNAP_TO_GRID` | `true` |

Undo/redo depth and grid snapping. The frontend has its own grid/snap
behavior today; if these become host-configurable they should flow through
the config API (`GET /draftsman/api/config`), which the frontend already
fetches on boot.

## `graph` section — layout defaults (removed in c657ba0)

| Key | Env | Default |
| --- | --- | --- |
| `graph.show_grid` | `DRAFTSMAN_GRAPH_SHOW_GRID` | `true` |
| `graph.grid_width` | `DRAFTSMAN_GRAPH_GRID_WIDTH` | `50` |
| `graph.label_edges` | `DRAFTSMAN_GRAPH_LABEL_EDGES` | `true` |
| `graph.use_gutters` | `DRAFTSMAN_GRAPH_USE_GUTTERS` | `true` |
| `graph.column_width` | `DRAFTSMAN_GRAPH_COLUMN_WIDTH` | `50` |
| `graph.column_min_width` | `DRAFTSMAN_GRAPH_COLUMN_MIN_WIDTH` | `50` |
| `graph.column_max_width` | `DRAFTSMAN_GRAPH_COLUMN_MAX_WIDTH` | `50` |
| `graph.gutter_width` | `DRAFTSMAN_GRAPH_GUTTER_WIDTH` | `50` |
| `graph.gutter_min_width` | `DRAFTSMAN_GRAPH_GUTTER_MIN_WIDTH` | `50` |
| `graph.gutter_max_width` | `DRAFTSMAN_GRAPH_GUTTER_TO_MAX` | `50` |

Superseded: layout genuinely lives in the graph document (saved via the
graphs API, version-controlled) plus the frontend's size-tier defaults and
Settings panel. Per-host layout defaults probably shouldn't come back as
env-var config; if ever wanted, they belong in the config API's presentation
layer.

## `presentation` section — per-model styling (removed in c657ba0)

```php
'presentation' => [
    App\Models\User::class => [
        'icon' => 'heroicon-o-user',
        'bg_color' => 'bg-sky-400',
        'text_color' => 'text-slate-100',
    ],
],
```

Half-built: the backend machinery EXISTS — `GetDraftsmanConfig` merges a
`presentation` section from `storage/draftsman/config.php`, `POST config`
writes it back (with `::class`-key export via `UpdateDraftsmanConfig`, the
one consumer of `package.models_path`) — but the frontend never renders the
icons/colors. Reviving this means frontend work reading `presentation` from
the config payload, not new config keys.
