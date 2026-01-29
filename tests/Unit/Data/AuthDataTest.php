<?php

declare(strict_types=1);

use ApiDocs\Data\AuthData;

it('creates bearer auth with defaults', function (): void {
    $auth = new AuthData;

    expect($auth->type)->toBe('bearer');
    expect($auth->token)->toBe('{{BEARER_TOKEN}}');
    expect($auth->username)->toBeNull();
    expect($auth->password)->toBeNull();
    expect($auth->apiKey)->toBeNull();
    expect($auth->apiKeyHeader)->toBe('X-API-Key');
});

it('creates bearer auth with custom token', function (): void {
    $auth = new AuthData(type: 'bearer', token: '{{CUSTOM_TOKEN}}');

    expect($auth->type)->toBe('bearer');
    expect($auth->token)->toBe('{{CUSTOM_TOKEN}}');
});

it('creates basic auth', function (): void {
    $auth = new AuthData(
        type: 'basic',
        username: 'admin',
        password: 'secret',
    );

    expect($auth->type)->toBe('basic');
    expect($auth->username)->toBe('admin');
    expect($auth->password)->toBe('secret');
});

it('creates apikey auth', function (): void {
    $auth = new AuthData(
        type: 'apikey',
        apiKey: 'my-api-key',
        apiKeyHeader: 'X-API-Key',
    );

    expect($auth->type)->toBe('apikey');
    expect($auth->apiKey)->toBe('my-api-key');
    expect($auth->apiKeyHeader)->toBe('X-API-Key');
});

it('creates noauth', function (): void {
    $auth = new AuthData(type: 'noauth');

    expect($auth->type)->toBe('noauth');
});
