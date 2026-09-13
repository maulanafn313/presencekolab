<?php

return [
    // Attempts per 60 seconds. API and legacy auth share these counters.
    'request_limits' => [
        'login' => ['per_ip' => 60, 'per_account_ip' => 10],
        'register' => ['per_ip' => 10, 'per_account_ip' => 3],
        'recovery' => ['per_ip' => 20, 'per_account_ip' => 5],
        'face' => ['per_ip' => 60],
    ],
];
