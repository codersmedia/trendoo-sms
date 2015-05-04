<?php
return [
    'debug'             => true,
    'login'             => env('TRENDOO_USER', 'demo'),
    'password'          => env('TRENDOO_PASS', 'demo'),
    'sms' => [
        'message_type'          => 'SI', // SI = Silver, GS = Gold Standard, GP = Gold Premium
        'sender'        => 'AZIENDA',
    ]
];
