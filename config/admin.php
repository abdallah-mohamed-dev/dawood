<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin User
    |--------------------------------------------------------------------------
    |
    | This application has a single administrator account, created/updated
    | idempotently by the AdminUserSeeder from these values.
    |
    */

    'name' => env('ADMIN_NAME', 'المدير'),

    'email' => env('ADMIN_EMAIL', 'admin@dawood.test'),

    'password' => env('ADMIN_PASSWORD', 'password'),

];
