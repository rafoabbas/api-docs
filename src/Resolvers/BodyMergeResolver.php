<?php

declare(strict_types=1);

namespace ApiDocs\Resolvers;

use ApiDocs\Attributes\ApiBody;
use ReflectionMethod;

final readonly class BodyMergeResolver
{
    public function __construct(
        private BodyResolver $bodyResolver = new BodyResolver,
    ) {}

    /**
     * Resolve body data, optionally merging ApiBody with FormRequest.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(ReflectionMethod $method, ?ApiBody $bodyAttr, string $httpMethod): ?array
    {
        // If not a method that accepts body, return null
        if (! in_array($httpMethod, ['POST', 'PUT', 'PATCH'])) {
            return null;
        }

        // No ApiBody attribute - just resolve from FormRequest
        if (! $bodyAttr instanceof \ApiDocs\Attributes\ApiBody) {
            return $this->bodyResolver->resolve($method);
        }

        // ApiBody with merge: true - merge with FormRequest
        if ($bodyAttr->merge) {
            $formRequestBody = $this->bodyResolver->resolve($method) ?? [];

            // Apply except filter
            $formRequestBody = $this->applyExcept($formRequestBody, $bodyAttr->except);

            return array_merge($formRequestBody, $bodyAttr->data);
        }

        // ApiBody with merge: false (default) - use only ApiBody data
        return $bodyAttr->data;
    }

    /**
     * Remove specified keys from body.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string>  $except
     * @return array<string, mixed>
     */
    private function applyExcept(array $body, array $except): array
    {
        if (count($except) === 0) {
            return $body;
        }

        return array_diff_key($body, array_flip($except));
    }
}
