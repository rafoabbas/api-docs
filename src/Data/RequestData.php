<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final readonly class RequestData
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
     * @param  array<int, HeaderData>  $headers  Custom headers
     * @param  array<int, QueryParamData>  $queryParams  Query parameters
     * @param  array<int, ResponseData>  $responses  Example responses
     * @param  array<int, VariableData>  $variables  Variables to extract
     * @param  array<int, TestData>  $tests  Test scripts
     * @param  array<string>  $preRequestScripts  Pre-request scripts
     * @param  AuthData|null  $auth  Authentication config
     * @param  array<string>  $middleware  Route middleware
     */
    public function __construct(
        public string $name,
        public string $method,
        public string $uri,
        public ?string $description = null,
        public string $folder = 'General',
        public int $order = 0,
        public ?array $body = null,
        public string $bodyMode = 'raw',
        public string $bodyLanguage = 'json',
        public array $headers = [],
        public array $queryParams = [],
        public array $responses = [],
        public array $variables = [],
        public array $tests = [],
        public array $preRequestScripts = [],
        public ?AuthData $auth = null,
        public array $middleware = [],
    ) {}
}
