<?php

declare(strict_types=1);

namespace ApiDocs\Collectors;

use ApiDocs\Data\RequestData;

final class RequestMerger
{
    /**
     * Merge requests from multiple collectors.
     * Attribute-defined requests take priority over YAML-defined requests.
     *
     * @param  array<int, RequestData>  $attributeRequests  Requests from AttributeCollector (priority)
     * @param  array<int, RequestData>  $yamlRequests  Requests from YamlCollector
     * @return array<int, RequestData>
     */
    public function merge(array $attributeRequests, array $yamlRequests): array
    {
        $merged = [];
        $attributeKeys = [];

        // First, add all attribute requests (they have priority)
        foreach ($attributeRequests as $request) {
            $key = $this->generateKey($request);
            $attributeKeys[$key] = true;
            $merged[$key] = $request;
        }

        // Then, add YAML requests that don't conflict with attribute requests
        foreach ($yamlRequests as $request) {
            $key = $this->generateKey($request);

            // Skip if this endpoint is already defined via attributes
            if (isset($attributeKeys[$key])) {
                continue;
            }

            // Add YAML request if not already present
            if (! isset($merged[$key])) {
                $merged[$key] = $request;
            }
        }

        return array_values($merged);
    }

    /**
     * Generate a unique key for a request based on method and URI.
     */
    private function generateKey(RequestData $request): string
    {
        $uri = $this->normalizeUri($request->uri);

        return strtoupper($request->method).':'.$uri;
    }

    /**
     * Normalize URI for comparison (remove leading/trailing slashes).
     */
    private function normalizeUri(string $uri): string
    {
        return trim($uri, '/');
    }
}
