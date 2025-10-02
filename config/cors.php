<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Atur pengaturan CORS untuk mengizinkan frontend (Ionic) mengakses backend.
    | Karena Ionic jalan di localhost:8100 / 192.168.0.107:8100, maka perlu ditambahkan.
    |
    */

    'paths' => ['api/*','login', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:8100',
        'http://192.168.0.107:8100',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
