<?php

declare(strict_types=1);

namespace ApiDocs\Resolvers;

use ReflectionMethod;

final readonly class ReturnTypeResolver
{
    public function __construct(
        private ResponseResolver $responseResolver = new ResponseResolver,
    ) {}

    /**
     * Resolve response from method's return statement.
     *
     * @return array{type: string, data: array<string, mixed>, resourceClass: string|null, wrapped: bool}|null
     */
    public function resolve(ReflectionMethod $method): ?array
    {
        $source = $this->getMethodSource($method);

        if ($source === null) {
            return null;
        }

        // Check for Resource::make(), new Resource(), or Resource::collection() return
        if ($resourceResult = $this->detectResourceReturn($source, $method)) {
            $resourceData = $this->responseResolver->resolve($resourceResult['class'], wrapped: false);

            return [
                'type' => 'resource',
                'data' => $resourceResult['isCollection'] ? [$resourceData] : $resourceData,
                'resourceClass' => $resourceResult['class'],
                'wrapped' => false,
                'isCollection' => $resourceResult['isCollection'],
            ];
        }

        // Check for ApiResponse::success() with Resource
        if ($result = $this->detectApiResponseWithResource($source, $method)) {
            return $result;
        }

        // Check for response()->json([...])
        if ($data = $this->detectResponseJson($source)) {
            return [
                'type' => 'json',
                'data' => $data,
                'resourceClass' => null,
                'wrapped' => false,
            ];
        }

        // Check for array return
        if ($data = $this->detectArrayReturn($source)) {
            return [
                'type' => 'array',
                'data' => $data,
                'resourceClass' => null,
                'wrapped' => false,
            ];
        }

        return null;
    }

    private function getMethodSource(ReflectionMethod $method): ?string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if ($filename === false || $startLine === false || $endLine === false) {
            return null;
        }

        $source = file_get_contents($filename);

        if ($source === false) {
            return null;
        }

        $lines = explode("\n", $source);

        return implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }

    /**
     * Get the full file source for namespace resolution.
     */
    private function getFileSource(ReflectionMethod $method): ?string
    {
        $filename = $method->getFileName();

        if ($filename === false) {
            return null;
        }

        return file_get_contents($filename) ?: null;
    }

    /**
     * Detect direct Resource return: return SomeResource::make(), new SomeResource(), or SomeResource::collection()
     *
     * @return array{class: string, isCollection: bool}|null
     */
    private function detectResourceReturn(string $source, ReflectionMethod $method): ?array
    {
        // Pattern: return SomeResource::collection(
        if (preg_match('/return\s+(\w+Resource)::collection\s*\(/i', $source, $match)) {
            $resourceClass = $this->resolveResourceClass($match[1], $method);

            return $resourceClass !== null ? ['class' => $resourceClass, 'isCollection' => true] : null;
        }

        // Pattern: return SomeResource::make(
        if (preg_match('/return\s+(\w+Resource)::make\s*\(/i', $source, $match)) {
            $resourceClass = $this->resolveResourceClass($match[1], $method);

            return $resourceClass !== null ? ['class' => $resourceClass, 'isCollection' => false] : null;
        }

        // Pattern: return new SomeResource(
        if (preg_match('/return\s+new\s+(\w+Resource)\s*\(/i', $source, $match)) {
            $resourceClass = $this->resolveResourceClass($match[1], $method);

            return $resourceClass !== null ? ['class' => $resourceClass, 'isCollection' => false] : null;
        }

        return null;
    }

    /**
     * Detect ApiResponse::success() with Resource parameter.
     *
     * @return array{type: string, data: array<string, mixed>, resourceClass: string|null, wrapped: bool}|null
     */
    private function detectApiResponseWithResource(string $source, ReflectionMethod $method): ?array
    {
        // Pattern: ApiResponse::success(SomeResource::make() or ApiResponse::success(new SomeResource()
        $patterns = [
            '/ApiResponse::(?:success|created)\s*\(\s*(\w+Resource)::make/i',
            '/ApiResponse::(?:success|created)\s*\(\s*(\w+Resource)::collection/i',
            '/ApiResponse::(?:success|created)\s*\(\s*new\s+(\w+Resource)/i',
            '/ApiResponse::(?:success|created)\s*\([^)]*data:\s*(\w+Resource)::make/i',
            '/ApiResponse::(?:success|created)\s*\([^)]*data:\s*(\w+Resource)::collection/i',
            '/ApiResponse::(?:success|created)\s*\([^)]*data:\s*new\s+(\w+Resource)/i',
        ];

        $isCollection = false;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $source, $match)) {
                $isCollection = str_contains($pattern, 'collection');
                $resourceClass = $this->resolveResourceClass($match[1], $method);

                if ($resourceClass !== null) {
                    $resourceData = $this->responseResolver->resolve($resourceClass, wrapped: false);

                    // For collections, wrap in array
                    $data = $isCollection
                        ? ['success' => true, 'status_code' => 200, 'message' => null, 'data' => [$resourceData]]
                        : $this->responseResolver->resolve($resourceClass, wrapped: true);

                    return [
                        'type' => 'api_response',
                        'data' => $data,
                        'resourceClass' => $resourceClass,
                        'wrapped' => true,
                        'isCollection' => $isCollection,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Detect response()->json([...]) return.
     *
     * @return array<string, mixed>|null
     */
    private function detectResponseJson(string $source): ?array
    {
        // Pattern: return response()->json([...])
        if (preg_match('/return\s+response\s*\(\s*\)\s*->\s*json\s*\(\s*\[([^\]]+)\]/s', $source, $match)) {
            return $this->parseInlineArray($match[1]);
        }

        return null;
    }

    /**
     * Detect direct array return.
     *
     * @return array<string, mixed>|null
     */
    private function detectArrayReturn(string $source): ?array
    {
        // Pattern: return [...] (but not return [...Resource...] which would be something else)
        // This is simple detection - only works for basic arrays
        if (! preg_match('/return\s+\[([^\]]+)\]\s*;/s', $source, $match)) {
            return null;
        }

        // Make sure it's not a Resource call
        if (! preg_match('/Resource/i', $match[1])) {
            return $this->parseInlineArray($match[1]);
        }

        return null;
    }

    /**
     * Parse inline array from source code.
     *
     * @return array<string, mixed>
     */
    private function parseInlineArray(string $arrayContent): array
    {
        $result = [];

        // Find key => value pairs: 'key' => or "key" =>
        preg_match_all("/['\"]([^'\"]+)['\"]\s*=>/", $arrayContent, $matches);

        foreach ($matches[1] as $key) {
            $result[$key] = $this->guessValueForKey($key);
        }

        return $result;
    }

    /**
     * Resolve resource class name to full namespace.
     */
    private function resolveResourceClass(string $resourceName, ReflectionMethod $method): ?string
    {
        $fileSource = $this->getFileSource($method);

        if ($fileSource === null) {
            return null;
        }

        // Extract use statements
        $uses = [];
        preg_match_all('/use\s+([^;]+);/', $fileSource, $useMatches);

        foreach ($useMatches[1] as $use) {
            $use = trim($use);

            if (preg_match('/(.+)\s+as\s+(\w+)$/', $use, $aliasMatch)) {
                $uses[$aliasMatch[2]] = $aliasMatch[1];
            } else {
                $parts = explode('\\', $use);
                $className = end($parts);
                $uses[$className] = $use;
            }
        }

        // Check use statements first
        if (isset($uses[$resourceName])) {
            return $uses[$resourceName];
        }

        // Try same namespace
        if (preg_match('/namespace\s+([^;]+);/', $fileSource, $nsMatch)) {
            $namespace = trim($nsMatch[1]);
            $fullClass = $namespace . '\\' . $resourceName;

            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        // Try common namespaces
        $commonNamespaces = [
            'App\\Http\\Resources',
            'App\\Http\\Resources\\Api',
            'App\\Http\\Resources\\Api\\V1',
            'App\\Http\\Resources\\Api\\V1\\Customer',
            'App\\Http\\Resources\\Api\\V1\\Order',
        ];

        foreach ($commonNamespaces as $ns) {
            $fullClass = $ns . '\\' . $resourceName;

            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        return null;
    }

    /**
     * Guess value for a key in inline array.
     */
    private function guessValueForKey(string $key): mixed
    {
        $keyLower = strtolower($key);

        // Common patterns
        $patterns = [
            'id' => 1,
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Example Name',
            'email' => 'user@example.com',
            'phone' => '+905551234567',
            'message' => 'Example message',
            'success' => true,
            'status' => 'active',
            'error' => 'Error message',
            'token' => 'example_token',
            'data' => [],
            'test' => 'test',
        ];

        if (isset($patterns[$keyLower])) {
            return $patterns[$keyLower];
        }

        // Boolean patterns
        if (str_starts_with($keyLower, 'is_') || str_starts_with($keyLower, 'has_')) {
            return true;
        }

        return 'example_value';
    }
}
