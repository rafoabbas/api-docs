<?php

declare(strict_types=1);

use ApiDocs\Data\ResponseData;

it('creates response with minimal parameters', function (): void {
    $response = new ResponseData(name: 'Success');

    expect($response->name)->toBe('Success');
    expect($response->status)->toBe(200);
    expect($response->body)->toBeArray()->toBeEmpty();
    expect($response->headers)->toBeArray()->toBeEmpty();
});

it('creates response with all parameters', function (): void {
    $response = new ResponseData(
        name: 'Created',
        status: 201,
        body: ['id' => 1, 'name' => 'John'],
        headers: ['Location' => '/api/users/1'],
    );

    expect($response->name)->toBe('Created');
    expect($response->status)->toBe(201);
    expect($response->body)->toBe(['id' => 1, 'name' => 'John']);
    expect($response->headers)->toBe(['Location' => '/api/users/1']);
});

it('creates error response', function (): void {
    $response = new ResponseData(
        name: 'Not Found',
        status: 404,
        body: ['success' => false, 'message' => 'Resource not found'],
    );

    expect($response->name)->toBe('Not Found');
    expect($response->status)->toBe(404);
    expect($response->body['success'])->toBeFalse();
});
