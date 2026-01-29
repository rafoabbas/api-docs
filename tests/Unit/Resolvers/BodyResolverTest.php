<?php

declare(strict_types=1);

use ApiDocs\Resolvers\BodyResolver;
use Illuminate\Foundation\Http\FormRequest;

// Test FormRequest fixtures
class TestLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ];
    }
}

class TestUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'nullable|integer',
            'phone' => 'required|string',
            'is_active' => 'boolean',
            'price' => 'numeric',
        ];
    }
}

class TestNestedRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user.name' => 'required|string',
            'user.email' => 'required|email',
            'address.city' => 'required|string',
        ];
    }
}

class TestEnumRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,active,completed',
            'type' => 'required|in:a,b,c',
        ];
    }
}

class TestArrayRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'tags' => 'array',
        ];
    }
}

class TestFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'avatar' => 'required|image',
            'document' => 'file',
        ];
    }
}

class TestController
{
    public function login(TestLoginRequest $request): void {}

    public function store(TestUserRequest $request): void {}

    public function nested(TestNestedRequest $request): void {}

    public function withEnum(TestEnumRequest $request): void {}

    public function withArray(TestArrayRequest $request): void {}

    public function withFile(TestFileRequest $request): void {}

    public function noRequest(): void {}

    public function regularRequest(\Illuminate\Http\Request $request): void {}
}

it('resolves body from form request', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'login');

    $body = $resolver->resolve($method);

    expect($body)->toBeArray();
    expect($body)->toHaveKey('email');
    expect($body)->toHaveKey('password');
});

it('returns null when no form request parameter', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'noRequest');

    $body = $resolver->resolve($method);

    expect($body)->toBeNull();
});

it('returns null for regular request class', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'regularRequest');

    $body = $resolver->resolve($method);

    expect($body)->toBeNull();
});

it('generates email value for email fields', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'login');

    $body = $resolver->resolve($method);

    expect($body['email'])->toBe('user@example.com');
});

it('generates integer for integer fields', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'store');

    $body = $resolver->resolve($method);

    expect($body['age'])->toBeInt();
});

it('generates boolean for boolean fields', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'store');

    $body = $resolver->resolve($method);

    expect($body['is_active'])->toBeBool();
});

it('generates numeric for numeric fields', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'store');

    $body = $resolver->resolve($method);

    expect($body['price'])->toBeNumeric();
});

it('handles nested fields with dot notation', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'nested');

    $body = $resolver->resolve($method);

    expect($body)->toHaveKey('user');
    expect($body['user'])->toHaveKey('name');
    expect($body['user'])->toHaveKey('email');
    expect($body)->toHaveKey('address');
    expect($body['address'])->toHaveKey('city');
});

it('uses first option for enum fields', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'withEnum');

    $body = $resolver->resolve($method);

    expect($body['status'])->toBe('pending');
    expect($body['type'])->toBe('a');
});

it('generates empty array for array fields', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'withArray');

    $body = $resolver->resolve($method);

    expect($body['items'])->toBeArray();
    expect($body['items'])->toBeEmpty();
    expect($body['tags'])->toBeArray();
});

it('generates file placeholder for file fields', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'withFile');

    $body = $resolver->resolve($method);

    expect($body['avatar'])->toBe('(file)');
    expect($body['document'])->toBe('(file)');
});

it('generates phone variable for phone fields', function () {
    $resolver = new BodyResolver;
    $method = new ReflectionMethod(TestController::class, 'store');

    $body = $resolver->resolve($method);

    expect($body['phone'])->toBe('{{PHONE}}');
});