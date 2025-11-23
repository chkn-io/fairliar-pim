<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'store_domain' => env('SHOPIFY_STORE_DOMAIN'),
    'api_version' => env('SHOPIFY_API_VERSION', '2024-01'),
    'graphql_endpoint' => env('SHOPIFY_STORE_DOMAIN') ? 'https://' . env('SHOPIFY_STORE_DOMAIN') . '/admin/api/' . env('SHOPIFY_API_VERSION', '2024-01') . '/graphql.json' : null,
    'default_location_id' => env('SHOPIFY_DEFAULT_LOCATION_ID'),
];