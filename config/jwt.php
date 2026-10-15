<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Secret & Algorithm
    |--------------------------------------------------------------------------
    |
    | Secret key used for signing and verifying JSON Web Tokens (HS256).
    | HS256 requires a secret key of at least 256 bits (32 characters).
    |
    */
    'secret' => env('JWT_SECRET', 'secret-key-default-tridharma-akreditasi-pbl-2026-min-32-bytes'),
    'algo' => env('JWT_ALGO', 'HS256'),
    'ttl' => (int) env('JWT_TTL', 86400),
];
