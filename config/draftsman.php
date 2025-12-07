<?php

// Config for Draftsman

return [

    /*
    |--------------------------------------------------------------------------
    | Model Path
    |--------------------------------------------------------------------------
    |
    | The path to your project's model directory.
    |
    */

    'model_path' => env('DRAFTSMAN_MODEL_PATH', 'app\\models'),

    /*
    |--------------------------------------------------------------------------
    | Default Snapshot Path
    |--------------------------------------------------------------------------
    |
    | Path at which snapshots are saved to by default.
    |
    */

    'snapshot_path' => env('DRAFTSMAN_SNAPSHOT_PATH', storage_path('app/draftsman/snapshots/')),

];
