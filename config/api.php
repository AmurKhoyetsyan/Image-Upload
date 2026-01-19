<?php

return [
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],
    'digest_auth' => [
        'username' => env('DIGEST_AUTH_USERNAME', 'admin'),
        'password' => env('DIGEST_AUTH_PASSWORD', 'password'),
        'realm' => env('DIGEST_AUTH_REALM', 'Restricted Area'),
    ],
];