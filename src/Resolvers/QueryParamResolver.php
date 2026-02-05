<?php

declare(strict_types=1);

namespace ApiDocs\Resolvers;

use ApiDocs\Data\QueryParamData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class QueryParamResolver
{
    /**
     * Resolve query parameters from a controller method's FormRequest parameter.
     *
     * @return array<int, QueryParamData>
     */
    public function resolve(ReflectionMethod $method): array
    {
        $formRequestClass = $this->findFormRequestParameter($method);

        if ($formRequestClass === null) {
            return [];
        }

        return $this->extractQueryParamsFromRules($formRequestClass);
    }

    /**
     * Find the FormRequest class from method parameters.
     *
     * @return class-string<FormRequest>|null
     */
    private function findFormRequestParameter(ReflectionMethod $method): ?string
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();

            if (! class_exists($typeName)) {
                continue;
            }

            if (is_subclass_of($typeName, FormRequest::class)) {
                return $typeName;
            }
        }

        return null;
    }

    /**
     * Extract query parameters from FormRequest rules.
     *
     * @param  class-string<FormRequest>  $formRequestClass
     * @return array<int, QueryParamData>
     */
    private function extractQueryParamsFromRules(string $formRequestClass): array
    {
        $reflection = new ReflectionClass($formRequestClass);

        if (! $reflection->hasMethod('rules')) {
            return [];
        }

        try {
            $instance = new $formRequestClass;
            $instance->replace([]);
            $rules = $instance->rules();
        } catch (\Throwable) {
            return $this->parseRulesFromSource($reflection);
        }

        return $this->rulesToQueryParams($rules);
    }

    /**
     * Parse rules from source code when instantiation fails.
     *
     * @param  ReflectionClass<FormRequest>  $reflection
     * @return array<int, QueryParamData>
     */
    private function parseRulesFromSource(ReflectionClass $reflection): array
    {
        if (! $reflection->hasMethod('rules')) {
            return [];
        }

        $method = $reflection->getMethod('rules');
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if ($filename === false || $startLine === false || $endLine === false) {
            return [];
        }

        $source = file_get_contents($filename);

        if ($source === false) {
            return [];
        }

        $lines = explode("\n", $source);
        $methodSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        preg_match_all("/['\"]([a-zA-Z_][a-zA-Z0-9_\.\*]*)['\"]\s*=>/", $methodSource, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $params = [];

        foreach ($matches[1] as $field) {
            if (Str::contains($field, '*')) {
                continue;
            }

            $value = $this->guessStringValue($field, []);
            $params[] = new QueryParamData(
                key: $field,
                value: (string) $value,
                description: null,
                disabled: false,
            );
        }

        return $params;
    }

    /**
     * Convert validation rules to query parameters.
     *
     * @param  array<string, mixed>  $rules
     * @return array<int, QueryParamData>
     */
    private function rulesToQueryParams(array $rules): array
    {
        $params = [];

        foreach ($rules as $field => $fieldRules) {
            if (Str::contains($field, '*') || Str::contains($field, '.')) {
                continue;
            }

            $rulesArray = $this->normalizeRules($fieldRules);
            $isRequired = in_array('required', $rulesArray);
            $isNullable = in_array('nullable', $rulesArray);

            $value = $this->generateExampleValue($field, $fieldRules);

            $params[] = new QueryParamData(
                key: $field,
                value: (string) $value,
                description: $isRequired ? 'Required' : 'Optional',
                disabled: ! $isRequired && $isNullable,
            );
        }

        return $params;
    }

    /**
     * Generate an example value based on field name and rules.
     */
    private function generateExampleValue(string $field, mixed $rules): mixed
    {
        $rulesArray = $this->normalizeRules($rules);
        $rulesString = implode('|', array_map(fn ($r): string => is_string($r) ? $r : '', $rulesArray));

        if (in_array('boolean', $rulesArray) || in_array('bool', $rulesArray)) {
            return 'true';
        }

        if (in_array('integer', $rulesArray) || in_array('int', $rulesArray)) {
            return $this->guessIntegerValue($field);
        }

        if (in_array('numeric', $rulesArray)) {
            return $this->guessNumericValue($field);
        }

        if (in_array('date', $rulesArray) || Str::contains($rulesString, 'date_format')) {
            return '2024-01-15';
        }

        return $this->guessStringValue($field, $rulesArray);
    }

    /**
     * Normalize rules to an array.
     *
     * @return array<int, mixed>
     */
    private function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        if (is_array($rules)) {
            return $rules;
        }

        return [$rules];
    }

    /**
     * Guess a string value based on field name.
     *
     * @param  array<int, mixed>  $rules
     */
    private function guessStringValue(string $field, array $rules): string
    {
        $fieldLower = Str::lower($field);
        $fieldSnake = Str::snake($field);

        $patterns = [
            'query' => 'search term',
            'search' => 'search term',
            'q' => 'search term',
            'email' => 'user@example.com',
            'phone' => '+905551234567',
            'name' => 'John Doe',
            'token' => 'abc123token',
            'session_token' => 'session_abc123',
            'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ];

        foreach ($patterns as $pattern => $value) {
            if (Str::contains($fieldLower, $pattern) || Str::contains($fieldSnake, $pattern)) {
                return $value;
            }
        }

        foreach ($rules as $rule) {
            if (is_string($rule) && Str::startsWith($rule, 'in:')) {
                $options = explode(',', Str::after($rule, 'in:'));

                return $options[0] ?? 'value';
            }
        }

        return 'value';
    }

    /**
     * Guess an integer value based on field name.
     */
    private function guessIntegerValue(string $field): int
    {
        $fieldLower = Str::lower($field);

        if (Str::contains($fieldLower, ['id', 'count', 'quantity', 'qty'])) {
            return 1;
        }

        // Check per_page/limit before page (since per_page contains 'page')
        if (Str::contains($fieldLower, ['per_page', 'limit'])) {
            return 10;
        }

        if (Str::contains($fieldLower, ['page'])) {
            return 1;
        }

        return 1;
    }

    /**
     * Guess a numeric value based on field name.
     */
    private function guessNumericValue(string $field): float|int
    {
        $fieldLower = Str::lower($field);

        if (Str::contains($fieldLower, ['lat', 'latitude'])) {
            return 41.0082;
        }

        if (Str::contains($fieldLower, ['lng', 'lon', 'longitude'])) {
            return 28.9784;
        }

        if (Str::contains($fieldLower, ['price', 'amount', 'total', 'cost'])) {
            return 99.99;
        }

        return 0;
    }
}
