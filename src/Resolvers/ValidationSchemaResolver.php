<?php

declare(strict_types=1);

namespace ApiDocs\Resolvers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class ValidationSchemaResolver
{
    /**
     * Resolve OpenAPI schema from a controller method's FormRequest.
     *
     * @return array{schema: array<string, mixed>, hasFileUpload: bool}|null
     */
    public function resolve(ReflectionMethod $method): ?array
    {
        $formRequestClass = $this->findFormRequestParameter($method);

        if ($formRequestClass === null) {
            return null;
        }

        return $this->buildSchemaFromRules($formRequestClass);
    }

    /**
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
     * @param  class-string<FormRequest>  $formRequestClass
     * @return array{schema: array<string, mixed>, hasFileUpload: bool}
     */
    private function buildSchemaFromRules(string $formRequestClass): array
    {
        $reflection = new ReflectionClass($formRequestClass);

        if (! $reflection->hasMethod('rules')) {
            return ['schema' => ['type' => 'object'], 'hasFileUpload' => false];
        }

        try {
            $instance = new $formRequestClass;
            $instance->replace([]);
            $rules = $instance->rules();
        } catch (\Throwable) {
            return ['schema' => ['type' => 'object'], 'hasFileUpload' => false];
        }

        return $this->rulesToSchema($rules);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{schema: array<string, mixed>, hasFileUpload: bool}
     */
    private function rulesToSchema(array $rules): array
    {
        $properties = [];
        $required = [];
        $hasFileUpload = false;

        foreach ($rules as $field => $fieldRules) {
            if (Str::contains($field, '*')) {
                continue;
            }

            $rulesArray = $this->normalizeRules($fieldRules);
            $property = $this->rulesToProperty($field, $rulesArray);

            if (in_array('required', $rulesArray)) {
                $required[] = $field;
            }

            if ($this->isFileRule($rulesArray)) {
                $hasFileUpload = true;
            }

            if (Str::contains($field, '.')) {
                $this->setNestedProperty($properties, $field, $property);
            } else {
                $properties[$field] = $property;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        $topLevelRequired = array_filter($required, fn (string $f): bool => ! Str::contains($f, '.'));

        if (count($topLevelRequired) > 0) {
            $schema['required'] = array_values($topLevelRequired);
        }

        return ['schema' => $schema, 'hasFileUpload' => $hasFileUpload];
    }

    /**
     * Convert a single field's rules to an OpenAPI property schema.
     *
     * @param  array<int, mixed>  $rules
     * @return array<string, mixed>
     */
    private function rulesToProperty(string $field, array $rules): array
    {
        $property = [];

        $type = $this->determineType($rules);
        $property['type'] = $type;

        $format = $this->determineFormat($field, $rules);

        if ($format !== null) {
            $property['format'] = $format;
        }

        if (in_array('nullable', $rules)) {
            $property['nullable'] = true;
        }

        $enum = $this->extractEnum($rules);

        if ($enum !== null) {
            $property['enum'] = $enum;
        }

        $this->applyConstraints($property, $rules, $type);

        $pattern = $this->extractPattern($rules);

        if ($pattern !== null) {
            $property['pattern'] = $pattern;
        }

        $property['example'] = $this->generateExample($field, $rules, $type, $enum);

        return $property;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function determineType(array $rules): string
    {
        if (in_array('boolean', $rules) || in_array('bool', $rules)) {
            return 'boolean';
        }

        if (in_array('integer', $rules) || in_array('int', $rules)) {
            return 'integer';
        }

        if (in_array('numeric', $rules)) {
            return 'number';
        }

        if (in_array('array', $rules)) {
            return 'array';
        }

        return 'string';
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function determineFormat(string $field, array $rules): ?string
    {
        if (in_array('email', $rules)) {
            return 'email';
        }

        if (in_array('url', $rules) || in_array('active_url', $rules)) {
            return 'uri';
        }

        if (in_array('uuid', $rules)) {
            return 'uuid';
        }

        if (in_array('ip', $rules) || in_array('ipv4', $rules)) {
            return 'ipv4';
        }

        if (in_array('ipv6', $rules)) {
            return 'ipv6';
        }

        if ($this->isFileRule($rules)) {
            return 'binary';
        }

        if (in_array('date', $rules)) {
            return 'date';
        }

        foreach ($rules as $rule) {
            if (is_string($rule) && Str::startsWith($rule, 'date_format:')) {
                $format = Str::after($rule, 'date_format:');

                if (Str::contains($format, ['H', 'h', 'T'])) {
                    return 'date-time';
                }

                return 'date';
            }
        }

        if (Str::contains(Str::lower($field), 'password')) {
            return 'password';
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<string|int>|null
     */
    private function extractEnum(array $rules): ?array
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && Str::startsWith($rule, 'in:')) {
                return explode(',', Str::after($rule, 'in:'));
            }

            if (is_object($rule)) {
                // Skip objects that can't be cast to string (e.g. ConditionalRules)
                if (! method_exists($rule, '__toString')) {
                    if ($rule instanceof \Illuminate\Validation\Rules\Enum) {
                        $enumValues = $this->extractEnumValues($rule);

                        if ($enumValues !== null) {
                            return $enumValues;
                        }
                    }

                    continue;
                }

                // Laravel Rule::in() → casts to "in:a,b,c"
                $ruleString = (string) $rule;

                if (Str::startsWith($ruleString, 'in:')) {
                    $values = explode(',', Str::after($ruleString, 'in:'));

                    return array_map(fn ($v): string => trim($v, '"\''), $values);
                }

                // Illuminate\Validation\Rules\Enum
                if ($rule instanceof \Illuminate\Validation\Rules\Enum) {
                    $enumValues = $this->extractEnumValues($rule);

                    if ($enumValues !== null) {
                        return $enumValues;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract enum values from Illuminate\Validation\Rules\Enum rule.
     *
     * @return array<string|int>|null
     */
    private function extractEnumValues(object $rule): ?array
    {
        try {
            $reflection = new ReflectionClass($rule);
            $typeProperty = $reflection->getProperty('type');
            $enumClass = $typeProperty->getValue($rule);

            if (is_string($enumClass) && enum_exists($enumClass)) {
                return $this->getBackedEnumValues($enumClass);
            }
        } catch (\Throwable) {
            // Fallback
        }

        return null;
    }

    /**
     * Get values from a BackedEnum class.
     *
     * @return array<string|int>|null
     */
    private function getBackedEnumValues(string $enumClass): ?array
    {
        if (! enum_exists($enumClass)) {
            return null;
        }

        try {
            $reflection = new \ReflectionEnum($enumClass);

            if (! $reflection->isBacked()) {
                return array_map(
                    fn (\ReflectionEnumUnitCase $case): string => $case->getName(),
                    $reflection->getCases(),
                );
            }

            return array_map(
                fn ($case) => $case->value,
                $enumClass::cases(),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Apply min/max/between constraints to the property.
     *
     * @param  array<string, mixed>  $property
     * @param  array<int, mixed>  $rules
     */
    private function applyConstraints(array &$property, array $rules, string $type): void
    {
        $isNumeric = in_array($type, ['integer', 'number']);

        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            if (Str::startsWith($rule, 'min:')) {
                $value = (int) Str::after($rule, 'min:');

                if ($isNumeric) {
                    $property['minimum'] = $value;
                } else {
                    $property['minLength'] = $value;
                }
            }

            if (Str::startsWith($rule, 'max:')) {
                $value = (int) Str::after($rule, 'max:');

                if ($isNumeric) {
                    $property['maximum'] = $value;
                } else {
                    $property['maxLength'] = $value;
                }
            }

            if (Str::startsWith($rule, 'between:')) {
                $parts = explode(',', Str::after($rule, 'between:'));

                if (count($parts) === 2) {
                    if ($isNumeric) {
                        $property['minimum'] = (int) $parts[0];
                        $property['maximum'] = (int) $parts[1];
                    } else {
                        $property['minLength'] = (int) $parts[0];
                        $property['maxLength'] = (int) $parts[1];
                    }
                }
            }

            if (Str::startsWith($rule, 'size:')) {
                $value = (int) Str::after($rule, 'size:');

                if ($isNumeric) {
                    $property['minimum'] = $value;
                    $property['maximum'] = $value;
                } elseif ($type === 'string') {
                    $property['minLength'] = $value;
                    $property['maxLength'] = $value;
                }
            }

            if (Str::startsWith($rule, 'digits:')) {
                $digits = (int) Str::after($rule, 'digits:');
                $property['pattern'] = '^\d{' . $digits . '}$';
            }

            if (Str::startsWith($rule, 'digits_between:')) {
                $parts = explode(',', Str::after($rule, 'digits_between:'));

                if (count($parts) === 2) {
                    $property['pattern'] = '^\d{' . $parts[0] . ',' . $parts[1] . '}$';
                }
            }
        }
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function extractPattern(array $rules): ?string
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && Str::startsWith($rule, 'regex:')) {
                $pattern = Str::after($rule, 'regex:');

                return (string) preg_replace('/^\/(.+)\/$/', '$1', $pattern);
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function isFileRule(array $rules): bool
    {
        if (in_array('file', $rules) || in_array('image', $rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            if (is_string($rule) && (Str::startsWith($rule, 'mimes:') || Str::startsWith($rule, 'mimetypes:'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $rules
     * @param  array<string|int>|null  $enum
     */
    private function generateExample(string $field, array $rules, string $type, ?array $enum): mixed
    {
        if ($enum !== null && count($enum) > 0) {
            return $enum[0];
        }

        if ($type === 'boolean') {
            return true;
        }

        if ($type === 'integer') {
            return $this->guessIntegerExample($field);
        }

        if ($type === 'number') {
            return $this->guessNumericExample($field);
        }

        if ($type === 'array') {
            return [];
        }

        return $this->guessStringExample($field, $rules);
    }

    private function guessIntegerExample(string $field): int
    {
        $lower = Str::lower($field);

        if (Str::contains($lower, ['per_page', 'limit'])) {
            return 10;
        }

        if (Str::contains($lower, ['page'])) {
            return 1;
        }

        if (Str::contains($lower, ['age'])) {
            return 25;
        }

        return 1;
    }

    private function guessNumericExample(string $field): float|int
    {
        $lower = Str::lower($field);

        if (Str::contains($lower, ['price', 'amount', 'total', 'cost'])) {
            return 99.99;
        }

        if (Str::contains($lower, ['lat', 'latitude'])) {
            return 41.0082;
        }

        if (Str::contains($lower, ['lng', 'lon', 'longitude'])) {
            return 28.9784;
        }

        return 0;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function guessStringExample(string $field, array $rules): string
    {
        if (in_array('email', $rules)) {
            return 'user@example.com';
        }

        if (in_array('url', $rules) || in_array('active_url', $rules)) {
            return 'https://example.com';
        }

        if (in_array('uuid', $rules)) {
            return '550e8400-e29b-41d4-a716-446655440000';
        }

        if ($this->isFileRule($rules)) {
            return '(binary)';
        }

        $lower = Str::lower($field);
        $snake = Str::snake($field);

        $patterns = [
            'email' => 'user@example.com',
            'phone' => '+905551234567',
            'password' => 'password123',
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'title' => 'Example Title',
            'description' => 'Example description',
            'address' => '123 Main Street',
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'url' => 'https://example.com',
            'token' => 'abc123token',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ];

        foreach ($patterns as $pattern => $value) {
            if (Str::contains($lower, $pattern) || Str::contains($snake, $pattern)) {
                return $value;
            }
        }

        return 'string';
    }

    /**
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
     * Set a nested property in the schema using dot notation.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $property
     */
    private function setNestedProperty(array &$properties, string $field, array $property): void
    {
        $keys = explode('.', $field);
        $current = &$properties;

        for ($i = 0; $i < count($keys) - 1; $i++) {
            $key = $keys[$i];

            if (! isset($current[$key])) {
                $current[$key] = [
                    'type' => 'object',
                    'properties' => [],
                ];
            }

            $current = &$current[$key]['properties'];
        }

        $current[end($keys)] = $property;
    }
}