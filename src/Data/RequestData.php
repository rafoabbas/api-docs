<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final class RequestData
{
    /**
     * @param  string  $name  Request name
     * @param  string  $method  HTTP method
     * @param  string  $uri  Request URI
     * @param  string|null  $description  Request description
     * @param  string  $folder  Folder path
     * @param  int  $order  Order within folder
     * @param  array<string, mixed>|null  $body  Request body
     * @param  string  $bodyMode  Body mode
     * @param  string  $bodyLanguage  Body language
     * @param  bool  $bodyMerge  Merge body with FormRequest-resolved body
     * @param  array<string>  $bodyExcept  Keys to exclude when merging body
     * @param  array<int, HeaderData>  $headers  Custom headers
     * @param  array<int, QueryParamData>  $queryParams  Query parameters
     * @param  array<int, ResponseData>  $responses  Example responses
     * @param  array<int, VariableData>  $variables  Variables to extract
     * @param  array<int, TestData>  $tests  Test scripts
     * @param  array<string>  $preRequestScripts  Pre-request scripts
     * @param  AuthData|null  $auth  Authentication config
     * @param  array<string>  $middleware  Route middleware
     * @param  string|null  $resource  Resource class for auto-response resolution
     * @param  int  $resourceStatus  HTTP status code for resource response
     * @param  bool|null  $resourceWrapped  Wrap in ApiResponse format
     * @param  bool|null  $resourceCollection  Return as collection
     * @param  bool  $deprecated  Whether endpoint is deprecated
     * @param  string|null  $deprecatedReason  Reason for deprecation
     * @param  string|null  $deprecatedReplacement  Suggested replacement endpoint
     * @param  array<string, mixed>|null  $bodySchema  OpenAPI schema from validation rules
     * @param  bool  $hasFileUpload  Whether request contains file uploads
     * @param  bool  $isPaginated  Whether response is paginated
     */
    public function __construct(
        public readonly string $name,
        public readonly string $method,
        public readonly string $uri,
        public readonly ?string $description = null,
        public readonly string $folder = 'General',
        public readonly int $order = 0,
        public readonly ?array $body = null,
        public readonly string $bodyMode = 'raw',
        public readonly string $bodyLanguage = 'json',
        public readonly bool $bodyMerge = false,
        public readonly array $bodyExcept = [],
        public readonly array $headers = [],
        public readonly array $queryParams = [],
        public readonly array $responses = [],
        public readonly array $variables = [],
        public readonly array $tests = [],
        public readonly array $preRequestScripts = [],
        public readonly ?AuthData $auth = null,
        public readonly array $middleware = [],
        public readonly ?string $resource = null,
        public readonly int $resourceStatus = 200,
        public readonly ?bool $resourceWrapped = null,
        public readonly ?bool $resourceCollection = null,
        public readonly bool $deprecated = false,
        public readonly ?string $deprecatedReason = null,
        public readonly ?string $deprecatedReplacement = null,
        public readonly ?array $bodySchema = null,
        public readonly bool $hasFileUpload = false,
        public readonly bool $isPaginated = false,
    ) {}
}
