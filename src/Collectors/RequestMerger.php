<?php

declare(strict_types=1);

namespace ApiDocs\Collectors;

use ApiDocs\Data\RequestData;
use ApiDocs\Data\ResponseData;
use ApiDocs\Resolvers\ResponseResolver;

final class RequestMerger
{
    private readonly ResponseResolver $responseResolver;

    public function __construct()
    {
        $this->responseResolver = new ResponseResolver;
    }

    /**
     * Merge requests from multiple collectors.
     * YAML-defined fields take priority over auto-collected attribute fields.
     * Attribute-collected data fills in gaps (middleware, auto-resolved body, etc.).
     *
     * @param  array<int, RequestData>  $attributeRequests  Requests from AttributeCollector
     * @param  array<int, RequestData>  $yamlRequests  Requests from YamlCollector (priority)
     * @return array<int, RequestData>
     */
    public function merge(array $attributeRequests, array $yamlRequests): array
    {
        $merged = [];
        $attributeMap = [];

        // Index attribute requests by method:uri
        foreach ($attributeRequests as $request) {
            $key = $this->generateKey($request);
            $attributeMap[$key] = $request;
        }

        $yamlKeys = [];

        // Process YAML requests first (they have priority)
        foreach ($yamlRequests as $yamlRequest) {
            $key = $this->generateKey($yamlRequest);
            $yamlKeys[$key] = true;

            $attrRequest = $attributeMap[$key] ?? null;

            if ($attrRequest !== null) {
                // Both sources exist - merge field-by-field with YAML priority
                $merged[$key] = $this->mergeRequests($yamlRequest, $attrRequest);
            } else {
                // Only YAML - resolve resource if specified
                $merged[$key] = $this->resolveYamlRequest($yamlRequest);
            }
        }

        // Add attribute-only requests (no YAML definition)
        foreach ($attributeRequests as $request) {
            $key = $this->generateKey($request);

            if (! isset($yamlKeys[$key])) {
                $merged[$key] = $request;
            }
        }

        return array_values($merged);
    }

    /**
     * Merge YAML and attribute requests field-by-field.
     * YAML explicitly-set fields take priority.
     * Attribute-collected data fills gaps (middleware, auto-resolved body/responses).
     */
    private function mergeRequests(RequestData $yaml, RequestData $attr): RequestData
    {
        // Body merge logic
        $body = $this->resolveBody($yaml, $attr);

        // Responses: YAML takes priority, then resource resolution, then attribute
        $responses = $this->resolveResponses($yaml, $attr);

        return new RequestData(
            name: $yaml->name,
            method: $yaml->method,
            uri: $attr->uri, // Use attribute URI (from actual route, has proper parameter names)
            description: $yaml->description ?? $attr->description,
            folder: $yaml->folder !== 'General' ? $yaml->folder : $attr->folder,
            order: $yaml->order,
            body: $body,
            bodyMode: $yaml->bodyMode !== 'raw' ? $yaml->bodyMode : $attr->bodyMode,
            bodyLanguage: $yaml->bodyLanguage !== 'json' ? $yaml->bodyLanguage : $attr->bodyLanguage,
            headers: count($yaml->headers) > 0 ? $yaml->headers : $attr->headers,
            queryParams: count($yaml->queryParams) > 0 ? $yaml->queryParams : $attr->queryParams,
            responses: $responses,
            variables: count($yaml->variables) > 0 ? $yaml->variables : $attr->variables,
            tests: count($yaml->tests) > 0 ? $yaml->tests : $attr->tests,
            preRequestScripts: count($yaml->preRequestScripts) > 0 ? $yaml->preRequestScripts : $attr->preRequestScripts,
            auth: $yaml->auth ?? $attr->auth,
            middleware: $attr->middleware, // Always from route
        );
    }

    /**
     * Resolve body data, handling body_merge logic.
     *
     * @return array<string, mixed>|null
     */
    private function resolveBody(RequestData $yaml, RequestData $attr): ?array
    {
        // If YAML has body_merge enabled, merge YAML body with attribute-resolved body
        if ($yaml->bodyMerge && $attr->body !== null) {
            $attrBody = $attr->body;

            // Apply except filter
            if (count($yaml->bodyExcept) > 0) {
                $attrBody = array_diff_key($attrBody, array_flip($yaml->bodyExcept));
            }

            return array_merge($attrBody, $yaml->body ?? []);
        }

        // YAML body takes priority if set
        if ($yaml->body !== null) {
            return $yaml->body;
        }

        return $attr->body;
    }

    /**
     * Resolve responses from YAML resource definition or fallback to attribute responses.
     *
     * @return array<int, ResponseData>
     */
    private function resolveResponses(RequestData $yaml, RequestData $attr): array
    {
        // If YAML has explicit responses, use them
        if (count($yaml->responses) > 0) {
            return $yaml->responses;
        }

        // If YAML specifies a resource class, resolve it
        if ($yaml->resource !== null) {
            return $this->resolveResourceResponses($yaml);
        }

        // Fall back to attribute-collected responses
        return $attr->responses;
    }

    /**
     * Resolve a YAML-only request (no attribute counterpart).
     */
    private function resolveYamlRequest(RequestData $yaml): RequestData
    {
        // If resource is specified and no explicit responses, resolve the resource
        if ($yaml->resource !== null && count($yaml->responses) === 0) {
            $responses = $this->resolveResourceResponses($yaml);

            return new RequestData(
                name: $yaml->name,
                method: $yaml->method,
                uri: $yaml->uri,
                description: $yaml->description,
                folder: $yaml->folder,
                order: $yaml->order,
                body: $yaml->body,
                bodyMode: $yaml->bodyMode,
                bodyLanguage: $yaml->bodyLanguage,
                headers: $yaml->headers,
                queryParams: $yaml->queryParams,
                responses: $responses,
                variables: $yaml->variables,
                tests: $yaml->tests,
                preRequestScripts: $yaml->preRequestScripts,
                auth: $yaml->auth,
                middleware: $yaml->middleware,
            );
        }

        return $yaml;
    }

    /**
     * Resolve resource class into response data.
     *
     * @return array<int, ResponseData>
     */
    private function resolveResourceResponses(RequestData $request): array
    {
        if ($request->resource === null || ! class_exists($request->resource)) {
            return [];
        }

        $isWrapped = $request->resourceWrapped ?? true;
        $isCollection = $request->resourceCollection ?? false;
        $resourceData = $this->responseResolver->resolve($request->resource, wrapped: false);

        if ($isWrapped) {
            $responseBody = [
                'success' => true,
                'status_code' => $request->resourceStatus,
                'message' => null,
                'data' => $isCollection ? [$resourceData] : $resourceData,
            ];
        } else {
            $responseBody = ['data' => $isCollection ? [$resourceData] : $resourceData];
        }

        return [new ResponseData('Success', $request->resourceStatus, $responseBody)];
    }

    /**
     * Generate a unique key for a request based on method and URI.
     */
    private function generateKey(RequestData $request): string
    {
        $uri = $this->normalizeUri($request->uri);

        return strtoupper($request->method) . ':' . $uri;
    }

    /**
     * Normalize URI for comparison (remove leading/trailing slashes).
     */
    private function normalizeUri(string $uri): string
    {
        return trim($uri, '/');
    }
}