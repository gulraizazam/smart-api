<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ziggy Groups
    |--------------------------------------------------------------------------
    |
    | Define route name patterns to include in Ziggy's output.
    | By default, Ziggy only includes web routes. We need to include
    | API routes that start with 'admin.' prefix.
    |
    */
    'groups' => [
        'admin' => ['admin.*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Except
    |--------------------------------------------------------------------------
    |
    | Exclude specific routes from Ziggy's output.
    |
    */
    'except' => [
        '_debugbar.*',
        'sanctum.*',
        'ignition.*',
    ],
];
