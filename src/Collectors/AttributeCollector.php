<?php

declare(strict_types=1);

namespace ApiDocs\Collectors;

use ApiDocs\Attributes\ApiAuth;
use ApiDocs\Attributes\ApiBody;
use ApiDocs\Attributes\ApiDeprecated;
use ApiDocs\Attributes\ApiFolder;
use ApiDocs\Attributes\ApiHeader;
use ApiDocs\Attributes\ApiHidden;
use ApiDocs\Attributes\ApiPreRequest;
use ApiDocs\Attributes\ApiQueryParam;
use ApiDocs\Attributes\ApiRequest;
use ApiDocs\Attributes\ApiResource;
use ApiDocs\Attributes\ApiResponse;
use ApiDocs\Attributes\ApiTest;
use ApiDocs\Attributes\ApiVariable;
use ApiDocs\Data\AuthData;
use ApiDocs\Data\HeaderData;
use ApiDocs\Data\QueryParamData;
use ApiDocs\Data\RequestData;
use ApiDocs\Data\ResponseData;
use ApiDocs\Data\TestData;
use ApiDocs\Data\VariableData;
use ApiDocs\Resolvers\BodyMergeResolver;
use ApiDocs\Resolvers\QueryParamResolver;
use ApiDocs\Resolvers\ResponseResolver;
use ApiDocs\Resolvers\ReturnTypeResolver;
use ApiDocs\Resolvers\ValidationSchemaResolver;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

final class AttributeCollector
{
    /** @var array<string> */
    private array $excludePrefixes = ['_', 'sanctum', 'telescope', 'storage', 'mcp'];

    public function __construct(
        private readonly Router $router,
        private readonly BodyMergeResolver $bodyMergeResolver = new BodyMergeResolver,
        private readonly QueryParamResolver $queryParamResolver = new QueryParamResolver,
        private readonly ResponseResolver $responseResolver = new ResponseResolver,
        private readonly ReturnTypeResolver $returnTypeResolver = new ReturnTypeResolver,
        private readonly ValidationSchemaResolver $validationSchemaResolver = new ValidationSchemaResolver,
    ) {}

    /**
     * @param  array<string>  $prefixes
     */
    public function setExcludePrefixes(array $prefixes): self
    {
        $this->excludePrefixes = $prefixes;

        return $this;
    }

    /**
     * @return array<int, RequestData>
     */
    public function collect(): array
    {
        $this->excludePrefixes = config('api-docs.exclude_prefixes', $this->excludePrefixes);
        $requests = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($this->shouldExcludeRoute($route)) {
                continue;
            }

            $controllerClass = $this->getControllerClass($route);
            $methodName = $this->getMethodName($route);

            if ($controllerClass === null) {
                continue;
            }

            if ($methodName === null) {
                continue;
            }

            if (! class_exists($controllerClass)) {
                continue;
            }

            $classReflection = new ReflectionClass($controllerClass);

            if ($this->hasAttribute($classReflection, ApiHidden::class)) {
                continue;
            }

            if (! $classReflection->hasMethod($methodName)) {
                continue;
            }

            $methodReflection = $classReflection->getMethod($methodName);

            if ($this->hasAttribute($methodReflection, ApiHidden::class)) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $requests[] = $this->buildRequestData(
                    $route,
                    $method,
                    $classReflection,
                    $methodReflection,
                );
            }
        }

        return $requests;
    }

    private function shouldExcludeRoute(Route $route): bool
    {
        $uri = $route->uri();

        foreach ($this->excludePrefixes as $prefix) {
            if (Str::startsWith($uri, $prefix)) {
                return true;
            }
        }

        return $uri === 'up';
    }

    private function getControllerClass(Route $route): ?string
    {
        $action = $route->getAction();

        if (isset($action['controller'])) {
            $parts = explode('@', $action['controller']);

            return $parts[0] ?? null;
        }

        return null;
    }

    private function getMethodName(Route $route): ?string
    {
        $action = $route->getAction();

        if (isset($action['controller'])) {
            $parts = explode('@', $action['controller']);

            return $parts[1] ?? '__invoke';
        }

        return null;
    }

    private function buildRequestData(
        Route $route,
        string $method,
        ReflectionClass $classReflection,
        ReflectionMethod $methodReflection,
    ): RequestData {
        $requestAttr = $this->getAttribute($methodReflection, ApiRequest::class);
        $folderAttr = $this->getAttribute($methodReflection, ApiFolder::class)
            ?? $this->getAttribute($classReflection, ApiFolder::class);
        $bodyAttr = $this->getAttribute($methodReflection, ApiBody::class);
        $authAttr = $this->getAttribute($methodReflection, ApiAuth::class)
            ?? $this->getAttribute($classReflection, ApiAuth::class);
        $deprecatedAttr = $this->getAttribute($methodReflection, ApiDeprecated::class)
            ?? $this->getAttribute($classReflection, ApiDeprecated::class);

        $name = $requestAttr?->name ?? $this->generateRequestName($route, $method);
        $description = $requestAttr?->description;
        $order = $requestAttr?->order ?? 0;
        $folder = $folderAttr?->name ?? $this->determineFolderFromUri($route->uri());

        $headers = $this->collectHeaders($classReflection, $methodReflection);
        $queryParams = $this->collectQueryParams($methodReflection, $method);
        $responses = $this->collectResponses($methodReflection);
        $variables = $this->collectVariables($methodReflection);
        $tests = $this->collectTests($methodReflection);
        $preRequestScripts = $this->collectPreRequestScripts($classReflection, $methodReflection);

        // Resolve body using BodyMergeResolver (handles merge logic)
        $body = $this->bodyMergeResolver->resolve($methodReflection, $bodyAttr, $method);
        $bodyMode = $bodyAttr?->mode ?? 'raw';
        $bodyLanguage = $bodyAttr?->language ?? 'json';

        // Resolve validation schema and file upload detection
        $bodySchema = null;
        $hasFileUpload = false;

        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $schemaResult = $this->validationSchemaResolver->resolve($methodReflection);

            if ($schemaResult !== null) {
                $bodySchema = $schemaResult['schema'];
                $hasFileUpload = $schemaResult['hasFileUpload'];

                // Auto-switch to formdata mode when file uploads are detected
                if ($hasFileUpload && $bodyMode === 'raw') {
                    $bodyMode = 'formdata';
                }
            }
        }

        // Try to resolve response from ApiResource attribute
        $resourceAttr = $this->getAttribute($methodReflection, ApiResource::class);

        if ($resourceAttr !== null && count($responses) === 0) {
            $isCollection = $resourceAttr->collection ?? $this->detectCollectionFromMethod($methodReflection);
            $isWrapped = $resourceAttr->wrapped ?? $this->detectWrappedFromMethod($methodReflection);
            $resourceData = $this->responseResolver->resolve($resourceAttr->resourceClass, wrapped: false);

            if ($isWrapped) {
                $responseBody = [
                    'success' => true,
                    'status_code' => $resourceAttr->status,
                    'message' => null,
                    'data' => $isCollection ? [$resourceData] : $resourceData,
                ];
            } else {
                $responseBody = ['data' => $isCollection ? [$resourceData] : $resourceData];
            }

            $responses[] = new ResponseData('Success', $resourceAttr->status, $responseBody);
        }

        // If no ApiResource and no ApiResponse, try auto-detect from return statement
        $isPaginated = false;

        if ($resourceAttr === null && count($responses) === 0) {
            $returnInfo = $this->returnTypeResolver->resolve($methodReflection);

            if ($returnInfo !== null) {
                $status = $returnInfo['type'] === 'api_response' && str_contains(strtolower($method), 'create') ? 201 : 200;
                $isPaginated = $returnInfo['isPaginated'] ?? false;

                if ($isPaginated) {
                    $responses[] = new ResponseData('Success', $status, $this->buildPaginatedResponse(
                        $returnInfo['data'],
                        $returnInfo['wrapped'] ?? false,
                    ));
                } else {
                    $responses[] = new ResponseData('Success', $status, $returnInfo['data']);
                }
            }
        }

        // Detect pagination from method source if not already detected
        if (! $isPaginated) {
            $isPaginated = $this->detectPaginationFromMethod($methodReflection);
        }

        $auth = $authAttr !== null
            ? new AuthData(
                $authAttr->type,
                $authAttr->token,
                $authAttr->username,
                $authAttr->password,
                $authAttr->apiKey,
                $authAttr->apiKeyHeader,
            )
            : null;

        return new RequestData(
            name: $name,
            method: $method,
            uri: $route->uri(),
            description: $description,
            folder: $folder,
            order: $order,
            body: $body,
            bodyMode: $bodyMode,
            bodyLanguage: $bodyLanguage,
            headers: $headers,
            queryParams: $queryParams,
            responses: $responses,
            variables: $variables,
            tests: $tests,
            preRequestScripts: $preRequestScripts,
            auth: $auth,
            middleware: $route->middleware(),
            deprecated: $deprecatedAttr !== null,
            deprecatedReason: $deprecatedAttr?->reason,
            deprecatedReplacement: $deprecatedAttr?->replacement,
            bodySchema: $bodySchema,
            hasFileUpload: $hasFileUpload,
            isPaginated: $isPaginated,
        );
    }

    /**
     * Build a paginated response structure.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPaginatedResponse(array $data, bool $wrapped): array
    {
        $paginationMeta = [
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'path' => 'https://example.com/api/resource',
            'per_page' => 15,
            'to' => 1,
            'total' => 1,
        ];

        $paginationLinks = [
            'first' => 'https://example.com/api/resource?page=1',
            'last' => 'https://example.com/api/resource?page=1',
            'prev' => null,
            'next' => null,
        ];

        if ($wrapped) {
            return [
                'success' => true,
                'status_code' => 200,
                'message' => null,
                'data' => array_is_list($data) ? $data : [$data],
                'links' => $paginationLinks,
                'meta' => $paginationMeta,
            ];
        }

        return [
            'data' => array_is_list($data) ? $data : [$data],
            'links' => $paginationLinks,
            'meta' => $paginationMeta,
        ];
    }

    /**
     * Detect if method uses pagination by analyzing the method source code.
     */
    private function detectPaginationFromMethod(ReflectionMethod $method): bool
    {
        $methodSource = $this->getMethodSource($method);

        if ($methodSource === null) {
            return false;
        }

        return (bool) preg_match('/->(?:paginate|simplePaginate|cursorPaginate)\s*\(/', $methodSource);
    }

    /**
     * @return array<int, HeaderData>
     */
    private function collectHeaders(ReflectionClass $classReflection, ReflectionMethod $methodReflection): array
    {
        $headers = [];

        foreach ($this->getAttributes($classReflection, ApiHeader::class) as $attr) {
            $headers[] = new HeaderData($attr->key, $attr->value, $attr->description, $attr->disabled);
        }

        foreach ($this->getAttributes($methodReflection, ApiHeader::class) as $attr) {
            $headers[] = new HeaderData($attr->key, $attr->value, $attr->description, $attr->disabled);
        }

        return $headers;
    }

    /**
     * @return array<int, QueryParamData>
     */
    private function collectQueryParams(ReflectionMethod $methodReflection, string $httpMethod): array
    {
        $params = [];

        // Collect from ApiQueryParam attributes first
        foreach ($this->getAttributes($methodReflection, ApiQueryParam::class) as $attr) {
            $params[] = new QueryParamData($attr->key, $attr->value, $attr->description, $attr->disabled);
        }

        // For GET and DELETE requests, also resolve from FormRequest if no manual params defined
        if (in_array($httpMethod, ['GET', 'DELETE']) && count($params) === 0) {
            $params = array_merge($params, $this->queryParamResolver->resolve($methodReflection));
        }

        return $params;
    }

    /**
     * @return array<int, ResponseData>
     */
    private function collectResponses(ReflectionMethod $methodReflection): array
    {
        $responses = [];

        foreach ($this->getAttributes($methodReflection, ApiResponse::class) as $attr) {
            $responses[] = new ResponseData($attr->name, $attr->status, $attr->body, $attr->headers);
        }

        return $responses;
    }

    /**
     * @return array<int, VariableData>
     */
    private function collectVariables(ReflectionMethod $methodReflection): array
    {
        $variables = [];

        foreach ($this->getAttributes($methodReflection, ApiVariable::class) as $attr) {
            $variables[] = new VariableData($attr->name, $attr->path, $attr->scope);
        }

        return $variables;
    }

    /**
     * @return array<int, TestData>
     */
    private function collectTests(ReflectionMethod $methodReflection): array
    {
        $tests = [];

        foreach ($this->getAttributes($methodReflection, ApiTest::class) as $attr) {
            $tests[] = new TestData($attr->script, $attr->name);
        }

        return $tests;
    }

    /**
     * @return array<string>
     */
    private function collectPreRequestScripts(ReflectionClass $classReflection, ReflectionMethod $methodReflection): array
    {
        $scripts = [];

        // Collect from class-level attributes first
        foreach ($this->getAttributes($classReflection, ApiPreRequest::class) as $attr) {
            $scripts[] = $attr->script;
        }

        // Then collect from method-level attributes
        foreach ($this->getAttributes($methodReflection, ApiPreRequest::class) as $attr) {
            $scripts[] = $attr->script;
        }

        return $scripts;
    }

    private function generateRequestName(Route $route, string $method): string
    {
        if ($name = $route->getName()) {
            $parts = explode('.', $name);
            $lastPart = end($parts);

            return Str::title(str_replace(['-', '_'], ' ', $lastPart));
        }

        $uri = $route->uri();
        $parts = explode('/', trim($uri, '/'));
        $lastPart = end($parts);

        if (Str::startsWith($lastPart, '{')) {
            $lastPart = $parts[count($parts) - 2] ?? $lastPart;

            return $method . ' ' . Str::title($lastPart) . ' by ID';
        }

        return $method . ' ' . Str::title(str_replace(['-', '_'], ' ', $lastPart));
    }

    private function determineFolderFromUri(string $uri): string
    {
        $parts = explode('/', trim($uri, '/'));

        if (count($parts) >= 3 && $parts[0] === 'v1') {
            return Str::title($parts[1]) . ' / ' . Str::title($parts[2]);
        }

        if (count($parts) >= 2) {
            return Str::title($parts[0]) . ' / ' . Str::title($parts[1]);
        }

        return Str::title($parts[0] ?? 'General');
    }

    /**
     * Get method source code for analysis.
     */
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
     * Detect if method returns a collection by analyzing the method source code.
     */
    private function detectCollectionFromMethod(ReflectionMethod $method): bool
    {
        $methodSource = $this->getMethodSource($method);

        if ($methodSource === null) {
            return false;
        }

        // Check for ::collection( pattern
        return (bool) preg_match('/\w+Resource::collection\s*\(/i', $methodSource);
    }

    /**
     * Detect if method uses ApiResponse wrapper by analyzing the method source code.
     */
    private function detectWrappedFromMethod(ReflectionMethod $method): bool
    {
        $methodSource = $this->getMethodSource($method);

        if ($methodSource === null) {
            return false;
        }

        // Check for ApiResponse:: pattern (success, created, error, etc.)
        return (bool) preg_match('/ApiResponse::/i', $methodSource);
    }

    /**
     * @template T of object
     *
     * @param  ReflectionClass<object>|ReflectionMethod  $reflection
     * @param  class-string<T>  $attributeClass
     */
    private function hasAttribute(ReflectionClass|ReflectionMethod $reflection, string $attributeClass): bool
    {
        return count($reflection->getAttributes($attributeClass)) > 0;
    }

    /**
     * @template T of object
     *
     * @param  ReflectionClass<object>|ReflectionMethod  $reflection
     * @param  class-string<T>  $attributeClass
     * @return T|null
     */
    private function getAttribute(ReflectionClass|ReflectionMethod $reflection, string $attributeClass): ?object
    {
        $attributes = $reflection->getAttributes($attributeClass);

        if (count($attributes) === 0) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @template T of object
     *
     * @param  ReflectionClass<object>|ReflectionMethod  $reflection
     * @param  class-string<T>  $attributeClass
     * @return array<T>
     */
    private function getAttributes(ReflectionClass|ReflectionMethod $reflection, string $attributeClass): array
    {
        return array_map(
            fn (ReflectionAttribute $attr): object => $attr->newInstance(),
            $reflection->getAttributes($attributeClass),
        );
    }
}
