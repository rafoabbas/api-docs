<?php

declare(strict_types=1);

use ApiDocs\Collectors\RequestMerger;
use ApiDocs\Data\RequestData;

it('returns empty array when both inputs are empty', function () {
    $merger = new RequestMerger;

    expect($merger->merge([], []))->toBeArray()->toBeEmpty();
});

it('returns attribute requests when yaml is empty', function () {
    $attributeRequests = [
        new RequestData(name: 'Attr 1', method: 'GET', uri: 'api/users'),
        new RequestData(name: 'Attr 2', method: 'POST', uri: 'api/users'),
    ];

    $merger = new RequestMerger;
    $result = $merger->merge($attributeRequests, []);

    expect($result)->toHaveCount(2);
    expect($result[0]->name)->toBe('Attr 1');
    expect($result[1]->name)->toBe('Attr 2');
});

it('returns yaml requests when attributes is empty', function () {
    $yamlRequests = [
        new RequestData(name: 'YAML 1', method: 'GET', uri: 'api/products'),
        new RequestData(name: 'YAML 2', method: 'POST', uri: 'api/products'),
    ];

    $merger = new RequestMerger;
    $result = $merger->merge([], $yamlRequests);

    expect($result)->toHaveCount(2);
    expect($result[0]->name)->toBe('YAML 1');
    expect($result[1]->name)->toBe('YAML 2');
});

it('merges non-conflicting requests from both sources', function () {
    $attributeRequests = [
        new RequestData(name: 'Attr Users', method: 'GET', uri: 'api/users'),
    ];

    $yamlRequests = [
        new RequestData(name: 'YAML Products', method: 'GET', uri: 'api/products'),
    ];

    $merger = new RequestMerger;
    $result = $merger->merge($attributeRequests, $yamlRequests);

    expect($result)->toHaveCount(2);
});

it('gives priority to attribute requests over yaml requests', function () {
    $attributeRequests = [
        new RequestData(
            name: 'Attribute Version',
            method: 'GET',
            uri: 'api/users',
            description: 'From attributes'
        ),
    ];

    $yamlRequests = [
        new RequestData(
            name: 'YAML Version',
            method: 'GET',
            uri: 'api/users',
            description: 'From YAML'
        ),
    ];

    $merger = new RequestMerger;
    $result = $merger->merge($attributeRequests, $yamlRequests);

    expect($result)->toHaveCount(1);
    expect($result[0]->name)->toBe('Attribute Version');
    expect($result[0]->description)->toBe('From attributes');
});

it('normalizes uri slashes for comparison', function () {
    $attributeRequests = [
        new RequestData(name: 'Attr', method: 'GET', uri: '/api/users/'),
    ];

    $yamlRequests = [
        new RequestData(name: 'YAML', method: 'GET', uri: 'api/users'),
    ];

    $merger = new RequestMerger;
    $result = $merger->merge($attributeRequests, $yamlRequests);

    expect($result)->toHaveCount(1);
    expect($result[0]->name)->toBe('Attr');
});

it('treats different methods as different endpoints', function () {
    $attributeRequests = [
        new RequestData(name: 'GET Users', method: 'GET', uri: 'api/users'),
    ];

    $yamlRequests = [
        new RequestData(name: 'POST Users', method: 'POST', uri: 'api/users'),
        new RequestData(name: 'DELETE Users', method: 'DELETE', uri: 'api/users'),
    ];

    $merger = new RequestMerger;
    $result = $merger->merge($attributeRequests, $yamlRequests);

    expect($result)->toHaveCount(3);
});

it('is case insensitive for http methods', function () {
    $attributeRequests = [
        new RequestData(name: 'Attr', method: 'GET', uri: 'api/users'),
    ];

    $yamlRequests = [
        new RequestData(name: 'YAML', method: 'get', uri: 'api/users'),
    ];

    $merger = new RequestMerger;
    $result = $merger->merge($attributeRequests, $yamlRequests);

    expect($result)->toHaveCount(1);
    expect($result[0]->name)->toBe('Attr');
});

it('handles complex merge scenario', function () {
    $attributeRequests = [
        new RequestData(name: 'Attr GET users', method: 'GET', uri: 'api/users'),
        new RequestData(name: 'Attr POST users', method: 'POST', uri: 'api/users'),
        new RequestData(name: 'Attr GET products', method: 'GET', uri: 'api/products'),
    ];

    $yamlRequests = [
        new RequestData(name: 'YAML GET users', method: 'GET', uri: 'api/users'), // Conflict - attr wins
        new RequestData(name: 'YAML DELETE users', method: 'DELETE', uri: 'api/users'), // New
        new RequestData(name: 'YAML GET orders', method: 'GET', uri: 'api/orders'), // New
    ];

    $merger = new RequestMerger;
    $result = $merger->merge($attributeRequests, $yamlRequests);

    expect($result)->toHaveCount(5);

    $names = array_map(fn ($r) => $r->name, $result);
    expect($names)->toContain('Attr GET users');
    expect($names)->toContain('Attr POST users');
    expect($names)->toContain('Attr GET products');
    expect($names)->toContain('YAML DELETE users');
    expect($names)->toContain('YAML GET orders');
    expect($names)->not->toContain('YAML GET users');
});
