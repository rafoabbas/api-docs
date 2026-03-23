<?php

declare(strict_types=1);

use ApiDocs\Attributes\ApiDeprecated;

it('can be instantiated with no arguments', function (): void {
    $attr = new ApiDeprecated;

    expect($attr)->toBeInstanceOf(ApiDeprecated::class);
    expect($attr->reason)->toBeNull();
    expect($attr->replacement)->toBeNull();
});

it('stores reason', function (): void {
    $attr = new ApiDeprecated(reason: 'Use v2 API');

    expect($attr->reason)->toBe('Use v2 API');
});

it('stores replacement', function (): void {
    $attr = new ApiDeprecated(replacement: '/v2/users');

    expect($attr->replacement)->toBe('/v2/users');
});

it('stores both reason and replacement', function (): void {
    $attr = new ApiDeprecated(reason: 'Outdated', replacement: '/v2/users');

    expect($attr->reason)->toBe('Outdated');
    expect($attr->replacement)->toBe('/v2/users');
});

it('is a valid attribute for class and method targets', function (): void {
    $reflection = new ReflectionClass(ApiDeprecated::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->not->toBeEmpty();
});
