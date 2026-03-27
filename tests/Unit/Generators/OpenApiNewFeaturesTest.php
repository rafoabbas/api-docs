<?php

declare(strict_types=1);

use ApiDocs\Data\RequestData;
use ApiDocs\Generators\OpenApiGenerator;

it('adds deprecated flag to operation', function (): void {
    $requests = [
        new RequestData(
            name: 'Old Users',
            method: 'GET',
            uri: 'api/v1/users',
            folder: 'Users',
            deprecated: true,
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $operation = $spec['paths']['/api/v1/users']['get'];

    expect($operation['deprecated'])->toBeTrue();
});

it('adds deprecation reason to description', function (): void {
    $requests = [
        new RequestData(
            name: 'Old Users',
            method: 'GET',
            uri: 'api/v1/users',
            folder: 'Users',
            deprecated: true,
            deprecatedReason: 'Use v2 API',
            deprecatedReplacement: '/api/v2/users',
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $operation = $spec['paths']['/api/v1/users']['get'];

    expect($operation['deprecated'])->toBeTrue();
    expect($operation['description'])->toContain('Deprecated');
    expect($operation['description'])->toContain('Use v2 API');
    expect($operation['description'])->toContain('/api/v2/users');
});

it('does not add deprecated flag for non-deprecated endpoints', function (): void {
    $requests = [
        new RequestData(name: 'Users', method: 'GET', uri: 'api/users', folder: 'Users'),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $operation = $spec['paths']['/api/users']['get'];

    expect($operation)->not->toHaveKey('deprecated');
});

it('uses body schema when available', function (): void {
    $requests = [
        new RequestData(
            name: 'Create User',
            method: 'POST',
            uri: 'api/users',
            folder: 'Users',
            body: ['email' => 'user@example.com', 'password' => 'secret'],
            bodySchema: [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                    'password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'example' => 'secret'],
                ],
                'required' => ['email', 'password'],
            ],
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $schema = $spec['paths']['/api/users']['post']['requestBody']['content']['application/json']['schema'];

    expect($schema['properties']['email']['format'])->toBe('email');
    expect($schema['properties']['password']['format'])->toBe('password');
    expect($schema['properties']['password']['minLength'])->toBe(8);
    expect($schema['required'])->toContain('email');
    expect($schema['required'])->toContain('password');
});

it('uses multipart content type for file uploads', function (): void {
    $requests = [
        new RequestData(
            name: 'Upload',
            method: 'POST',
            uri: 'api/upload',
            folder: 'Files',
            body: ['title' => 'My File', 'file' => '(binary)'],
            bodyMode: 'formdata',
            hasFileUpload: true,
            bodySchema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'example' => 'My File'],
                    'file' => ['type' => 'string', 'format' => 'binary', 'example' => '(binary)'],
                ],
                'required' => ['title', 'file'],
            ],
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $requestBody = $spec['paths']['/api/upload']['post']['requestBody'];

    expect($requestBody['content'])->toHaveKey('multipart/form-data');
    expect($requestBody['content'])->not->toHaveKey('application/json');

    $schema = $requestBody['content']['multipart/form-data']['schema'];
    expect($schema['properties']['file']['format'])->toBe('binary');
});

it('generates request body from body schema only', function (): void {
    $requests = [
        new RequestData(
            name: 'Create',
            method: 'POST',
            uri: 'api/items',
            folder: 'Items',
            bodySchema: [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255, 'example' => 'John'],
                ],
                'required' => ['name'],
            ],
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    expect($spec['paths']['/api/items']['post'])->toHaveKey('requestBody');

    $schema = $spec['paths']['/api/items']['post']['requestBody']['content']['application/json']['schema'];
    expect($schema['properties']['name']['maxLength'])->toBe(255);
});

it('falls back to data inference when no body schema', function (): void {
    $requests = [
        new RequestData(
            name: 'Create',
            method: 'POST',
            uri: 'api/items',
            folder: 'Items',
            body: ['name' => 'John', 'active' => true],
        ),
    ];

    $generator = new OpenApiGenerator;
    $spec = $generator->generate($requests);

    $schema = $spec['paths']['/api/items']['post']['requestBody']['content']['application/json']['schema'];

    expect($schema['properties']['name']['type'])->toBe('string');
    expect($schema['properties']['active']['type'])->toBe('boolean');
});
