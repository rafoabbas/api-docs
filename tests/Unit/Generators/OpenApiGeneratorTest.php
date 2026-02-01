<?php

declare(strict_types=1);

use ApiDocs\Data\AuthData;
use ApiDocs\Data\QueryParamData;
use ApiDocs\Data\RequestData;
use ApiDocs\Data\ResponseData;
use ApiDocs\Generators\OpenApiGenerator;

it('generates valid openapi structure', function (): void {
    $requests = [
        new RequestData(name: 'Get Users', method: 'GET', uri: 'api/users', folder: 'Users'),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    expect($spec)->toHaveKey('openapi');
    expect($spec['openapi'])->toBe('3.0.3');
    expect($spec)->toHaveKey('info');
    expect($spec)->toHaveKey('servers');
    expect($spec)->toHaveKey('tags');
    expect($spec)->toHaveKey('paths');
});

it('sets api info correctly', function (): void {
    $requests = [
        new RequestData(name: 'Test', method: 'GET', uri: 'api/test', folder: 'Test'),
    ];

    $generator = new OpenApiGenerator;
    $generator->setTitle('My API');
    $generator->setVersion('2.0.0');
    $generator->setDescription('API Description');

    $spec = $generator->generate($requests);

    expect($spec['info']['title'])->toBe('My API');
    expect($spec['info']['version'])->toBe('2.0.0');
    expect($spec['info']['description'])->toBe('API Description');
});

it('generates paths with correct http methods', function (): void {
    $requests = [
        new RequestData(name: 'Get', method: 'GET', uri: 'api/users/{id}', folder: 'Users'),
        new RequestData(name: 'Update', method: 'PUT', uri: 'api/users/{id}', folder: 'Users'),
        new RequestData(name: 'Delete', method: 'DELETE', uri: 'api/users/{id}', folder: 'Users'),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    expect($spec['paths'])->toHaveKey('/api/users/{id}');
    expect($spec['paths']['/api/users/{id}'])->toHaveKey('get');
    expect($spec['paths']['/api/users/{id}'])->toHaveKey('put');
    expect($spec['paths']['/api/users/{id}'])->toHaveKey('delete');
});

it('extracts path parameters', function (): void {
    $requests = [
        new RequestData(name: 'Get User', method: 'GET', uri: 'api/users/{id}', folder: 'Users'),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $operation = $spec['paths']['/api/users/{id}']['get'];

    expect($operation['parameters'])->toBeArray();
    expect($operation['parameters'][0]['name'])->toBe('id');
    expect($operation['parameters'][0]['in'])->toBe('path');
    expect($operation['parameters'][0]['required'])->toBeTrue();
});

it('includes query parameters', function (): void {
    $requests = [
        new RequestData(
            name: 'Search',
            method: 'GET',
            uri: 'api/search',
            folder: 'Search',
            queryParams: [
                new QueryParamData(key: 'q', value: 'term', description: 'Search query'),
                new QueryParamData(key: 'page', value: '1'),
            ],
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $params = $spec['paths']['/api/search']['get']['parameters'];

    expect($params)->toHaveCount(2);
    expect($params[0]['name'])->toBe('q');
    expect($params[0]['in'])->toBe('query');
    expect($params[0]['example'])->toBe('term');
});

it('generates request body for post/put/patch', function (): void {
    $requests = [
        new RequestData(
            name: 'Create User',
            method: 'POST',
            uri: 'api/users',
            folder: 'Users',
            body: ['name' => 'John', 'email' => 'john@example.com'],
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $operation = $spec['paths']['/api/users']['post'];

    expect($operation)->toHaveKey('requestBody');
    expect($operation['requestBody']['required'])->toBeTrue();
    expect($operation['requestBody']['content'])->toHaveKey('application/json');
});

it('generates responses from response data', function (): void {
    $requests = [
        new RequestData(
            name: 'Get User',
            method: 'GET',
            uri: 'api/users/{id}',
            folder: 'Users',
            responses: [
                new ResponseData('Success', 200, ['id' => 1, 'name' => 'John']),
                new ResponseData('Not Found', 404, ['error' => 'User not found']),
            ],
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $responses = $spec['paths']['/api/users/{id}']['get']['responses'];

    expect($responses)->toHaveKey('200');
    expect($responses)->toHaveKey('404');
    expect($responses['200']['description'])->toBe('Success');
    expect($responses['404']['description'])->toBe('Not Found');
});

it('adds security schemes for authenticated requests', function (): void {
    $requests = [
        new RequestData(
            name: 'Protected',
            method: 'GET',
            uri: 'api/protected',
            folder: 'Auth',
            auth: new AuthData(type: 'bearer'),
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    expect($spec)->toHaveKey('components');
    expect($spec['components']['securitySchemes'])->toHaveKey('bearerAuth');
});

it('adds security requirement to authenticated endpoints', function (): void {
    $requests = [
        new RequestData(
            name: 'Protected',
            method: 'GET',
            uri: 'api/protected',
            folder: 'Auth',
            middleware: ['auth:sanctum'],
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $operation = $spec['paths']['/api/protected']['get'];
    expect($operation)->toHaveKey('security');
});

it('handles noauth correctly', function (): void {
    $requests = [
        new RequestData(
            name: 'Public',
            method: 'GET',
            uri: 'api/public',
            folder: 'Public',
            auth: new AuthData(type: 'noauth'),
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $operation = $spec['paths']['/api/public']['get'];
    expect($operation)->not->toHaveKey('security');
});

it('extracts tags from folder names using last part', function (): void {
    $requests = [
        new RequestData(name: 'R1', method: 'GET', uri: 'api/users', folder: 'V1 / Customer / Auth'),
        new RequestData(name: 'R2', method: 'GET', uri: 'api/products', folder: 'V1 / Customer / Order'),
        new RequestData(name: 'R3', method: 'POST', uri: 'api/users', folder: 'V1 / Customer / Auth'),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $tagNames = array_column($spec['tags'], 'name');

    expect($tagNames)->toContain('Auth');
    expect($tagNames)->toContain('Order');
    expect(count(array_unique($tagNames)))->toBe(count($tagNames)); // No duplicates
});

it('generates yaml output', function (): void {
    $requests = [
        new RequestData(name: 'Test', method: 'GET', uri: 'api/test', folder: 'Test'),
    ];

    $generator = new OpenApiGenerator;
    $yaml = $generator->generateYaml($requests);

    expect($yaml)->toContain('openapi: 3.0.3');
    expect($yaml)->toContain('paths:');
});

it('generates json output', function (): void {
    $requests = [
        new RequestData(name: 'Test', method: 'GET', uri: 'api/test', folder: 'Test'),
    ];

    $generator = new OpenApiGenerator;
    $json = $generator->generateJson($requests);
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect($decoded['openapi'])->toBe('3.0.3');
});

it('configures servers', function (): void {
    $requests = [
        new RequestData(name: 'Test', method: 'GET', uri: 'api/test', folder: 'Test'),
    ];

    $generator = new OpenApiGenerator;
    $generator->addServer('https://api.example.com', 'Production');
    $generator->addServer('https://staging.example.com', 'Staging');

    $spec = $generator->generate($requests);

    expect($spec['servers'])->toHaveCount(2);
    expect($spec['servers'][0]['url'])->toBe('https://api.example.com');
    expect($spec['servers'][0]['description'])->toBe('Production');
});

it('infers schema types from body data', function (): void {
    $requests = [
        new RequestData(
            name: 'Create',
            method: 'POST',
            uri: 'api/records',
            folder: 'Records',
            body: [
                'string_field' => 'test',
                'integer_field' => 123,
                'float_field' => 12.5,
                'boolean_field' => true,
                'email_field' => 'test@example.com',
            ],
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $schema = $spec['paths']['/api/records']['post']['requestBody']['content']['application/json']['schema'];

    expect($schema['properties']['string_field']['type'])->toBe('string');
    expect($schema['properties']['integer_field']['type'])->toBe('integer');
    expect($schema['properties']['float_field']['type'])->toBe('number');
    expect($schema['properties']['boolean_field']['type'])->toBe('boolean');
    expect($schema['properties']['email_field']['format'])->toBe('email');
});

it('handles optional path parameters', function (): void {
    $requests = [
        new RequestData(name: 'Get', method: 'GET', uri: 'api/users/{id?}', folder: 'Users'),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    expect($spec['paths'])->toHaveKey('/api/users/{id}');
});
