<?php

declare(strict_types=1);

use ApiDocs\Resolvers\ValidationSchemaResolver;
use Illuminate\Foundation\Http\FormRequest;

// Test FormRequest fixtures

class SchemaLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:8|max:64',
        ];
    }
}

class SchemaUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'age' => 'nullable|integer|min:0|max:150',
            'score' => 'numeric|between:0,100',
            'is_active' => 'boolean',
            'tags' => 'array',
        ];
    }
}

class SchemaEnumRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,active,completed',
            'priority' => ['required', 'in:low,medium,high'],
        ];
    }
}

class SchemaFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string',
            'avatar' => 'required|image',
            'document' => 'file',
            'attachment' => 'mimes:pdf,doc',
        ];
    }
}

class SchemaFormatRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'website' => 'url',
            'id' => 'uuid',
            'birthday' => 'date',
            'login_at' => 'date_format:Y-m-d\TH:i:sP',
            'ip_address' => 'ip',
        ];
    }
}

class SchemaRegexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|regex:/^[A-Z]{3}-\d{4}$/',
            'pin' => 'required|digits:6',
        ];
    }
}

class SchemaNestedRequest extends FormRequest
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

class SchemaTestController
{
    public function login(SchemaLoginRequest $request): void {}

    public function store(SchemaUserRequest $request): void {}

    public function withEnum(SchemaEnumRequest $request): void {}

    public function withFile(SchemaFileRequest $request): void {}

    public function withFormats(SchemaFormatRequest $request): void {}

    public function withRegex(SchemaRegexRequest $request): void {}

    public function withNested(SchemaNestedRequest $request): void {}

    public function noRequest(): void {}
}

it('resolves schema from form request', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'login');

    $result = $resolver->resolve($method);

    expect($result)->not->toBeNull();
    expect($result['schema'])->toHaveKey('type', 'object');
    expect($result['schema'])->toHaveKey('properties');
    expect($result['schema']['properties'])->toHaveKey('email');
    expect($result['schema']['properties'])->toHaveKey('password');
});

it('returns null when no form request', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'noRequest');

    $result = $resolver->resolve($method);

    expect($result)->toBeNull();
});

it('detects required fields', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'login');

    $result = $resolver->resolve($method);

    expect($result['schema']['required'])->toContain('email');
    expect($result['schema']['required'])->toContain('password');
});

it('converts email rule to format', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'login');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['email']['type'])->toBe('string');
    expect($result['schema']['properties']['email']['format'])->toBe('email');
});

it('converts min/max rules to constraints', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'login');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['password']['minLength'])->toBe(8);
    expect($result['schema']['properties']['password']['maxLength'])->toBe(64);
});

it('converts integer type correctly', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'store');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['age']['type'])->toBe('integer');
    expect($result['schema']['properties']['age']['nullable'])->toBeTrue();
    expect($result['schema']['properties']['age']['minimum'])->toBe(0);
    expect($result['schema']['properties']['age']['maximum'])->toBe(150);
});

it('converts numeric type with between constraint', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'store');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['score']['type'])->toBe('number');
    expect($result['schema']['properties']['score']['minimum'])->toBe(0);
    expect($result['schema']['properties']['score']['maximum'])->toBe(100);
});

it('converts boolean type correctly', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'store');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['is_active']['type'])->toBe('boolean');
});

it('converts array type correctly', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'store');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['tags']['type'])->toBe('array');
});

it('converts in rule to enum', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'withEnum');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['status']['enum'])->toBe(['pending', 'active', 'completed']);
    expect($result['schema']['properties']['priority']['enum'])->toBe(['low', 'medium', 'high']);
});

it('detects file uploads', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'withFile');

    $result = $resolver->resolve($method);

    expect($result['hasFileUpload'])->toBeTrue();
    expect($result['schema']['properties']['avatar']['format'])->toBe('binary');
    expect($result['schema']['properties']['document']['format'])->toBe('binary');
    expect($result['schema']['properties']['attachment']['format'])->toBe('binary');
    expect($result['schema']['properties']['title'])->not->toHaveKey('format');
});

it('converts various format rules', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'withFormats');

    $result = $resolver->resolve($method);
    $properties = $result['schema']['properties'];

    expect($properties['email']['format'])->toBe('email');
    expect($properties['website']['format'])->toBe('uri');
    expect($properties['id']['format'])->toBe('uuid');
    expect($properties['birthday']['format'])->toBe('date');
    expect($properties['login_at']['format'])->toBe('date-time');
    expect($properties['ip_address']['format'])->toBe('ipv4');
});

it('converts regex rule to pattern', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'withRegex');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['code']['pattern'])->toBe('^[A-Z]{3}-\d{4}$');
});

it('converts digits rule to pattern', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'withRegex');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['pin']['pattern'])->toBe('^\d{6}$');
});

it('handles nested fields', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'withNested');

    $result = $resolver->resolve($method);
    $properties = $result['schema']['properties'];

    expect($properties)->toHaveKey('user');
    expect($properties['user']['type'])->toBe('object');
    expect($properties['user']['properties'])->toHaveKey('name');
    expect($properties['user']['properties'])->toHaveKey('email');
    expect($properties)->toHaveKey('address');
    expect($properties['address']['properties'])->toHaveKey('city');
});

it('does not report file upload when no file rules', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'login');

    $result = $resolver->resolve($method);

    expect($result['hasFileUpload'])->toBeFalse();
});

it('generates example values', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'login');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['email']['example'])->toBe('user@example.com');
    expect($result['schema']['properties']['password']['example'])->toBe('password123');
});

it('uses first enum value as example', function (): void {
    $resolver = new ValidationSchemaResolver;
    $method = new ReflectionMethod(SchemaTestController::class, 'withEnum');

    $result = $resolver->resolve($method);

    expect($result['schema']['properties']['status']['example'])->toBe('pending');
});
