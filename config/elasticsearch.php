<?php

return [
    'host' => env('ELASTICSEARCH_HOST', 'localhost'),
    'port' => env('ELASTICSEARCH_PORT', '9200'),
    'user' => env('ELASTICSEARCH_USER', ''),
    'pass' => env('ELASTICSEARCH_PASS', ''),
    'index' => env('ELASTICSEARCH_INDEX', 'appointments'),
];
