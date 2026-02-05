<?php

declare(strict_types=1);

use ApiDocs\Data\QueryParamData;
use ApiDocs\Resolvers\QueryParamResolver;
use Illuminate\Foundation\Http\FormRequest;

// Test FormRequest fixtures for query params
class TestSearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
        ];
    }
}

class TestPlaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'place_id' => ['required', 'string'],
        ];
    }
}

class TestCoordinatesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}

class TestEnumQueryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,active,completed'],
        ];
    }
}

class TestQueryController
{
    public function search(TestSearchRequest $request): void {}

    public function place(TestPlaceRequest $request): void {}

    public function coordinates(TestCoordinatesRequest $request): void {}

    public function withEnum(TestEnumQueryRequest $request): void {}

    public function noRequest(): void {}

    public function regularRequest(\Illuminate\Http\Request $request): void {}
}

it('resolves query params from form request', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'search');

    $params = $resolver->resolve($method);

    expect($params)->toBeArray();
    expect($params)->toHaveCount(5);
    expect($params[0])->toBeInstanceOf(QueryParamData::class);
});

it('returns empty array when no form request parameter', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'noRequest');

    $params = $resolver->resolve($method);

    expect($params)->toBeArray();
    expect($params)->toBeEmpty();
});

it('returns empty array for regular request class', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'regularRequest');

    $params = $resolver->resolve($method);

    expect($params)->toBeArray();
    expect($params)->toBeEmpty();
});

it('marks required params as required and enabled', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'search');

    $params = $resolver->resolve($method);

    $queryParam = collect($params)->firstWhere('key', 'query');

    expect($queryParam)->not->toBeNull();
    expect($queryParam->description)->toBe('Required');
    expect($queryParam->disabled)->toBeFalse();
});

it('marks nullable params as optional and disabled', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'search');

    $params = $resolver->resolve($method);

    $latitudeParam = collect($params)->firstWhere('key', 'latitude');

    expect($latitudeParam)->not->toBeNull();
    expect($latitudeParam->description)->toBe('Optional');
    expect($latitudeParam->disabled)->toBeTrue();
});

it('generates sensible values for latitude', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'coordinates');

    $params = $resolver->resolve($method);

    $latitudeParam = collect($params)->firstWhere('key', 'latitude');

    expect($latitudeParam)->not->toBeNull();
    expect((float) $latitudeParam->value)->toBeGreaterThan(0);
});

it('generates sensible values for longitude', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'coordinates');

    $params = $resolver->resolve($method);

    $longitudeParam = collect($params)->firstWhere('key', 'longitude');

    expect($longitudeParam)->not->toBeNull();
    expect((float) $longitudeParam->value)->toBeGreaterThan(0);
});

it('generates place_id value for place_id field', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'place');

    $params = $resolver->resolve($method);

    $placeIdParam = collect($params)->firstWhere('key', 'place_id');

    expect($placeIdParam)->not->toBeNull();
    expect($placeIdParam->value)->toContain('ChIJ');
});

it('generates page value for page field', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'search');

    $params = $resolver->resolve($method);

    $pageParam = collect($params)->firstWhere('key', 'page');

    expect($pageParam)->not->toBeNull();
    expect($pageParam->value)->toBe('1');
});

it('generates per_page value for per_page field', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'search');

    $params = $resolver->resolve($method);

    $perPageParam = collect($params)->firstWhere('key', 'per_page');

    expect($perPageParam)->not->toBeNull();
    expect($perPageParam->value)->toBe('10');
});

it('uses first option for enum fields', function (): void {
    $resolver = new QueryParamResolver;
    $method = new ReflectionMethod(TestQueryController::class, 'withEnum');

    $params = $resolver->resolve($method);

    $statusParam = collect($params)->firstWhere('key', 'status');

    expect($statusParam)->not->toBeNull();
    expect($statusParam->value)->toBe('pending');
});
