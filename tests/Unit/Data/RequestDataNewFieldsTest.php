<?php

declare(strict_types=1);

use ApiDocs\Data\RequestData;

it('has deprecated fields with defaults', function (): void {
    $data = new RequestData(name: 'Test', method: 'GET', uri: 'api/test');

    expect($data->deprecated)->toBeFalse();
    expect($data->deprecatedReason)->toBeNull();
    expect($data->deprecatedReplacement)->toBeNull();
});

it('stores deprecated fields', function (): void {
    $data = new RequestData(
        name: 'Test',
        method: 'GET',
        uri: 'api/test',
        deprecated: true,
        deprecatedReason: 'Use v2',
        deprecatedReplacement: '/api/v2/test',
    );

    expect($data->deprecated)->toBeTrue();
    expect($data->deprecatedReason)->toBe('Use v2');
    expect($data->deprecatedReplacement)->toBe('/api/v2/test');
});

it('has body schema field with default', function (): void {
    $data = new RequestData(name: 'Test', method: 'POST', uri: 'api/test');

    expect($data->bodySchema)->toBeNull();
});

it('stores body schema', function (): void {
    $schema = [
        'type' => 'object',
        'properties' => [
            'email' => ['type' => 'string', 'format' => 'email'],
        ],
        'required' => ['email'],
    ];

    $data = new RequestData(
        name: 'Test',
        method: 'POST',
        uri: 'api/test',
        bodySchema: $schema,
    );

    expect($data->bodySchema)->toBe($schema);
});

it('has file upload field with default', function (): void {
    $data = new RequestData(name: 'Test', method: 'POST', uri: 'api/test');

    expect($data->hasFileUpload)->toBeFalse();
});

it('stores file upload flag', function (): void {
    $data = new RequestData(
        name: 'Test',
        method: 'POST',
        uri: 'api/test',
        hasFileUpload: true,
    );

    expect($data->hasFileUpload)->toBeTrue();
});

it('has pagination field with default', function (): void {
    $data = new RequestData(name: 'Test', method: 'GET', uri: 'api/test');

    expect($data->isPaginated)->toBeFalse();
});

it('stores pagination flag', function (): void {
    $data = new RequestData(
        name: 'Test',
        method: 'GET',
        uri: 'api/test',
        isPaginated: true,
    );

    expect($data->isPaginated)->toBeTrue();
});
