<?php

declare(strict_types=1);

namespace ApiDocs\Resolvers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class BodyResolver
{
    private readonly ExampleValueGenerator $exampleValueGenerator;

    public function __construct()
    {
        $this->exampleValueGenerator = new ExampleValueGenerator;
    }

    /**
     * Resolve body data from a controller method's FormRequest parameter.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(ReflectionMethod $method): ?array
    {
        $formRequestClass = $this->findFormRequestParameter($method);

        if ($formRequestClass === null) {
            return null;
        }

        return $this->extractBodyFromRules($formRequestClass);
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
     * Extract body fields from FormRequest rules.
     *
     * @param  class-string<FormRequest>  $formRequestClass
     * @return array<string, mixed>
     */
    private function extractBodyFromRules(string $formRequestClass): array
    {
        $reflection = new ReflectionClass($formRequestClass);

        if (! $reflection->hasMethod('rules')) {
            return [];
        }

        // Try to get rules from a mock instance
        // This might fail if rules() depends on request data
        try {
            // Create a mock request with empty data
            $instance = new $formRequestClass;

            // Replace request data with empty array to avoid errors
            $instance->replace([]);

            $rules = $instance->rules();
        } catch (\Throwable) {
            // If rules() fails, try to parse the source code directly
            return $this->parseRulesFromSource($reflection);
        }

        return $this->rulesToExampleBody($rules);
    }

    /**
     * Parse rules from source code when instantiation fails.
     *
     * @param  ReflectionClass<FormRequest>  $reflection
     * @return array<string, mixed>
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

        // Find array keys in the return statement
        // Pattern: 'key' => or "key" =>
        preg_match_all("/['\"]([a-zA-Z_][a-zA-Z0-9_\.\*]*)['\"]\s*=>/", $methodSource, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $body = [];

        foreach ($matches[1] as $field) {
            // Skip wildcard fields
            if (Str::contains($field, '*')) {
                continue;
            }

            $value = $this->exampleValueGenerator->string($field);
            $this->setNestedValue($body, $field, $value);
        }

        return $body;
    }

    /**
     * Convert validation rules to example body data.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function rulesToExampleBody(array $rules): array
    {
        $body = [];

        foreach ($rules as $field => $fieldRules) {
            // Handle nested fields (e.g., 'address.city')
            $value = $this->generateExampleValue($field, $fieldRules);
            $this->setNestedValue($body, $field, $value);
        }

        return $body;
    }

    /**
     * Generate an example value based on field name and rules.
     */
    private function generateExampleValue(string $field, mixed $rules): mixed
    {
        $rulesArray = $this->normalizeRules($rules);
        $rulesString = implode('|', array_map(fn ($r): string => is_string($r) ? $r : '', $rulesArray));

        // Check for array type
        if (in_array('array', $rulesArray)) {
            return [];
        }

        // Check for boolean
        if (in_array('boolean', $rulesArray) || in_array('bool', $rulesArray)) {
            return true;
        }

        // Check for integer
        if (in_array('integer', $rulesArray) || in_array('int', $rulesArray)) {
            return $this->exampleValueGenerator->integer($field);
        }

        // Check for numeric
        if (in_array('numeric', $rulesArray)) {
            return $this->exampleValueGenerator->numeric($field);
        }

        // Check for file/image
        if (in_array('file', $rulesArray) || in_array('image', $rulesArray)) {
            return '(file)';
        }

        // Check for date
        if (in_array('date', $rulesArray) || Str::contains($rulesString, 'date_format')) {
            return '2024-01-15';
        }

        // Default to string - try to guess a sensible value
        return $this->exampleValueGenerator->string($field, $rulesArray);
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
     * Set a nested array value using dot notation.
     *
     * @param  array<string, mixed>  $array
     */
    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);

        // Handle array notation like 'items.*'
        if (in_array('*', $keys)) {
            return; // Skip wildcard fields for now
        }

        $current = &$array;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (! isset($current[$k]) || ! is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }
}
