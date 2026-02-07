<?php

declare(strict_types=1);

namespace ApiDocs\Generators;

use ApiDocs\Data\AuthData;
use ApiDocs\Data\QueryParamData;
use ApiDocs\Data\RequestData;
use ApiDocs\Data\ResponseData;
use Illuminate\Support\Str;

final class CollectionGenerator
{
    private string $baseUrl = '{{API_URL}}';

    private string $baseUrlV1 = '{{API_URL_V1}}';

    /** @var array<string, string> */
    private array $variables = [];

    private string $variableScope = 'collection';

    /** @var array<string, string> */
    private array $defaultHeaders = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    public function __construct(private readonly string $collectionName) {}

    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function setBaseUrlV1(string $baseUrlV1): self
    {
        $this->baseUrlV1 = $baseUrlV1;

        return $this;
    }

    /**
     * @param  array<string, string>  $variables
     */
    public function setVariables(array $variables): self
    {
        $this->variables = $variables;

        return $this;
    }

    public function addVariable(string $key, string $value): self
    {
        $this->variables[$key] = $value;

        return $this;
    }

    public function setVariableScope(string $scope): self
    {
        $this->variableScope = $scope;

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = $headers;

        return $this;
    }

    /**
     * @param  array<int, RequestData>  $requests
     * @return array<string, mixed>
     */
    public function generate(array $requests): array
    {
        $items = $this->groupRequestsByFolder($requests);

        return [
            'info' => [
                '_postman_id' => Str::uuid()->toString(),
                'name' => $this->collectionName,
                'description' => "API collection for {$this->collectionName}",
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => $items,
            'event' => [
                [
                    'listen' => 'prerequest',
                    'script' => [
                        'type' => 'text/javascript',
                        'exec' => [''],
                    ],
                ],
                [
                    'listen' => 'test',
                    'script' => [
                        'type' => 'text/javascript',
                        'exec' => [''],
                    ],
                ],
            ],
            'variable' => $this->buildVariables(),
        ];
    }

    /**
     * @param  array<int, RequestData>  $requests
     * @return array<int, array<string, mixed>>
     */
    private function groupRequestsByFolder(array $requests): array
    {
        $grouped = [];

        foreach ($requests as $request) {
            $folderPath = $request->folder;

            if (! isset($grouped[$folderPath])) {
                $grouped[$folderPath] = [];
            }

            $grouped[$folderPath][] = $request;
        }

        // Sort items within each folder by order
        foreach ($grouped as $folder => $items) {
            usort($items, fn (RequestData $a, RequestData $b): int => $a->order <=> $b->order);
            $grouped[$folder] = $items;
        }

        // Sort folders
        ksort($grouped);

        $result = [];

        foreach ($grouped as $folderName => $folderRequests) {
            $folderItems = array_map(
                $this->buildRequestItem(...),
                $folderRequests,
            );

            // Handle nested folders (e.g., "Auth / OTP")
            $result[] = $this->buildFolderStructure($folderName, $folderItems);
        }

        return $this->mergeNestedFolders($result);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function buildFolderStructure(string $folderPath, array $items): array
    {
        $parts = array_map(trim(...), explode('/', $folderPath));

        if (count($parts) === 1) {
            return [
                'name' => $parts[0],
                'item' => $items,
            ];
        }

        // Build nested structure
        $current = [
            'name' => array_pop($parts),
            'item' => $items,
        ];

        while (count($parts) > 0) {
            $current = [
                'name' => array_pop($parts),
                'item' => [$current],
            ];
        }

        return $current;
    }

    /**
     * @param  array<int, array<string, mixed>>  $folders
     * @return array<int, array<string, mixed>>
     */
    private function mergeNestedFolders(array $folders): array
    {
        $merged = [];

        foreach ($folders as $folder) {
            $this->mergeIntoStructure($merged, $folder);
        }

        return $this->convertToIndexedArray($merged);
    }

    /**
     * @param  array<string, array<string, mixed>>  $structure
     * @param  array<string, mixed>  $folder
     */
    private function mergeIntoStructure(array &$structure, array $folder): void
    {
        $name = $folder['name'];

        if (! isset($structure[$name])) {
            // Convert item array to associative for consistent merging
            $structure[$name] = $folder;

            if (isset($structure[$name]['item'])) {
                $structure[$name]['item'] = $this->convertToAssociativeArray($structure[$name]['item']);
            }

            return;
        }

        // Merge items
        if (isset($folder['item'])) {
            foreach ($folder['item'] as $item) {
                if (isset($item['name']) && isset($item['item'])) {
                    // It's a subfolder - recursively merge
                    $this->mergeIntoStructure($structure[$name]['item'], $item);
                } else {
                    // It's a request - use a unique key to avoid duplicates
                    $requestKey = 'request_' . ($item['name'] ?? uniqid());
                    $structure[$name]['item'][$requestKey] = $item;
                }
            }
        }
    }

    /**
     * Convert indexed array to associative array keyed by name (for folders) or unique key (for requests).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function convertToAssociativeArray(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (isset($item['name']) && isset($item['item'])) {
                // It's a folder
                $result[$item['name']] = $item;
                $result[$item['name']]['item'] = $this->convertToAssociativeArray($item['item']);
            } else {
                // It's a request
                $key = 'request_' . ($item['name'] ?? uniqid());
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * Convert associative array back to indexed array for Postman format.
     *
     * @param  array<string, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function convertToIndexedArray(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $item['item'] = $this->convertToIndexedArray($item['item']);
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestItem(RequestData $request): array
    {
        $item = [
            'name' => $request->name,
            'request' => [
                'method' => $request->method,
                'header' => $this->buildHeaders($request),
                'url' => $this->buildUrl($request),
            ],
            'response' => $this->buildResponses($request),
        ];

        if ($request->description !== null) {
            $item['request']['description'] = $request->description;
        }

        if ($request->auth instanceof \ApiDocs\Data\AuthData) {
            $item['request']['auth'] = $this->buildAuth($request->auth);
        }

        $body = $this->buildBody($request);

        if ($body !== null) {
            $item['request']['body'] = $body;
        }

        $events = $this->buildEvents($request);

        if (count($events) > 0) {
            $item['event'] = $events;
        }

        return $item;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildHeaders(RequestData $request): array
    {
        $headers = [];

        // Add default headers
        foreach ($this->defaultHeaders as $key => $value) {
            $headers[] = [
                'key' => $key,
                'value' => $value,
                'type' => 'text',
            ];
        }

        // Add auth header if needed
        if ($this->requiresAuth($request)) {
            $headers[] = [
                'key' => 'Authorization',
                'value' => 'Bearer {{BEARER_TOKEN}}',
                'type' => 'text',
            ];
        }

        // Add custom headers
        foreach ($request->headers as $header) {
            $headers[] = [
                'key' => $header->key,
                'value' => $header->value,
                'type' => 'text',
                'description' => $header->description,
                'disabled' => $header->disabled,
            ];
        }

        return $headers;
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
     * @return array<string, mixed>
     */
    private function buildUrl(RequestData $request): array
    {
        $uri = $request->uri;
        $path = collect(explode('/', trim($uri, '/')))
            ->map(function (string $segment): string {
                if (Str::startsWith($segment, '{') && Str::endsWith($segment, '}')) {
                    $paramName = trim($segment, '{}?');

                    return ':' . $paramName;
                }

                return $segment;
            })
            ->toArray();

        $variables = [];

        foreach ($path as $segment) {
            if (Str::startsWith($segment, ':')) {
                $variables[] = [
                    'key' => ltrim($segment, ':'),
                    'value' => '',
                    'description' => '',
                ];
            }
        }

        // Use API_URL_V1 if path starts with v1
        $baseUrl = $this->baseUrl;

        if (count($path) > 0 && $path[0] === 'v1') {
            $baseUrl = $this->baseUrlV1;
            array_shift($path);
        }

        $rawPath = count($path) > 0 ? '/' . implode('/', $path) : '';

        $url = [
            'raw' => $baseUrl . $rawPath,
            'host' => [$baseUrl],
            'path' => $path,
        ];

        if (count($variables) > 0) {
            $url['variable'] = $variables;
        }

        // Add query params
        if (count($request->queryParams) > 0) {
            $url['query'] = array_map(
                fn (QueryParamData $param): array => [
                    'key' => $param->key,
                    'value' => $param->value,
                    'description' => $param->description,
                    'disabled' => $param->disabled,
                ],
                $request->queryParams,
            );
        }

        return $url;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildBody(RequestData $request): ?array
    {
        if (! in_array($request->method, ['POST', 'PUT', 'PATCH'])) {
            return null;
        }

        $body = $request->body ?? [];

        if ($request->bodyMode === 'raw') {
            return [
                'mode' => 'raw',
                'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'options' => [
                    'raw' => [
                        'language' => $request->bodyLanguage,
                    ],
                ],
            ];
        }

        if ($request->bodyMode === 'formdata') {
            return [
                'mode' => 'formdata',
                'formdata' => array_map(
                    fn (string $key, mixed $value): array => [
                        'key' => $key,
                        'value' => is_array($value) ? json_encode($value) : (string) $value,
                        'type' => 'text',
                    ],
                    array_keys($body),
                    array_values($body),
                ),
            ];
        }

        if ($request->bodyMode === 'urlencoded') {
            return [
                'mode' => 'urlencoded',
                'urlencoded' => array_map(
                    fn (string $key, mixed $value): array => [
                        'key' => $key,
                        'value' => is_array($value) ? json_encode($value) : (string) $value,
                        'type' => 'text',
                    ],
                    array_keys($body),
                    array_values($body),
                ),
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildAuth(AuthData $auth): ?array
    {
        return match ($auth->type) {
            'bearer' => [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => $auth->token ?? '{{BEARER_TOKEN}}',
                        'type' => 'string',
                    ],
                ],
            ],
            'basic' => [
                'type' => 'basic',
                'basic' => [
                    ['key' => 'username', 'value' => $auth->username ?? '', 'type' => 'string'],
                    ['key' => 'password', 'value' => $auth->password ?? '', 'type' => 'string'],
                ],
            ],
            'apikey' => [
                'type' => 'apikey',
                'apikey' => [
                    ['key' => 'key', 'value' => $auth->apiKeyHeader ?? 'X-API-Key', 'type' => 'string'],
                    ['key' => 'value', 'value' => $auth->apiKey ?? '', 'type' => 'string'],
                    ['key' => 'in', 'value' => 'header', 'type' => 'string'],
                ],
            ],
            'noauth' => ['type' => 'noauth'],
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildResponses(RequestData $request): array
    {
        return array_map(
            fn (ResponseData $response): array => [
                'name' => $response->name,
                'originalRequest' => [
                    'method' => $request->method,
                    'url' => $this->buildUrl($request),
                ],
                'status' => $this->getStatusText($response->status),
                'code' => $response->status,
                '_postman_previewlanguage' => 'json',
                'header' => array_map(
                    fn (string $key, string $value): array => ['key' => $key, 'value' => $value],
                    array_keys($response->headers),
                    array_values($response->headers),
                ),
                'body' => json_encode($response->body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ],
            $request->responses,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildEvents(RequestData $request): array
    {
        $events = [];

        // Pre-request scripts
        if (count($request->preRequestScripts) > 0) {
            $events[] = [
                'listen' => 'prerequest',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => $request->preRequestScripts,
                ],
            ];
        }

        // Test scripts
        $testScripts = $this->buildTestScripts($request);

        if (count($testScripts) > 0) {
            $events[] = [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => $testScripts,
                ],
            ];
        }

        return $events;
    }

    /**
     * @return array<string>
     */
    private function buildTestScripts(RequestData $request): array
    {
        $scripts = [];

        // Add custom tests
        foreach ($request->tests as $test) {
            if ($test->name !== null) {
                $scripts[] = "pm.test(\"{$test->name}\", function () {";
                $scripts[] = "    {$test->script}";
                $scripts[] = '});';
                $scripts[] = '';
            } else {
                $scripts[] = $test->script;
            }
        }

        // Add variable extraction scripts
        foreach ($request->variables as $variable) {
            $scripts[] = '// Extract ' . $variable->name . ' from response';
            $scripts[] = 'var jsonData = pm.response.json();';

            $pathParts = explode('.', $variable->path);
            $accessPath = 'jsonData' . implode('', array_map(fn ($p): string => is_numeric($p) ? "[{$p}]" : ".{$p}", $pathParts));

            $scripts[] = "if ({$accessPath}) {";

            $setter = match ($this->variableScope) {
                'environment' => 'pm.environment.set',
                'global' => 'pm.globals.set',
                default => 'pm.collectionVariables.set',
            };

            $scripts[] = "    {$setter}(\"{$variable->name}\", {$accessPath});";
            $scripts[] = "    console.log(\"{$variable->name} updated: \" + {$accessPath});";
            $scripts[] = '}';
            $scripts[] = '';
        }

        return $scripts;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildVariables(): array
    {
        $vars = [];

        foreach ($this->variables as $key => $value) {
            $vars[] = [
                'key' => $key,
                'value' => $value,
                'type' => 'string',
            ];
        }

        return $vars;
    }

    private function getStatusText(int $code): string
    {
        return match ($code) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => 'Unknown',
        };
    }
}
