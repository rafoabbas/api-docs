<?php

declare(strict_types=1);

use ApiDocs\Data\RequestData;
use ApiDocs\Generators\CollectionGenerator;

it('adds deprecation notice to description', function (): void {
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

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $request = $collection['item'][0]['item'][0]['request'];

    expect($request['description'])->toContain('DEPRECATED');
    expect($request['description'])->toContain('Use v2 API');
    expect($request['description'])->toContain('/api/v2/users');
});

it('adds deprecation notice without reason', function (): void {
    $requests = [
        new RequestData(
            name: 'Old Users',
            method: 'GET',
            uri: 'api/v1/users',
            folder: 'Users',
            deprecated: true,
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $request = $collection['item'][0]['item'][0]['request'];

    expect($request['description'])->toBe('**DEPRECATED**');
});

it('prepends deprecation notice to existing description', function (): void {
    $requests = [
        new RequestData(
            name: 'Old Users',
            method: 'GET',
            uri: 'api/v1/users',
            folder: 'Users',
            description: 'List all users',
            deprecated: true,
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $request = $collection['item'][0]['item'][0]['request'];

    expect($request['description'])->toContain('DEPRECATED');
    expect($request['description'])->toContain('List all users');
});

it('marks file fields as file type in formdata', function (): void {
    $requests = [
        new RequestData(
            name: 'Upload',
            method: 'POST',
            uri: 'api/upload',
            folder: 'Files',
            body: ['title' => 'My File', 'avatar' => '(file)'],
            bodyMode: 'formdata',
            hasFileUpload: true,
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $body = $collection['item'][0]['item'][0]['request']['body'];

    expect($body['mode'])->toBe('formdata');

    $titleField = collect($body['formdata'])->firstWhere('key', 'title');
    $avatarField = collect($body['formdata'])->firstWhere('key', 'avatar');

    expect($titleField['type'])->toBe('text');
    expect($avatarField['type'])->toBe('file');
});

it('marks file fields using body schema', function (): void {
    $requests = [
        new RequestData(
            name: 'Upload',
            method: 'POST',
            uri: 'api/upload',
            folder: 'Files',
            body: ['title' => 'My File', 'avatar' => '(binary)'],
            bodyMode: 'formdata',
            hasFileUpload: true,
            bodySchema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'example' => 'My File'],
                    'avatar' => ['type' => 'string', 'format' => 'binary', 'example' => '(binary)'],
                ],
            ],
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $body = $collection['item'][0]['item'][0]['request']['body'];
    $avatarField = collect($body['formdata'])->firstWhere('key', 'avatar');

    expect($avatarField['type'])->toBe('file');
});

it('does not add deprecation to non-deprecated requests', function (): void {
    $requests = [
        new RequestData(
            name: 'Users',
            method: 'GET',
            uri: 'api/users',
            folder: 'Users',
            description: 'List users',
        ),
    ];

    $generator = new CollectionGenerator('Test API');
    $collection = $generator->generate($requests);

    $request = $collection['item'][0]['item'][0]['request'];

    expect($request['description'])->toBe('List users');
    expect($request['description'])->not->toContain('DEPRECATED');
});
