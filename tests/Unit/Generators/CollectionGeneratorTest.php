<?php

declare(strict_types=1);

use ApiDocs\Data\AuthData;
use ApiDocs\Data\QueryParamData;
use ApiDocs\Data\RequestData;
use ApiDocs\Data\ResponseData;
use ApiDocs\Generators\CollectionGenerator;

it('generates valid postman collection structure', function (): void {
    $requests = [
        new RequestData(name: 'Get Users', method: 'GET', uri: 'api/users', folder: 'Users'),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    expect($collection)->toHaveKey('info');
    expect($collection)->toHaveKey('item');
    expect($collection)->toHaveKey('variable');
    expect($collection['info']['name'])->toBe('Test API');
    expect($collection['info']['schema'])->toBe('https://schema.getpostman.com/json/collection/v2.1.0/collection.json');
});

it('groups requests by folder', function (): void {
    $requests = [
        new RequestData(name: 'Get Users', method: 'GET', uri: 'api/users', folder: 'Users'),
        new RequestData(name: 'Create User', method: 'POST', uri: 'api/users', folder: 'Users'),
        new RequestData(name: 'Get Products', method: 'GET', uri: 'api/products', folder: 'Products'),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $folderNames = array_column($collection['item'], 'name');

    expect($folderNames)->toContain('Users');
    expect($folderNames)->toContain('Products');
});

it('handles nested folders', function (): void {
    $requests = [
        new RequestData(name: 'Request OTP', method: 'POST', uri: 'api/auth/otp', folder: 'Auth / OTP'),
        new RequestData(name: 'Verify OTP', method: 'POST', uri: 'api/auth/otp/verify', folder: 'Auth / OTP'),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    expect($collection['item'][0]['name'])->toBe('Auth');
    expect($collection['item'][0]['item'][0]['name'])->toBe('OTP');
});

it('adds request method and url', function (): void {
    $requests = [
        new RequestData(name: 'Get Users', method: 'GET', uri: 'api/users', folder: 'Users'),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $request = $collection['item'][0]['item'][0]['request'];

    expect($request['method'])->toBe('GET');
    expect($request['url'])->toBeArray();
});

it('adds body for post requests', function (): void {
    $requests = [
        new RequestData(
            name: 'Create User',
            method: 'POST',
            uri: 'api/users',
            folder: 'Users',
            body: ['name' => 'John', 'email' => 'john@example.com'],
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $request = $collection['item'][0]['item'][0]['request'];

    expect($request)->toHaveKey('body');
    expect($request['body']['mode'])->toBe('raw');
});

it('adds query parameters to url', function (): void {
    $requests = [
        new RequestData(
            name: 'Search',
            method: 'GET',
            uri: 'api/search',
            folder: 'Search',
            queryParams: [
                new QueryParamData('q', 'term'),
                new QueryParamData('page', '1'),
            ],
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $url = $collection['item'][0]['item'][0]['request']['url'];

    expect($url)->toHaveKey('query');
    expect($url['query'])->toHaveCount(2);
});

it('adds responses as examples', function (): void {
    $requests = [
        new RequestData(
            name: 'Get User',
            method: 'GET',
            uri: 'api/users/{id}',
            folder: 'Users',
            responses: [
                new ResponseData('Success', 200, ['id' => 1]),
                new ResponseData('Not Found', 404, ['error' => 'Not found']),
            ],
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $responses = $collection['item'][0]['item'][0]['response'];

    expect($responses)->toHaveCount(2);
    expect($responses[0]['name'])->toBe('Success');
    expect($responses[0]['code'])->toBe(200);
});

it('adds auth configuration', function (): void {
    $requests = [
        new RequestData(
            name: 'Protected',
            method: 'GET',
            uri: 'api/protected',
            folder: 'Auth',
            auth: new AuthData(type: 'bearer'),
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $request = $collection['item'][0]['item'][0]['request'];

    expect($request)->toHaveKey('auth');
    expect($request['auth']['type'])->toBe('bearer');
});

it('adds noauth correctly', function (): void {
    $requests = [
        new RequestData(
            name: 'Public',
            method: 'GET',
            uri: 'api/public',
            folder: 'Public',
            auth: new AuthData(type: 'noauth'),
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $request = $collection['item'][0]['item'][0]['request'];

    expect($request['auth']['type'])->toBe('noauth');
});

it('sets collection variables', function (): void {
    $requests = [
        new RequestData(name: 'Test', method: 'GET', uri: 'api/test', folder: 'Test'),
    ];

    $generator = new CollectionGenerator('Test API');
    $generator->setVariables([
        'API_URL' => 'https://api.example.com',
        'TOKEN' => '',
    ]);

    $collection = $generator->generate($requests);

    $variableKeys = array_column($collection['variable'], 'key');

    expect($variableKeys)->toContain('API_URL');
    expect($variableKeys)->toContain('TOKEN');
});

it('adds variable via addVariable', function (): void {
    $requests = [
        new RequestData(name: 'Test', method: 'GET', uri: 'api/test', folder: 'Test'),
    ];

    $generator = new CollectionGenerator('Test API');
    $generator->addVariable('CUSTOM_VAR', 'custom_value');

    $collection = $generator->generate($requests);

    $customVar = collect($collection['variable'])->firstWhere('key', 'CUSTOM_VAR');

    expect($customVar['value'])->toBe('custom_value');
});

it('converts path parameters to postman format', function (): void {
    $requests = [
        new RequestData(name: 'Get User', method: 'GET', uri: 'api/users/{id}', folder: 'Users'),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $url = $collection['item'][0]['item'][0]['request']['url'];

    expect($url['path'])->toContain(':id');
    expect($url)->toHaveKey('variable');
});

it('sorts requests by order within folder', function (): void {
    $requests = [
        new RequestData(name: 'Third', method: 'GET', uri: 'api/c', folder: 'Test', order: 3),
        new RequestData(name: 'First', method: 'GET', uri: 'api/a', folder: 'Test', order: 1),
        new RequestData(name: 'Second', method: 'GET', uri: 'api/b', folder: 'Test', order: 2),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $items = $collection['item'][0]['item'];

    expect($items[0]['name'])->toBe('First');
    expect($items[1]['name'])->toBe('Second');
    expect($items[2]['name'])->toBe('Third');
});

it('uses API_URL_V1 for v1 routes', function (): void {
    $requests = [
        new RequestData(name: 'V1 Route', method: 'GET', uri: 'v1/users', folder: 'Users'),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $url = $collection['item'][0]['item'][0]['request']['url'];

    expect($url['raw'])->toContain('API_URL_V1');
});

it('adds authorization header for sanctum middleware', function (): void {
    $requests = [
        new RequestData(
            name: 'Protected',
            method: 'GET',
            uri: 'api/protected',
            folder: 'Auth',
            middleware: ['auth:sanctum'],
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $headers = $collection['item'][0]['item'][0]['request']['header'];
    $authHeader = collect($headers)->firstWhere('key', 'Authorization');

    expect($authHeader)->not->toBeNull();
    expect($authHeader['value'])->toContain('Bearer');
});
