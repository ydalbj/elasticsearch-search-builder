<?php
return [
    'monolog' => [
        'hosts' => [
            [
                'host' => env('ELASTIC_MONOLOG_HOST', 'localhost'),
                'port' => env('ELASTIC_MONOLOG_PORT', 9200),
                'username' => env('ELASTIC_USER', 'elastic'),
                'password' => env('ELASTIC_PASSWORD', 'changeme'),
            ],
        ],
    ],
    'goods' => [
        'hosts' => [
            [
                'host' => env('ELASTIC_GOODSLOG_HOST', 'localhost'),
                'port' => env('ELASTIC_GOODSLOG_PORT', 9200),
                'username' => env('ELASTIC_USER', 'elastic'),
                'password' => env('ELASTIC_PASSWORD', 'changeme'),
            ],
        ],
    ],
];
