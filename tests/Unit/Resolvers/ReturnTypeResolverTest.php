<?php

declare(strict_types=1);

use ApiDocs\Resolvers\ReturnTypeResolver;
use ApiDocs\Tests\Fixtures\ReturnType\TestReturnController;
use ApiDocs\Tests\Fixtures\ReturnType\TestUserResource;

it('detects resource make return', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'resourceMake');
    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('resource')
        ->and($result['isCollection'])->toBeFalse()
        ->and($result['resourceClass'])->toBe(TestUserResource::class)
        ->and($result['data'])->toBeArray()
        ->and($result['data'])->toHaveKeys(['id', 'name']);
});

it('detects new resource return', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'resourceNew');
    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('resource')
        ->and($result['isCollection'])->toBeFalse()
        ->and($result['resourceClass'])->toBe(TestUserResource::class);
});

it('detects resource collection return', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'resourceCollection');
    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('resource')
        ->and($result['isCollection'])->toBeTrue()
        ->and($result['data'])->toBeArray();
});

it('detects response json return', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'responseJson');
    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('json')
        ->and($result['data'])->toHaveKeys(['message', 'status'])
        ->and($result['wrapped'])->toBeFalse();
});

it('detects array return', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'arrayReturn');
    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull()
        ->and($result['type'])->toBe('array')
        ->and($result['data'])->toHaveKeys(['message', 'success'])
        ->and($result['wrapped'])->toBeFalse();
});

it('detects pagination with paginate', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'withPaginate');
    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull()
        ->and($result['isPaginated'])->toBeTrue();
});

it('detects pagination with simple paginate', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'withSimplePaginate');
    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull()
        ->and($result['isPaginated'])->toBeTrue();
});

it('detects pagination with cursor paginate', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'withCursorPaginate');
    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull()
        ->and($result['isPaginated'])->toBeTrue();
});

it('returns null for void method', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'noReturn');
    $result = $resolver->resolve($method);

    expect($result)->toBeNull();
});

it('returns null for non-pattern return', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'stringReturn');
    $result = $resolver->resolve($method);

    expect($result)->toBeNull();
});

it('sets isPaginated false when no pagination', function () {
    $resolver = new ReturnTypeResolver;
    $method = new ReflectionMethod(TestReturnController::class, 'resourceMake');
    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull()
        ->and($result['isPaginated'])->toBeFalse();
});
