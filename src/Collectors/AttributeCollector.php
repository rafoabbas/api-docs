<?php

declare(strict_types=1);

namespace ApiDocs\Collectors;

use ApiDocs\Attributes\ApiAuth;
use ApiDocs\Attributes\ApiBody;
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
use ApiDocs\Resolvers\ResponseResolver;
use ApiDocs\Resolvers\ReturnTypeResolver;
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

    private readonly BodyMergeResolver $bodyMergeResolver;

    private readonly ResponseResolver $responseResolver;

    private readonly ReturnTypeResolver $returnTypeResolver;

    public function __construct(
        private readonly Router $router,
    ) {
        $this->bodyMergeResolver = new BodyMergeResolver;
        $this->responseResolver = new ResponseResolver;
        $this->returnTypeResolver = new ReturnTypeResolver;
    }

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

        $name = $requestAttr?->name ?? $this->generateRequestName($route, $method);
        $description = $requestAttr?->description;
        $order = $requestAttr?->order ?? 0;
        $folder = $folderAttr?->name ?? $this->determineFolderFromUri($route->uri());

        $headers = $this->collectHeaders($classReflection, $methodReflection);
        $queryParams = $this->collectQueryParams($methodReflection);
        $responses = $this->collectResponses($methodReflection);
        $variables = $this->collectVariables($methodReflection);
        $tests = $this->collectTests($methodReflection);
        $preRequestScripts = $this->collectPreRequestScripts($methodReflection);

        // Resolve body using BodyMergeResolver (handles merge logic)
        $body = $this->bodyMergeResolver->resolve($methodReflection, $bodyAttr, $method);
        $bodyMode = $bodyAttr?->mode ?? 'raw';
        $bodyLanguage = $bodyAttr?->language ?? 'json';

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
        if ($resourceAttr === null && count($responses) === 0) {
            $returnInfo = $this->returnTypeResolver->resolve($methodReflection);

            if ($returnInfo !== null) {
                $status = $returnInfo['type'] === 'api_response' && str_contains(strtolower($method), 'create') ? 201 : 200;
                $responses[] = new ResponseData('Success', $status, $returnInfo['data']);
            }
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
        );
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
    private function collectQueryParams(ReflectionMethod $methodReflection): array
    {
        $params = [];

        foreach ($this->getAttributes($methodReflection, ApiQueryParam::class) as $attr) {
            $params[] = new QueryParamData($attr->key, $attr->value, $attr->description, $attr->disabled);
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
    private function collectPreRequestScripts(ReflectionMethod $methodReflection): array
    {
        $scripts = [];

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
