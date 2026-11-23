<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin account
    |--------------------------------------------------------------------------
    |
    | The single admin account (the Founder / Chairman). It is created by
    | Database\Seeders\AdminUserSeeder. Public registration is disabled.
    |
    */

    'name' => env('ADMIN_NAME', 'Edward J. Dugan'),

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

    /*
    | Shown under the admin's name in the sidebar and on the More screen.
    */
    'title' => 'Founder / Chairman',

];
