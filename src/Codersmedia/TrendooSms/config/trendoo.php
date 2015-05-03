<?php
return [
    'debug'             => true,
    'login'             => env('TRENDOO_USER', 'demo'),
    'password'          => env('TRENDOO_PASS', 'demo'),
    'sms' => [
        'type'          => 'SI', // SI = Silver, GS = Gold Standard, GP = Gold Premium
    ]
];
