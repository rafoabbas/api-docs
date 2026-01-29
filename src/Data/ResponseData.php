<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final readonly class ResponseData
{
    /**
     * @param  string  $name  Response example name
     * @param  int  $status  HTTP status code
     * @param  array<string, mixed>  $body  Response body
     * @param  array<string, string>  $headers  Response headers
     */
    public function __construct(
        public string $name,
        public int $status = 200,
        public array $body = [],
        public array $headers = [],
    ) {}
}
