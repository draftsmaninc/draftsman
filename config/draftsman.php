<?php

// Config for Draftsman

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
