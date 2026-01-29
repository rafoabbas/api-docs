<?php

declare(strict_types=1);

use ApiDocs\Resolvers\ResponseResolver;
use Illuminate\Http\Resources\Json\JsonResource;

// Test Resource fixtures
class TestUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
        ];
    }
}

class TestProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_verified' => $this->is_verified,
        ];
    }
}

class TestOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_price' => $this->total_price,
        ];
    }
}

it('resolves resource structure with wrapping', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestUserResource::class, wrapped: true);

    expect($result)->toHaveKey('success');
    expect($result)->toHaveKey('status_code');
    expect($result)->toHaveKey('message');
    expect($result)->toHaveKey('data');
    expect($result['success'])->toBeTrue();
    expect($result['status_code'])->toBe(200);
});

it('resolves resource structure without wrapping', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestUserResource::class, wrapped: false);

    expect($result)->not->toHaveKey('success');
    expect($result)->not->toHaveKey('data');
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('name');
    expect($result)->toHaveKey('email');
});

it('generates example values based on field names', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestUserResource::class, wrapped: false);

    expect($result['id'])->toBeInt();
    expect($result['email'])->toBe('user@example.com');
});

it('generates datetime for _at fields', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestUserResource::class, wrapped: false);

    expect($result['created_at'])->toContain('2024');
});

it('generates phone value for phone fields', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestProfileResource::class, wrapped: false);

    expect($result['phone'])->toContain('+');
});

it('generates url for url fields', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestProfileResource::class, wrapped: false);

    expect($result['avatar_url'])->toContain('https://');
});

it('generates boolean for is_ fields', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestProfileResource::class, wrapped: false);

    expect($result['is_verified'])->toBeBool();
});

it('generates status string for status fields', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestOrderResource::class, wrapped: false);

    expect($result['status'])->toBe('active');
});

it('generates price value for price fields', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestOrderResource::class, wrapped: false);

    expect($result['total_price'])->toBeNumeric();
});

it('returns empty array for non-existent class', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve('NonExistentResource', wrapped: false);

    expect($result)->toBeEmpty();
});

it('wraps data in standard format', function (): void {
    $resolver = new ResponseResolver;
    $result = $resolver->resolve(TestUserResource::class, wrapped: true);

    expect($result['data'])->toHaveKey('id');
    expect($result['data'])->toHaveKey('name');
    expect($result['data'])->toHaveKey('email');
});
