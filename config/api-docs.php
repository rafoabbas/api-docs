<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Exclude Prefixes
    |--------------------------------------------------------------------------
    |
    | Route prefixes to exclude from the Postman collection.
    |
    */
    'exclude_prefixes' => [
        '_',
        'sanctum',
        'telescope',
        'storage',
        'mcp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Variables
    |--------------------------------------------------------------------------
    |
    | Additional variables to include in the Postman collection.
    |
    */
    'variables' => [
        // 'CUSTOM_VAR' => 'value',
    ],

    /*
    |--------------------------------------------------------------------------
    | Postman Environments
    |--------------------------------------------------------------------------
    |
    | Define Postman environments to generate separate environment files.
    | Each environment will be exported as a .postman_environment.json file.
    |
    */
    'environments' => [
        'local' => [
            'API_URL' => env('API_URL', 'http://localhost'),
            'BEARER_TOKEN' => '',
        ],
        // 'staging' => [
        //     'API_URL' => 'https://staging-api.example.com',
        //     'BEARER_TOKEN' => '',
        // ],
        // 'production' => [
        //     'API_URL' => 'https://api.example.com',
        //     'BEARER_TOKEN' => '',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Headers
    |--------------------------------------------------------------------------
    |
    | Default headers to include in all requests.
    |
    */
    'default_headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

    /*
    |--------------------------------------------------------------------------
    | YAML Configuration
    |--------------------------------------------------------------------------
    |
    | Path to YAML files containing API definitions.
    | These files will be merged with PHP attribute definitions.
    | Attributes take priority over YAML definitions.
    |
    */
    'yaml_path' => resource_path('api-docs'),

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for OpenAPI 3.x specification output.
    |
    */
    'openapi' => [
        /*
        | API title for OpenAPI info section
        */
        'title' => env('APP_NAME', 'API Documentation'),

        /*
        | API version (semver format recommended)
        */
        'version' => '1.0.0',

        /*
        | API description
        */
        'description' => '',

        /*
        | Server URLs for the API
        | Each server should have 'url' and optionally 'description'
        */
        'servers' => [
            // ['url' => 'https://api.example.com', 'description' => 'Production'],
            // ['url' => 'https://staging-api.example.com', 'description' => 'Staging'],
        ],

        /*
        | Output format: 'yaml' or 'json'
        */
        'output_format' => 'yaml',
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    |
    | Default output paths for generated files.
    |
    */
    'output' => [
        /*
        | Directory for Postman collection output
        */
        'postman_path' => base_path('docs/postman'),

        /*
        | Directory for OpenAPI specification output
        */
        'openapi_path' => base_path('docs/openapi'),
    ],
];
