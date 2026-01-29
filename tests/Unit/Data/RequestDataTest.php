<?php

declare(strict_types=1);

use ApiDocs\Data\AuthData;
use ApiDocs\Data\HeaderData;
use ApiDocs\Data\QueryParamData;
use ApiDocs\Data\RequestData;
use ApiDocs\Data\ResponseData;

it('can create a request data with minimal parameters', function () {
    $request = new RequestData(
        name: 'Get Users',
        method: 'GET',
        uri: 'api/users'
    );

    expect($request->name)->toBe('Get Users');
    expect($request->method)->toBe('GET');
    expect($request->uri)->toBe('api/users');
    expect($request->folder)->toBe('General');
    expect($request->description)->toBeNull();
    expect($request->body)->toBeNull();
    expect($request->headers)->toBeArray()->toBeEmpty();
    expect($request->queryParams)->toBeArray()->toBeEmpty();
    expect($request->responses)->toBeArray()->toBeEmpty();
    expect($request->auth)->toBeNull();
});

it('can create a request data with all parameters', function () {
    $request = new RequestData(
        name: 'Create User',
        method: 'POST',
        uri: 'api/users',
        description: 'Create a new user',
        folder: 'Users / Create',
        order: 1,
        body: ['name' => 'John', 'email' => 'john@example.com'],
        bodyMode: 'raw',
        bodyLanguage: 'json',
        headers: [new HeaderData('X-Custom', 'value')],
        queryParams: [new QueryParamData('page', '1')],
        responses: [new ResponseData('Success', 201, ['id' => 1])],
        auth: new AuthData('bearer'),
        middleware: ['auth:sanctum']
    );

    expect($request->name)->toBe('Create User');
    expect($request->method)->toBe('POST');
    expect($request->uri)->toBe('api/users');
    expect($request->description)->toBe('Create a new user');
    expect($request->folder)->toBe('Users / Create');
    expect($request->order)->toBe(1);
    expect($request->body)->toBe(['name' => 'John', 'email' => 'john@example.com']);
    expect($request->bodyMode)->toBe('raw');
    expect($request->bodyLanguage)->toBe('json');
    expect($request->headers)->toHaveCount(1);
    expect($request->queryParams)->toHaveCount(1);
    expect($request->responses)->toHaveCount(1);
    expect($request->auth)->not->toBeNull();
    expect($request->auth->type)->toBe('bearer');
    expect($request->middleware)->toContain('auth:sanctum');
});

it('is readonly', function () {
    $request = new RequestData(
        name: 'Test',
        method: 'GET',
        uri: 'api/test'
    );

    $reflection = new ReflectionClass($request);

    expect($reflection->isReadOnly())->toBeTrue();
});
