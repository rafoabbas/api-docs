<?php

declare(strict_types=1);

namespace ApiDocs\Resolvers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

final class ResponseResolver
{
    /** @var array<string, bool> Track resolved resources to prevent infinite loops */
    private array $resolvedResources = [];

    /**
     * Resolve response structure from a Resource class.
     *
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    public function resolve(string $resourceClass, bool $wrapped = true): array
    {
        $this->resolvedResources = [];

        $structure = $this->analyzeResourceStructure($resourceClass);

        if ($wrapped) {
            return [
                'success' => true,
                'status_code' => 200,
                'message' => null,
                'data' => $structure,
            ];
        }

        return $structure;
    }

    /**
     * Analyze a Resource class to extract its structure.
     *
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function analyzeResourceStructure(string $resourceClass): array
    {
        if (! class_exists($resourceClass)) {
            return [];
        }

        // Prevent infinite recursion
        if (isset($this->resolvedResources[$resourceClass])) {
            return ['__recursive__' => class_basename($resourceClass)];
        }

        $this->resolvedResources[$resourceClass] = true;

        $reflection = new ReflectionClass($resourceClass);

        if (! $reflection->hasMethod('toArray')) {
            return [];
        }

        $method = $reflection->getMethod('toArray');

        return $this->parseToArrayMethod($method, $resourceClass);
    }

    /**
     * Parse the toArray method to extract field structure.
     *
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function parseToArrayMethod(ReflectionMethod $method, string $resourceClass): array
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if ($filename === false || $startLine === false || $endLine === false) {
            return [];
        }

        $fileSource = file_get_contents($filename);
        if ($fileSource === false) {
            return [];
        }

        $lines = explode("\n", $fileSource);
        $methodSource = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        // Get namespace and use statements for resolving nested resources
        $namespace = $this->extractNamespace($fileSource);
        $useStatements = $this->extractUseStatements($fileSource);

        return $this->extractArrayStructure($methodSource, $resourceClass, $namespace, $useStatements);
    }

    /**
     * Extract namespace from source code.
     */
    private function extractNamespace(string $source): string
    {
        if (preg_match('/namespace\s+([^;]+);/', $source, $match)) {
            return trim($match[1]);
        }

        return '';
    }

    /**
     * Extract use statements from source code.
     *
     * @return array<string, string> Map of class name to full namespace
     */
    private function extractUseStatements(string $source): array
    {
        $uses = [];

        preg_match_all('/use\s+([^;]+);/', $source, $matches);

        foreach ($matches[1] as $use) {
            $use = trim($use);

            // Handle aliased imports: use Foo\Bar as Baz
            if (preg_match('/(.+)\s+as\s+(\w+)$/', $use, $aliasMatch)) {
                $uses[$aliasMatch[2]] = $aliasMatch[1];
            } else {
                // Get the class name from full namespace
                $parts = explode('\\', $use);
                $className = end($parts);
                $uses[$className] = $use;
            }
        }

        return $uses;
    }

    /**
     * Extract array structure from method source code.
     *
     * @param  class-string<JsonResource>  $resourceClass
     * @param  array<string, string>  $useStatements
     * @return array<string, mixed>
     */
    private function extractArrayStructure(
        string $source,
        string $resourceClass,
        string $namespace,
        array $useStatements
    ): array {
        $structure = [];

        // Find array keys in the return statement
        // Pattern: 'key' => or "key" =>
        preg_match_all("/['\"]([^'\"]+)['\"]\s*=>/", $source, $matches);

        if (empty($matches[1])) {
            return [];
        }

        foreach ($matches[1] as $key) {
            $structure[$key] = $this->guessValueForKey($key, $source, $namespace, $useStatements);
        }

        return $structure;
    }

    /**
     * Guess a sensible example value for a key.
     *
     * @param  array<string, string>  $useStatements
     */
    private function guessValueForKey(
        string $key,
        string $source,
        string $namespace,
        array $useStatements
    ): mixed {
        // Check for nested Resource (e.g., CustomerResource::make)
        $resourcePattern = "/['\"]".preg_quote($key, '/')."['\"]\s*=>\s*(\w+Resource)::make/";
        if (preg_match($resourcePattern, $source, $match)) {
            $nestedResourceName = $match[1];
            $nestedResourceClass = $this->resolveResourceClass($nestedResourceName, $namespace, $useStatements);

            if ($nestedResourceClass !== null && class_exists($nestedResourceClass)) {
                return $this->analyzeResourceStructure($nestedResourceClass);
            }

            return ['__resource__' => $nestedResourceName];
        }

        // Check for $this->when pattern
        $whenPattern = "/['\"]".preg_quote($key, '/')."['\"]\s*=>\s*\\\$this->when/";
        if (preg_match($whenPattern, $source)) {
            // Check if when contains a nested resource
            $whenResourcePattern = "/['\"]".preg_quote($key, '/')."['\"]\s*=>\s*\\\$this->when\s*\([^,]+,\s*(\w+Resource)::make/";
            if (preg_match($whenResourcePattern, $source, $match)) {
                $nestedResourceName = $match[1];
                $nestedResourceClass = $this->resolveResourceClass($nestedResourceName, $namespace, $useStatements);

                if ($nestedResourceClass !== null && class_exists($nestedResourceClass)) {
                    return $this->analyzeResourceStructure($nestedResourceClass);
                }
            }

            // It's conditional, try to guess based on key name
            return $this->guessValueByKeyName($key);
        }

        return $this->guessValueByKeyName($key);
    }

    /**
     * Resolve a resource class name to its full namespace.
     *
     * @param  array<string, string>  $useStatements
     */
    private function resolveResourceClass(string $resourceName, string $namespace, array $useStatements): ?string
    {
        // Check if it's in use statements
        if (isset($useStatements[$resourceName])) {
            return $useStatements[$resourceName];
        }

        // Assume it's in the same namespace
        if ($namespace !== '') {
            $fullClass = $namespace.'\\'.$resourceName;
            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        // Try common Resource namespaces
        $commonNamespaces = [
            'App\\Http\\Resources',
            'App\\Http\\Resources\\Api',
            'App\\Http\\Resources\\Api\\V1',
            'App\\Http\\Resources\\Api\\V1\\Customer',
        ];

        foreach ($commonNamespaces as $ns) {
            $fullClass = $ns.'\\'.$resourceName;
            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        return null;
    }

    /**
     * Guess value based on key name patterns.
     */
    private function guessValueByKeyName(string $key): mixed
    {
        $keyLower = Str::lower($key);
        $keySnake = Str::snake($key);

        // Boolean patterns
        $booleanPatterns = [
            'is_', 'has_', 'can_', 'should_', 'needs_', 'allows_', 'enabled', 'active',
            'verified', 'confirmed', 'approved', 'published', 'visible', 'available',
            'allow_',
        ];
        foreach ($booleanPatterns as $pattern) {
            if (Str::contains($keySnake, $pattern) || Str::startsWith($keySnake, $pattern)) {
                return true;
            }
        }

        // ID patterns
        if ($keySnake === 'id' || Str::endsWith($keySnake, '_id')) {
            return 1;
        }

        // UUID patterns
        if (Str::contains($keySnake, 'uuid')) {
            return '550e8400-e29b-41d4-a716-446655440000';
        }

        // Token patterns
        if (Str::contains($keyLower, 'token')) {
            return 'example_token_string';
        }

        // Type patterns (usually string enum)
        if ($keySnake === 'type' || Str::endsWith($keySnake, '_type')) {
            return 'default';
        }

        // Status patterns
        if ($keySnake === 'status' || Str::endsWith($keySnake, '_status')) {
            return 'active';
        }

        // Date/time patterns
        $datePatterns = ['_at', 'date', 'time', 'created', 'updated', 'deleted'];
        foreach ($datePatterns as $pattern) {
            if (Str::contains($keySnake, $pattern)) {
                return '2024-01-15T10:30:00Z';
            }
        }

        // Email patterns
        if (Str::contains($keyLower, 'email')) {
            return 'user@example.com';
        }

        // Phone patterns
        if (Str::contains($keyLower, 'phone')) {
            return '+905551234567';
        }

        // Name patterns
        if (Str::contains($keyLower, 'name')) {
            return 'Example Name';
        }

        // URL patterns
        if (Str::contains($keyLower, ['url', 'link', 'href'])) {
            return 'https://example.com';
        }

        // Image/avatar patterns
        if (Str::contains($keyLower, ['image', 'avatar', 'photo', 'picture'])) {
            return 'https://example.com/image.jpg';
        }

        // Count/total patterns
        if (Str::contains($keySnake, ['count', 'total', 'quantity', 'amount'])) {
            return 10;
        }

        // Price patterns
        if (Str::contains($keyLower, ['price', 'cost', 'fee', 'amount'])) {
            return 99.99;
        }

        // Array/collection patterns (but exclude common non-array fields ending in 's')
        $nonArrayFields = ['status', 'alias', 'address', 'class', 'access', 'process', 'success', 'progress'];
        $isNonArray = false;
        foreach ($nonArrayFields as $field) {
            if (Str::endsWith($keySnake, $field)) {
                $isNonArray = true;
                break;
            }
        }
        if (Str::endsWith($key, 's') && ! $isNonArray) {
            return [];
        }

        // Default to string
        return 'example_value';
    }
}
