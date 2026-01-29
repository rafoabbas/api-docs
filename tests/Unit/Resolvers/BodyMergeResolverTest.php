<?php

declare(strict_types=1);

use ApiDocs\Attributes\ApiBody;
use ApiDocs\Resolvers\BodyMergeResolver;
use Illuminate\Foundation\Http\FormRequest;

// Test FormRequest fixture
class MergeTestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
        ];
    }
}

class MergeTestController
{
    public function store(MergeTestRequest $request): void {}

    public function noRequest(): void {}
}

it('returns null for GET requests', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $body = $resolver->resolve($method, null, 'GET');

    expect($body)->toBeNull();
});

it('returns null for DELETE requests', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $body = $resolver->resolve($method, null, 'DELETE');

    expect($body)->toBeNull();
});

it('resolves body for POST requests', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $body = $resolver->resolve($method, null, 'POST');

    expect($body)->toBeArray();
    expect($body)->toHaveKey('name');
    expect($body)->toHaveKey('email');
});

it('resolves body for PUT requests', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $body = $resolver->resolve($method, null, 'PUT');

    expect($body)->toBeArray();
});

it('resolves body for PATCH requests', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $body = $resolver->resolve($method, null, 'PATCH');

    expect($body)->toBeArray();
});

it('uses only ApiBody data when merge is false', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $apiBody = new ApiBody(
        data: ['custom_field' => 'custom_value'],
        merge: false,
    );

    $body = $resolver->resolve($method, $apiBody, 'POST');

    expect($body)->toBe(['custom_field' => 'custom_value']);
    expect($body)->not->toHaveKey('name');
    expect($body)->not->toHaveKey('email');
});

it('merges ApiBody with FormRequest when merge is true', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $apiBody = new ApiBody(
        data: ['custom_field' => 'custom_value'],
        merge: true,
    );

    $body = $resolver->resolve($method, $apiBody, 'POST');

    // Should have both FormRequest fields and custom field
    expect($body)->toHaveKey('name');
    expect($body)->toHaveKey('email');
    expect($body)->toHaveKey('custom_field');
    expect($body['custom_field'])->toBe('custom_value');
});

it('ApiBody data overrides FormRequest fields when merging', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $apiBody = new ApiBody(
        data: ['email' => 'override@example.com'],
        merge: true,
    );

    $body = $resolver->resolve($method, $apiBody, 'POST');

    expect($body['email'])->toBe('override@example.com');
});

it('applies except filter when merging', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $apiBody = new ApiBody(
        data: ['custom' => 'value'],
        merge: true,
        except: ['phone'],
    );

    $body = $resolver->resolve($method, $apiBody, 'POST');

    expect($body)->toHaveKey('name');
    expect($body)->toHaveKey('email');
    expect($body)->not->toHaveKey('phone');
    expect($body)->toHaveKey('custom');
});

it('applies multiple except filters', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $apiBody = new ApiBody(
        data: [],
        merge: true,
        except: ['name', 'phone'],
    );

    $body = $resolver->resolve($method, $apiBody, 'POST');

    expect($body)->not->toHaveKey('name');
    expect($body)->not->toHaveKey('phone');
    expect($body)->toHaveKey('email');
});

it('returns empty array when ApiBody has no data and merge is false', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'store');

    $apiBody = new ApiBody(data: [], merge: false);

    $body = $resolver->resolve($method, $apiBody, 'POST');

    expect($body)->toBe([]);
});

it('handles method without FormRequest when merge is true', function (): void {
    $resolver = new BodyMergeResolver;
    $method = new ReflectionMethod(MergeTestController::class, 'noRequest');

    $apiBody = new ApiBody(
        data: ['custom' => 'value'],
        merge: true,
    );

    $body = $resolver->resolve($method, $apiBody, 'POST');

    expect($body)->toBe(['custom' => 'value']);
});
