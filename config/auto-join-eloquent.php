<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto Join Eloquent Configuration
    |--------------------------------------------------------------------------
    |
    | The following options determine how the auto-join system handles relationship
    | joins and alias resolution.
    |
    */

    // When true, auto-join will generate simple sequential aliases (A, B, C, …, then A1, B1, etc.).
    'use_simple_aliases' => env('AUTO_JOIN_SIMPLE_ALIASES', true),

    // The default join type to use. Options are "left" or "inner".
    'join_type' => env('AUTO_JOIN_TYPE', 'left'),

    // Debug mode: if true, the package will output debug information for query compilation.
    'debug' => env('AUTO_JOIN_DEBUG', false),

    // DEBUG_SQL: if true, additional SQL debugging output will be enabled.
    'debug_sql' => env('AUTO_JOIN_DEBUG_SQL', false),
];
