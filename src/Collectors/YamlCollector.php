<?php

declare(strict_types=1);

namespace ApiDocs\Collectors;

use ApiDocs\Data\AuthData;
use ApiDocs\Data\HeaderData;
use ApiDocs\Data\QueryParamData;
use ApiDocs\Data\RequestData;
use ApiDocs\Data\ResponseData;
use ApiDocs\Data\TestData;
use ApiDocs\Data\VariableData;
use Symfony\Component\Yaml\Yaml;

final class YamlCollector
{
    private string $yamlPath;

    public function __construct(?string $yamlPath = null)
    {
        $this->yamlPath = $yamlPath ?? resource_path('api-docs');
    }

    public function setYamlPath(string $path): self
    {
        $this->yamlPath = $path;

        return $this;
    }

    /**
     * @return array<int, RequestData>
     */
    public function collect(): array
    {
        if (! is_dir($this->yamlPath)) {
            return [];
        }

        $requests = [];
        $files = $this->findYamlFiles($this->yamlPath);

        foreach ($files as $file) {
            $content = $this->parseYamlFile($file);

            if ($content === null) {
                continue;
            }

            $folderRequests = $this->parseFileContent($content);
            $requests = array_merge($requests, $folderRequests);
        }

        return $requests;
    }

    /**
     * @return array<string>
     */
    private function findYamlFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['yaml', 'yml'], true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseYamlFile(string $filePath): ?array
    {
        try {
            $content = Yaml::parseFile($filePath);

            return is_array($content) ? $content : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int, RequestData>
     */
    private function parseFileContent(array $content): array
    {
        $requests = [];
        $folder = $content['folder'] ?? 'General';
        $folderDescription = $content['description'] ?? null;
        $yamlRequests = $content['requests'] ?? [];

        // File-level shared settings
        $sharedAuth = $this->parseAuth($content['auth'] ?? null);
        $sharedHeaders = $this->parseHeaders($content['headers'] ?? []);
        $sharedPreRequestScripts = $content['pre_request_scripts'] ?? [];

        foreach ($yamlRequests as $index => $requestData) {
            // Skip hidden requests
            if (! empty($requestData['hidden'])) {
                continue;
            }

            $request = $this->buildRequestData(
                $requestData,
                $folder,
                $index,
                $sharedAuth,
                $sharedHeaders,
                $sharedPreRequestScripts,
            );

            if ($request instanceof \ApiDocs\Data\RequestData) {
                $requests[] = $request;
            }
        }

        return $requests;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, HeaderData>  $sharedHeaders
     * @param  array<string>  $sharedPreRequestScripts
     */
    private function buildRequestData(
        array $data,
        string $folder,
        int $index,
        ?AuthData $sharedAuth,
        array $sharedHeaders,
        array $sharedPreRequestScripts,
    ): ?RequestData {
        if (! isset($data['name'], $data['method'], $data['uri'])) {
            return null;
        }

        // Merge request-level headers with shared headers
        $requestHeaders = $this->parseHeaders($data['headers'] ?? []);
        $headers = array_merge($sharedHeaders, $requestHeaders);

        // Merge pre-request scripts
        $requestScripts = $data['pre_request_scripts'] ?? [];
        $preRequestScripts = array_merge($sharedPreRequestScripts, $requestScripts);

        // Auth: request-level overrides shared
        $auth = $this->parseAuth($data['auth'] ?? null) ?? $sharedAuth;

        return new RequestData(
            name: $data['name'],
            method: strtoupper((string) $data['method']),
            uri: ltrim((string) $data['uri'], '/'),
            description: $data['description'] ?? null,
            folder: $data['folder'] ?? $folder,
            order: $data['order'] ?? $index,
            body: $data['body'] ?? null,
            bodyMode: $data['body_mode'] ?? 'raw',
            bodyLanguage: $data['body_language'] ?? 'json',
            bodyMerge: $data['body_merge'] ?? false,
            bodyExcept: $data['body_except'] ?? [],
            headers: $headers,
            queryParams: $this->parseQueryParams($data['query_params'] ?? []),
            responses: $this->parseResponses($data['responses'] ?? []),
            variables: $this->parseVariables($data['variables'] ?? []),
            tests: $this->parseTests($data['tests'] ?? []),
            preRequestScripts: $preRequestScripts,
            auth: $auth,
            middleware: $data['middleware'] ?? [],
            resource: $data['resource'] ?? null,
            resourceStatus: $data['resource_status'] ?? 200,
            resourceWrapped: $data['resource_wrapped'] ?? null,
            resourceCollection: $data['resource_collection'] ?? null,
        );
    }

    /**
     * @param  array<int|string, mixed>  $headers
     * @return array<int, HeaderData>
     */
    private function parseHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $key => $value) {
            if (is_array($value)) {
                $result[] = new HeaderData(
                    key: $value['key'] ?? (string) $key,
                    value: $value['value'] ?? '',
                    description: $value['description'] ?? null,
                    disabled: $value['disabled'] ?? false,
                );
            } else {
                $result[] = new HeaderData(
                    key: (string) $key,
                    value: (string) $value,
                );
            }
        }

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return array<int, QueryParamData>
     */
    private function parseQueryParams(array $params): array
    {
        $result = [];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $result[] = new QueryParamData(
                    key: $value['key'] ?? (string) $key,
                    value: $value['value'] ?? '',
                    description: $value['description'] ?? null,
                    disabled: $value['disabled'] ?? false,
                );
            } else {
                $result[] = new QueryParamData(
                    key: (string) $key,
                    value: (string) $value,
                );
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $responses
     * @return array<int, ResponseData>
     */
    private function parseResponses(array $responses): array
    {
        $result = [];

        foreach ($responses as $response) {
            if (! isset($response['name'])) {
                continue;
            }

            $result[] = new ResponseData(
                name: $response['name'],
                status: $response['status'] ?? 200,
                body: $response['body'] ?? [],
                headers: $response['headers'] ?? [],
            );
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $variables
     * @return array<int, VariableData>
     */
    private function parseVariables(array $variables): array
    {
        $result = [];

        foreach ($variables as $variable) {
            if (! isset($variable['name'], $variable['path'])) {
                continue;
            }

            $result[] = new VariableData(
                name: $variable['name'],
                path: $variable['path'],
                scope: $variable['scope'] ?? 'environment',
            );
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tests
     * @return array<int, TestData>
     */
    private function parseTests(array $tests): array
    {
        $result = [];

        foreach ($tests as $test) {
            if (! isset($test['script'])) {
                continue;
            }

            $result[] = new TestData(
                script: $test['script'],
                name: $test['name'] ?? null,
            );
        }

        return $result;
    }

    private function parseAuth(array|bool|null $auth): ?AuthData
    {
        if ($auth === null) {
            return null;
        }

        if ($auth === true) {
            return new AuthData(type: 'bearer', token: '{{BEARER_TOKEN}}');
        }

        if ($auth === false) {
            return null;
        }

        return new AuthData(
            type: $auth['type'] ?? 'bearer',
            token: $auth['token'] ?? '{{BEARER_TOKEN}}',
            username: $auth['username'] ?? null,
            password: $auth['password'] ?? null,
            apiKey: $auth['api_key'] ?? null,
            apiKeyHeader: $auth['api_key_header'] ?? 'X-API-Key',
        );
    }
}