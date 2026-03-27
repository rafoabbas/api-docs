<?php

declare(strict_types=1);

namespace ApiDocs\Data;

final class ResponseData
{
    /**
     * @param  string  $name  Response example name
     * @param  int  $status  HTTP status code
     * @param  array<string, mixed>  $body  Response body
     * @param  array<string, string>  $headers  Response headers
     */
    public function __construct(
        public readonly string $name,
        public readonly int $status = 200,
        public readonly array $body = [],
        public readonly array $headers = [],
    ) {}
}
