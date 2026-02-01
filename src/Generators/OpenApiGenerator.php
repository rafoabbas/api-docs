<?php

declare(strict_types=1);

namespace ApiDocs\Generators;

use ApiDocs\Data\HeaderData;
use ApiDocs\Data\QueryParamData;
use ApiDocs\Data\RequestData;
use ApiDocs\Data\ResponseData;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

final class OpenApiGenerator
{
    private string $title = 'API Documentation';

    private string $version = '1.0.0';

    private string $description = '';

    /** @var array<int, array<string, string>> */
    private array $servers = [];

    private string $defaultServer = '';

    /** @var array<string, mixed> */
    private array $securitySchemes = [];

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array<int, array<string, string>>  $servers
     */
    public function setServers(array $servers): self
    {
        $this->servers = $servers;

        return $this;
    }

    public function addServer(string $url, string $description = ''): self
    {
        $this->servers[] = [
            'url' => $url,
            'description' => $description,
        ];

        return $this;
    }

    public function setDefaultServer(string $url): self
    {
        $this->defaultServer = $url;

        return $this;
    }

    /**
     * @param  array<int, RequestData>  $requests
     * @return array<string, mixed>
     */
    public function generate(array $requests): array
    {
        $paths = $this->groupRequestsByPath($requests);
        $tags = $this->extractTags($requests);

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
            ],
        ];

        if ($this->description !== '') {
            $spec['info']['description'] = $this->description;
        }

        $spec['servers'] = $this->buildServers();
        $spec['tags'] = $tags;
        $spec['paths'] = $paths;

        $securitySchemes = $this->buildSecuritySchemes($requests);

        if (count($securitySchemes) > 0) {
            $spec['components'] = [
                'securitySchemes' => $securitySchemes,
            ];
        }

        return $spec;
    }

    /**
     * Generate YAML output.
     *
     * @param  array<int, RequestData>  $requests
     */
    public function generateYaml(array $requests): string
    {
        return Yaml::dump($this->generate($requests), 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /**
     * Generate JSON output.
     *
     * @param  array<int, RequestData>  $requests
     */
    public function generateJson(array $requests): string
    {
        return (string) json_encode($this->generate($requests), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<int, RequestData>  $requests
     * @return array<string, array<string, mixed>>
     */
    private function groupRequestsByPath(array $requests): array
    {
        $paths = [];

        foreach ($requests as $request) {
            $path = $this->convertUriToOpenApiPath($request->uri);
            $method = strtolower($request->method);

            if (! isset($paths[$path])) {
                $paths[$path] = [];
            }

            $paths[$path][$method] = $this->buildOperation($request);
        }

        ksort($paths);

        return $paths;
    }

    private function convertUriToOpenApiPath(string $uri): string
    {
        // Convert Laravel route params {param} to OpenAPI {param}
        $path = '/' . ltrim($uri, '/');

        // Handle optional parameters {param?} -> {param}
        $path = preg_replace('/\{(\w+)\?\}/', '{$1}', $path);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOperation(RequestData $request): array
    {
        $operation = [
            'summary' => $request->name,
            'tags' => [$this->extractTagFromFolder($request->folder)],
            'operationId' => $this->generateOperationId($request),
        ];

        if ($request->description !== null) {
            $operation['description'] = $request->description;
        }

        // Path parameters
        $pathParams = $this->extractPathParameters($request->uri);
        $parameters = [];

        foreach ($pathParams as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
        }

        // Query parameters
        foreach ($request->queryParams as $queryParam) {
            $parameters[] = $this->buildQueryParameter($queryParam);
        }

        // Header parameters (non-standard ones)
        foreach ($request->headers as $header) {
            if (! in_array(strtolower($header->key), ['accept', 'content-type', 'authorization'])) {
                $parameters[] = $this->buildHeaderParameter($header);
            }
        }

        if (count($parameters) > 0) {
            $operation['parameters'] = $parameters;
        }

        // Request body
        if (in_array($request->method, ['POST', 'PUT', 'PATCH']) && $request->body !== null) {
            $operation['requestBody'] = $this->buildRequestBody($request);
        }

        // Responses
        $operation['responses'] = $this->buildResponses($request);

        // Security
        if ($this->requiresAuth($request)) {
            $operation['security'] = [$this->buildSecurityRequirement($request)];
        }

        return $operation;
    }

    private function generateOperationId(RequestData $request): string
    {
        $name = Str::camel($request->name);
        $name = str_replace([' ', '-', '/'], '', $name);

        return lcfirst($name);
    }

    /**
     * @return array<string>
     */
    private function extractPathParameters(string $uri): array
    {
        preg_match_all('/\{(\w+)\??}/', $uri, $matches);

        return $matches[1] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQueryParameter(QueryParamData $param): array
    {
        $parameter = [
            'name' => $param->key,
            'in' => 'query',
            'required' => false,
            'schema' => ['type' => 'string'],
        ];

        if ($param->value !== '') {
            $parameter['example'] = $param->value;
        }

        if ($param->description !== null) {
            $parameter['description'] = $param->description;
        }

        return $parameter;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHeaderParameter(HeaderData $header): array
    {
        $parameter = [
            'name' => $header->key,
            'in' => 'header',
            'required' => false,
            'schema' => ['type' => 'string'],
        ];

        if ($header->value !== '') {
            $parameter['example'] = $header->value;
        }

        if ($header->description !== null) {
            $parameter['description'] = $header->description;
        }

        return $parameter;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestBody(RequestData $request): array
    {
        $content = [];

        if ($request->bodyMode === 'raw' && $request->bodyLanguage === 'json') {
            $content['application/json'] = [
                'schema' => $this->inferSchemaFromData($request->body ?? []),
            ];
        } elseif ($request->bodyMode === 'formdata') {
            $content['multipart/form-data'] = [
                'schema' => $this->inferSchemaFromData($request->body ?? []),
            ];
        } elseif ($request->bodyMode === 'urlencoded') {
            $content['application/x-www-form-urlencoded'] = [
                'schema' => $this->inferSchemaFromData($request->body ?? []),
            ];
        } else {
            $content['application/json'] = [
                'schema' => $this->inferSchemaFromData($request->body ?? []),
            ];
        }

        return [
            'required' => true,
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponses(RequestData $request): array
    {
        $responses = [];

        if (count($request->responses) === 0) {
            // Default response
            $responses['200'] = [
                'description' => 'Successful response',
            ];
        } else {
            foreach ($request->responses as $response) {
                $responses[(string) $response->status] = $this->buildResponse($response);
            }
        }

        return $responses;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(ResponseData $response): array
    {
        $result = [
            'description' => $response->name,
        ];

        if (count($response->body) > 0) {
            $result['content'] = [
                'application/json' => [
                    'schema' => $this->inferSchemaFromData($response->body),
                ],
            ];
        }

        if (count($response->headers) > 0) {
            $result['headers'] = [];

            foreach ($response->headers as $key => $value) {
                $result['headers'][$key] = [
                    'schema' => ['type' => 'string'],
                    'example' => $value,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function inferSchemaFromData(array $data): array
    {
        if (count($data) === 0) {
            return ['type' => 'object'];
        }

        // Check if it's a list (sequential array)
        if (array_is_list($data)) {
            return [
                'type' => 'array',
                'items' => count($data) > 0 ? $this->inferSchemaFromValue($data[0]) : ['type' => 'string'],
            ];
        }

        $properties = [];
        $required = [];

        foreach ($data as $key => $value) {
            $properties[$key] = $this->inferSchemaFromValue($value);

            // Consider all provided fields as required for examples
            if ($value !== null) {
                $required[] = $key;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (count($required) > 0) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function inferSchemaFromValue(mixed $value): array
    {
        if (is_null($value)) {
            return ['type' => 'string', 'nullable' => true];
        }

        if (is_bool($value)) {
            return ['type' => 'boolean', 'example' => $value];
        }

        if (is_int($value)) {
            return ['type' => 'integer', 'example' => $value];
        }

        if (is_float($value)) {
            return ['type' => 'number', 'example' => $value];
        }

        if (is_string($value)) {
            $schema = ['type' => 'string', 'example' => $value];

            // Detect common formats
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $schema['format'] = 'email';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $schema['format'] = 'date';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                $schema['format'] = 'date-time';
            } elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                $schema['format'] = 'uuid';
            }

            return $schema;
        }

        if (is_array($value)) {
            return $this->inferSchemaFromData($value);
        }

        return ['type' => 'string'];
    }

    private function requiresAuth(RequestData $request): bool
    {
        if ($request->auth instanceof \ApiDocs\Data\AuthData) {
            return $request->auth->type !== 'noauth';
        }

        return in_array('auth:sanctum', $request->middleware)
            || in_array('auth', $request->middleware);
    }

    /**
     * @return array<string, array<string>>
     */
    private function buildSecurityRequirement(RequestData $request): array
    {
        if ($request->auth instanceof \ApiDocs\Data\AuthData) {
            return match ($request->auth->type) {
                'bearer' => ['bearerAuth' => []],
                'basic' => ['basicAuth' => []],
                'apikey' => ['apiKeyAuth' => []],
                default => ['bearerAuth' => []],
            };
        }

        // Default to bearer for sanctum/auth middleware
        return ['bearerAuth' => []];
    }

    /**
     * @param  array<int, RequestData>  $requests
     * @return array<string, array<string, mixed>>
     */
    private function buildSecuritySchemes(array $requests): array
    {
        $schemes = [];
        $hasBearer = false;
        $hasBasic = false;
        $hasApiKey = false;
        $apiKeyHeader = 'X-API-Key';

        foreach ($requests as $request) {
            if ($this->requiresAuth($request)) {
                if ($request->auth !== null) {
                    match ($request->auth->type) {
                        'bearer' => $hasBearer = true,
                        'basic' => $hasBasic = true,
                        'apikey' => (function () use (&$hasApiKey, &$apiKeyHeader, $request): void {
                            $hasApiKey = true;

                            if ($request->auth?->apiKeyHeader) {
                                $apiKeyHeader = $request->auth->apiKeyHeader;
                            }
                        })(),
                        default => $hasBearer = true,
                    };
                } else {
                    // Default for middleware-based auth
                    $hasBearer = true;
                }
            }
        }

        if ($hasBearer) {
            $schemes['bearerAuth'] = [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ];
        }

        if ($hasBasic) {
            $schemes['basicAuth'] = [
                'type' => 'http',
                'scheme' => 'basic',
            ];
        }

        if ($hasApiKey) {
            $schemes['apiKeyAuth'] = [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => $apiKeyHeader,
            ];
        }

        return $schemes;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildServers(): array
    {
        if (count($this->servers) > 0) {
            return array_map(function (array $server): array {
                $result = ['url' => $server['url']];

                if (isset($server['description']) && ($server['description'] !== '' && $server['description'] !== '0')) {
                    $result['description'] = $server['description'];
                }

                return $result;
            }, $this->servers);
        }

        if ($this->defaultServer !== '') {
            return [['url' => $this->defaultServer]];
        }

        return [['url' => '/']];
    }

    /**
     * @param  array<int, RequestData>  $requests
     * @return array<int, array<string, string>>
     */
    private function extractTags(array $requests): array
    {
        $tags = [];
        $seen = [];

        foreach ($requests as $request) {
            $tagName = $this->extractTagFromFolder($request->folder);

            if (! isset($seen[$tagName])) {
                $seen[$tagName] = true;
                $tags[] = ['name' => $tagName];
            }
        }

        usort($tags, fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $tags;
    }

    private function extractTagFromFolder(string $folder): string
    {
        // Use the last part of the folder as the tag
        // e.g., "V1 / Customer / Auth" -> "Auth"
        $parts = array_map(trim(...), explode('/', $folder));

        return end($parts) ?: $parts[0];
    }
}
