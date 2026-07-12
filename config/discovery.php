<?php

return [
    'driver' => env('DISCOVERY_DRIVER', 'database'),
    'index' => env('OPENSEARCH_PROFILE_INDEX', 'sportuniverse_profiles_v1'),
    'hosts' => array_filter(explode(',', env('OPENSEARCH_HOSTS', env('OPENSEARCH_HOST', 'http://127.0.0.1:9200')))),
    'username' => env('OPENSEARCH_USERNAME'),
    'password' => env('OPENSEARCH_PASSWORD'),
    'verify_ssl' => env('OPENSEARCH_VERIFY_SSL', true),
];
