<?php

declare(strict_types=1);

namespace ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ApiResponse
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
